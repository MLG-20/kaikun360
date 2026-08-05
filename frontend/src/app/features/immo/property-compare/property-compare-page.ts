import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { CatalogService } from '../../../core/api/catalog.service';
import { CompareStore } from '../../../core/state/compare-store';
import { Property } from '../../../models/property.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/** État de chargement de la comparaison. */
type LoadState = 'idle' | 'loading' | 'ready' | 'failed';

/** Une ligne du tableau : un critère, et sa valeur pour chaque bien comparé. */
interface CompareRow {
  label: string;
  /** Valeurs alignées sur l'ordre des colonnes ; `null` = non renseigné. */
  values: (string | null)[];
  /**
   * Les valeurs de cette ligne diffèrent-elles d'un bien à l'autre ?
   *
   * ⚠️ C'est l'information utile d'un comparateur. Un tableau où tout se lit au
   * même niveau oblige à relire ligne par ligne pour trouver ce qui sépare deux
   * biens — exactement le défaut corrigé sur les fiches back-office en F8.3.
   */
  differs: boolean;
}

/**
 * Comparateur de biens (F8.15.e) — route `/immobilier/comparer`.
 *
 * `GET /properties/compare` existe depuis **B2.5** et n'avait **aucun
 * appelant** : le CDC §2.1 range pourtant la « comparaison » parmi les
 * fonctions de Kaikun Immo. Il ne manquait pas seulement un écran, il manquait
 * un moyen de CHOISIR — d'où `CompareStore`, alimenté par les cases du
 * catalogue, dont cette page est le débouché.
 *
 * ⚠️ **La réponse peut être plus courte que la demande.** Le serveur ne renvoie
 * que les biens *publiés*, ignore les ids inconnus et tronque au-delà de 4 —
 * silencieusement. Une sélection vieille de trois semaines peut donc contenir un
 * bien retiré de la vente. La page compare ce qu'elle a reçu à ce qu'elle a
 * demandé et le **dit**, au lieu de laisser un bien s'évaporer du tableau.
 */
@Component({
  selector: 'app-property-compare-page',
  imports: [RouterLink, BackLinkComponent],
  templateUrl: './property-compare-page.html',
  styleUrl: './property-compare-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PropertyComparePageComponent {
  private readonly catalog = inject(CatalogService);
  protected readonly compare = inject(CompareStore);

  protected readonly state = signal<LoadState>('idle');
  /** Biens renvoyés par le serveur, réordonnés selon la sélection. */
  protected readonly properties = signal<Property[]>([]);

  /**
   * Nombre de biens sélectionnés que le serveur n'a pas renvoyés (dépubliés,
   * supprimés). Zéro dans le cas normal.
   */
  protected readonly missing = computed(
    () => this.compare.count() - this.properties().length,
  );

  /** Rien à comparer : la page le dit et renvoie au catalogue. */
  protected readonly isEmpty = computed(
    () => this.state() === 'ready' && this.properties().length === 0,
  );

  constructor() {
    this.load();
  }

  /**
   * Charge la comparaison depuis la sélection courante.
   *
   * ⚠️ La sélection ne voyage **pas par l'URL**. Ce serait tentant (une
   * comparaison partageable), mais elle vient du `localStorage` : lue au rendu
   * serveur elle serait vide, et l'URL et le store se contrediraient au moindre
   * retrait depuis cette page. Le store est la seule source.
   */
  protected load(): void {
    const ids = this.compare.ids();

    if (ids.length === 0) {
      this.properties.set([]);
      this.state.set('ready');
      return;
    }

    this.state.set('loading');
    this.catalog.compareProperties(ids).subscribe({
      next: (response) => {
        // Le serveur renvoie les biens dans SON ordre (celui de la table) ; on
        // rétablit l'ordre de sélection, seul ordre que l'utilisateur a en tête.
        const parId = new Map(response.data.map((bien) => [bien.id, bien]));
        this.properties.set(
          ids.map((id) => parId.get(id)).filter((bien): bien is Property => !!bien),
        );
        this.state.set('ready');
      },
      error: () => this.state.set('failed'),
    });
  }

  /** Retire un bien du tableau (et de la sélection), puis recharge. */
  protected remove(id: number): void {
    this.compare.remove(id);
    this.load();
  }

  /** Vide la comparaison. */
  protected clear(): void {
    this.compare.clear();
    this.load();
  }

  /** En-têtes de colonnes : les biens effectivement comparés. */
  protected readonly columns = computed(() => this.properties());

  /**
   * Les lignes du tableau. Construites ici et non dans le gabarit pour que le
   * calcul de « ce qui diffère » se fasse une fois par critère.
   */
  protected readonly rows = computed<CompareRow[]>(() => {
    const biens = this.properties();
    if (biens.length === 0) {
      return [];
    }

    const ligne = (label: string, lire: (bien: Property) => string | null): CompareRow => {
      const values = biens.map(lire);
      // Une ligne « diffère » dès que deux valeurs ne sont pas identiques. Avec
      // un seul bien comparé, rien ne diffère par construction.
      const differs = new Set(values.map((v) => v ?? '—')).size > 1;
      return { label, values, differs };
    };

    return [
      ligne('Prix', (bien) => formatFcfa(bien.price_xof)),
      ligne('Type de bien', (bien) => bien.type_label ?? null),
      ligne('Commune', (bien) => bien.location?.commune ?? null),
      ligne('Département', (bien) => bien.location?.department ?? null),
      ligne('Région', (bien) => bien.location?.region ?? null),
      ligne('Zone touristique', (bien) => (bien.location?.tourist_zone ? 'Oui' : 'Non')),
      ligne('Vérification', (bien) => this.verificationLabel(bien.verification_level)),
      ligne('Photos', (bien) => `${bien.photos?.length ?? 0}`),
      ligne('Publié le', (bien) => this.dateCourte(bien.published_at)),
    ];
  });

  /** Prix formaté pour l'en-tête de colonne. */
  protected prix(bien: Property): string | null {
    return formatFcfa(bien.price_xof);
  }

  /**
   * Libellé lisible du niveau de vérification.
   *
   * ⚠️ **Même règle que le badge du catalogue** (`verifiedBadge`), délibérément :
   * `verification_level` est une colonne libre, pas une enum — en base elle ne
   * vaut aujourd'hui que `unverified`, et rien dans le flux de validation ne
   * l'écrit (l'approbation d'un bien change son `status`). Inventer ici des
   * paliers que le produit ne produit pas ferait dire au comparateur ce que le
   * catalogue ne dit pas, sur le critère de confiance justement.
   */
  private verificationLabel(niveau: string | null): string {
    return niveau && niveau !== 'unverified' && niveau !== 'aucun'
      ? 'Vérifié'
      : 'Non vérifié';
  }

  /** Date au format court, ou null si le bien n'est pas daté. */
  private dateCourte(iso: string | null): string | null {
    if (!iso) {
      return null;
    }
    const date = new Date(iso);
    return Number.isNaN(date.getTime())
      ? null
      : date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  }
}
