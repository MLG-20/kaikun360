import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';

import { CatalogComponent } from '../../shared/components/catalog/catalog';
import { PageHeroComponent } from '../../shared/components/page-hero/page-hero';
import { SearchEngineComponent } from '../../shared/components/search-engine/search-engine';
import { UNIVERSES, Universe } from '../../shared/components/catalog/catalog.config';

/** Textes d'ouverture d'un univers de recherche (surtitre, titre, accroche). */
interface CatalogIntro {
  eyebrow: string;
  title: string;
  lead: string;
}

/** Textes de l'état « aucun résultat » d'un univers (F21, généralisé). */
interface CatalogEmptyState {
  message: string;
  ctaLabel: string;
  ctaSubject: string;
}

/** Textes de l'état « aucun résultat » d'un univers (F21, généralisé). */
interface CatalogEmptyState {
  message: string;
  ctaLabel: string;
  ctaSubject: string;
}

/**
 * Page de résultats de recherche (F2.1) — route `/recherche`.
 *
 * Hôte générique du catalogue : l'univers vient du query param `univers`
 * (posé par le moteur de recherche), le reste des filtres est géré par le
 * composant `app-catalog` lui-même.
 *
 * ## Le bandeau (F12)
 *
 * Cette page était la seule grande page publique à s'ouvrir sur un titre nu.
 * Elle a désormais son bandeau, avec une particularité : **une page, cinq
 * visages**. Le titre et l'image suivent l'onglet d'univers actif, parce qu'un
 * visiteur qui cherche une villa à la nuit et un autre qui cherche un 4×4 ne
 * sont pas en train de faire la même chose — les accueillir avec la même phrase
 * générique serait une occasion perdue.
 *
 * Les textes ci-dessous sont les **valeurs par défaut** : le back-office peut
 * les remplacer univers par univers (clés `recherche.immobilier`, …), et leur
 * donner une image de fond. Sans saisie, l'image est héritée de la grande page
 * de l'univers correspondant.
 */
@Component({
  selector: 'app-catalog-page',
  imports: [PageHeroComponent, SearchEngineComponent, CatalogComponent],
  templateUrl: './catalog-page.html',
  styleUrl: './catalog-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly params = toSignal(this.route.queryParamMap);

  /** Univers demandé, validé contre le registre (repli sur « immobilier »). */
  readonly universe = computed<Universe>(() => {
    const raw = this.params()?.get('univers');
    return raw && raw in UNIVERSES ? (raw as Universe) : 'immobilier';
  });

  /**
   * Clé du bandeau au back-office.
   *
   * ⚠️ Doit correspondre EXACTEMENT à une entrée de `HeroCatalog::BANNERS` côté
   * serveur (`recherche.immobilier`, `recherche.nuitees`…). Une clé inconnue ne
   * casse rien — la page affichera simplement ses textes par défaut sur le
   * dégradé — mais elle deviendrait impilotable en silence.
   */
  readonly heroKey = computed(() => `recherche.${this.universe()}`);

  /**
   * Textes d'ouverture par univers.
   *
   * Écrits pour la page de RÉSULTATS, et pas recopiés de la grande page de
   * l'univers : ici le visiteur a déjà choisi ce qu'il cherche, on l'aide à
   * trier, on ne lui présente plus le métier. C'est la même raison qui fait
   * qu'une surcharge de texte saisie au back-office ne se transmet jamais d'une
   * page à l'autre — seule l'image est héritée.
   */
  private readonly intros: Record<Universe, CatalogIntro> = {
    immobilier: {
      eyebrow: 'Catalogue Immobilier',
      title: 'Trouvez le bien qui vous ressemble',
      lead:
        'Appartements, villas, terrains et locaux professionnels, tous contrôlés ' +
        'avant leur mise en ligne. Affinez par ville, budget ou surface : chaque ' +
        'annonce affichée est une annonce que vous pouvez visiter.',
    },
    nuitees: {
      eyebrow: 'Catalogue Nuitées',
      title: 'Dormez là où vous vous sentirez chez vous',
      lead:
        'Appartements meublés, villas et maisons d’hôtes réservables à la nuit. ' +
        'Comparez les équipements, la caution et les disponibilités réelles avant ' +
        'de réserver — sans mauvaise surprise à l’arrivée.',
    },
    tourisme: {
      eyebrow: 'Catalogue Tourisme',
      title: 'Le Sénégal, une expérience à la fois',
      lead:
        'Excursions, circuits culturels et escapades nature, conduits par des ' +
        'guides vérifiés. Choisissez vos dates, vérifiez les places restantes, ' +
        'et laissez-vous emmener.',
    },
    transport: {
      eyebrow: 'Catalogue Transport',
      title: 'Le bon véhicule, pour le bon trajet',
      lead:
        'Berlines, 4×4, minibus et pirogues, avec ou sans chauffeur, proposés par ' +
        'des prestataires vérifiés. Filtrez selon le nombre de places et votre ' +
        'budget, puis demandez votre réservation.',
    },
    mobilite: {
      eyebrow: 'Catalogue Mobilité',
      title: 'Un trajet organisé, du départ à l’arrivée',
      lead:
        'Navettes aéroport, transferts interurbains et excursions organisées, ' +
        'opérés par des prestataires vérifiés. Comparez horaires, points de ' +
        'départ et tarifs en un coup d’œil.',
    },
  };

  /** Textes d'ouverture de l'univers courant. */
  readonly intro = computed<CatalogIntro>(() => this.intros[this.universe()]);

  /**
   * États « aucun résultat » par univers (F21, généralisé depuis Tourisme) :
   * les catalogues démarrent vides le temps que l'offre se remplisse, le
   * message générique laissait plutôt penser à une panne. Un bouton WhatsApp
   * vers une demande sur mesure remplace le mur muet.
   */
  private readonly emptyStates: Record<Universe, CatalogEmptyState> = {
    immobilier: {
      message: 'Biens bientôt disponibles',
      ctaLabel: 'Demander un bien sur mesure',
      ctaSubject: 'un bien immobilier sur mesure',
    },
    nuitees: {
      message: 'Séjours bientôt disponibles',
      ctaLabel: 'Demander un séjour sur mesure',
      ctaSubject: 'un séjour sur mesure',
    },
    tourisme: {
      message: 'Circuits bientôt disponibles',
      ctaLabel: 'Demander un circuit sur mesure',
      ctaSubject: 'un circuit sur mesure',
    },
    transport: {
      message: 'Véhicules bientôt disponibles',
      ctaLabel: 'Demander un véhicule sur mesure',
      ctaSubject: 'un véhicule sur mesure',
    },
    mobilite: {
      message: 'Services de mobilité bientôt disponibles',
      ctaLabel: 'Demander un trajet sur mesure',
      ctaSubject: 'un trajet sur mesure',
    },
  };

  /** État « aucun résultat » de l'univers courant. */
  readonly emptyState = computed<CatalogEmptyState>(() => this.emptyStates[this.universe()]);
}
