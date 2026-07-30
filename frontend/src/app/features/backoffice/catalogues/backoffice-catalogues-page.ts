import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';

import { HttpErrorResponse } from '@angular/common/http';

import { AdminPropertyPatch, AdminService, CatalogQuery } from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Paginated } from '../../../core/api/pagination.model';
import { Experience } from '../../../models/experience.model';
import { Property } from '../../../models/property.model';
import { Vehicle } from '../../../models/vehicle.model';

/** Type de catalogue affiché (onglet). */
type CatalogType = 'property' | 'vehicle' | 'experience';

/** Métadonnées d'affichage d'un onglet. */
interface TabMeta {
  key: CatalogType;
  label: string;
}

/**
 * Ligne normalisée d'un catalogue : les 3 ressources (bien / véhicule /
 * expérience) ont des formes différentes, on les ramène à une même structure
 * pour un tableau unique et lisible.
 */
interface CatalogRow {
  id: number;
  label: string;
  reference: string | null;
  status: string | null;
  statusLabel: string;
  ownerName: string | null;
  priceXof: number | null;
  priceSuffix: string;
  date: string | null;
}

/** Une option de filtre par statut. */
interface StatusOption {
  value: string;
  label: string;
}

/**
 * Écran **Catalogues** du back-office (F7.2.b) — navigateur de supervision.
 *
 * Contrairement aux catalogues publics (limités aux ressources publiées), cette
 * vue expose **TOUS les statuts** (brouillon, en attente, publié, suspendu,
 * rejeté, archivé) des biens, véhicules et expériences, pour que l'équipe
 * supervise l'ensemble de l'offre. La validation/publication reste dans l'écran
 * **Validation** (F7.2.a). Filtres : statut + recherche.
 *
 * **F7.3.g — les BIENS deviennent modifiables ici** (dette CDC §15 « un admin peut
 * modifier »). Deux gestes par ligne, réservés à l'onglet Biens :
 *  - **corriger** l'intitulé public, le prix et la description — ce que l'équipe
 *    reprend en pratique sur une annonce mal saisie ; la localisation, les médias
 *    et le reste restent au formulaire du propriétaire ;
 *  - **archiver** l'annonce (et l'en sortir), ce qui la retire du catalogue sans
 *    rien supprimer : réservations et documents restent intacts.
 *
 * Périmètre arbitré : ni création à la place d'un propriétaire, ni réattribution
 * à un autre compte — réattribuer change qui touche les loyers. Le bien reste à
 * son propriétaire, et chaque geste est tracé au journal d'audit.
 *
 * ⚠️ Sortir de l'archive renvoie le bien **en file de validation**, jamais
 * directement en ligne : c'est le serveur qui l'impose, l'écran l'annonce.
 *
 * Endpoints : `GET /admin/properties|vehicles|experiences` (déjà livrés en B13,
 * garde `consulter:dashboard-admin`).
 */
@Component({
  selector: 'app-backoffice-catalogues-page',
  imports: [FormsModule],
  templateUrl: './backoffice-catalogues-page.html',
  styleUrl: './backoffice-catalogues-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeCataloguesPageComponent {
  private readonly admin = inject(AdminService);

  /** Onglets (un par univers catalogué). */
  protected readonly tabs: readonly TabMeta[] = [
    { key: 'property', label: 'Biens' },
    { key: 'vehicle', label: 'Véhicules' },
    { key: 'experience', label: 'Expériences' },
  ];

  /** Onglet courant. */
  protected readonly selected = signal<CatalogType>('property');

  /** Lignes de la page courante (normalisées). */
  protected readonly rows = signal<CatalogRow[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Filtres. */
  protected status = '';
  protected search = '';

  /** Options de statut (l'archivage n'existe que pour les biens, mais sans effet ailleurs). */
  protected readonly statusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente_validation', label: 'En attente' },
    { value: 'publie', label: 'Publié' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'rejete', label: 'Rejeté' },
    { value: 'archive', label: 'Archivé' },
  ];

  // --- Correction / archivage d'un bien (F7.3.g) -------------------------------

  /** Ligne dont le panneau d'action est ouvert. */
  protected readonly openRowId = signal<number | null>(null);
  /** Nature du panneau : correction ou archivage. */
  protected readonly panelKind = signal<'edit' | 'archive' | null>(null);
  /** Ligne en cours d'écriture (verrouille ses boutons). */
  protected readonly savingId = signal<number | null>(null);

  /** Saisies des panneaux. */
  protected editForm: AdminPropertyPatch = { title: '', price_xof: null, description: '' };
  protected archiveReason = '';

  protected readonly actionError = signal<string | null>(null);
  protected readonly actionDone = signal<string | null>(null);

  constructor() {
    this.load();
  }

  /** Les gestes d'édition ne concernent que les biens. */
  protected canEdit(): boolean {
    return this.selected() === 'property';
  }

  /** Ouvre la correction d'une ligne (préremplie). */
  protected openEdit(row: CatalogRow): void {
    this.resetPanels();
    this.editForm = {
      title: row.label,
      price_xof: row.priceXof,
      // La liste ne transporte pas la description : laissée vide, elle n'est pas
      // envoyée — on ne risque donc pas d'effacer un texte existant.
      description: '',
    };
    this.panelKind.set('edit');
    this.openRowId.set(row.id);
  }

  /** Ouvre l'archivage (ou la sortie d'archive) d'une ligne. */
  protected openArchive(row: CatalogRow): void {
    this.resetPanels();
    this.panelKind.set('archive');
    this.openRowId.set(row.id);
  }

  protected closePanel(): void {
    this.openRowId.set(null);
    this.panelKind.set(null);
  }

  private resetPanels(): void {
    this.archiveReason = '';
    this.actionError.set(null);
    this.actionDone.set(null);
  }

  /** Enregistre la correction. */
  protected saveEdit(row: CatalogRow): void {
    const title = (this.editForm.title ?? '').trim();
    if (!title) {
      this.actionError.set('Le bien a besoin d’un intitulé.');
      return;
    }

    const payload: AdminPropertyPatch = { title, price_xof: this.editForm.price_xof ?? null };
    const description = (this.editForm.description ?? '').trim();
    // Champ laissé vide = non touché : on ne remplace pas une description par du vide.
    if (description) payload.description = description;

    this.write(row, this.admin.adminUpdateProperty(row.id, payload), 'Bien corrigé.');
  }

  /** Archive l'annonce (motif facultatif, tracé). */
  protected archive(row: CatalogRow): void {
    this.write(
      row,
      this.admin.adminArchiveProperty(row.id, this.archiveReason.trim() || undefined),
      'Bien archivé — il ne paraît plus au catalogue.',
    );
  }

  /** Sort l'annonce de l'archive : elle repasse en file de validation. */
  protected restore(row: CatalogRow): void {
    this.write(
      row,
      this.admin.adminRestoreProperty(row.id),
      'Bien sorti d’archive : il repasse en file de validation.',
    );
  }

  /** Exécute une écriture puis recharge la page (statut et filtres peuvent bouger). */
  private write(
    row: CatalogRow,
    request$: ReturnType<AdminService['adminRestoreProperty']>,
    done: string,
  ): void {
    if (this.savingId() !== null) return;

    this.savingId.set(row.id);
    this.actionError.set(null);
    this.actionDone.set(null);

    request$.subscribe({
      next: () => {
        this.savingId.set(null);
        this.closePanel();
        this.actionDone.set(done);
        // Un changement de statut peut faire sortir la ligne du filtre courant :
        // on recharge plutôt que de rafistoler la ligne à l'écran.
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.savingId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      return 'Action réservée aux comptes disposant du droit « valider un bien ».';
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
      return first ?? body?.message ?? 'Action impossible dans cet état.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }

  /** Sélectionne un onglet et recharge (les filtres sont conservés). */
  protected select(type: CatalogType): void {
    if (type === this.selected()) return;
    this.selected.set(type);
    this.page.set(1);
    this.load();
  }

  /** Applique les filtres (recherche / statut) depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  /** Charge la page courante de l'onglet sélectionné. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: CatalogQuery = {
      status: this.status || undefined,
      q: this.search.trim() || undefined,
      page: this.page(),
    };

    const type = this.selected();
    const request$: Observable<Paginated<Property | Vehicle | Experience>> =
      type === 'property'
        ? this.admin.adminProperties(query)
        : type === 'vehicle'
          ? this.admin.adminVehicles(query)
          : this.admin.adminExperiences(query);

    request$.subscribe({
      next: (paginated) => {
        this.rows.set(paginated.data.map((item) => this.normalize(type, item)));
        this.total.set(paginated.meta.total);
        this.lastPage.set(paginated.meta.last_page);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Page précédente / suivante (bornée). */
  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /** Ramène une ressource (selon le type) à la ligne de tableau commune. */
  private normalize(type: CatalogType, item: Property | Vehicle | Experience): CatalogRow {
    if (type === 'property') {
      const p = item as Property;
      return {
        id: p.id,
        label: p.title,
        reference: null,
        status: p.status,
        statusLabel: this.statusLabel(p.status),
        ownerName: p.owner?.name ?? null,
        priceXof: p.price_xof,
        priceSuffix: '',
        date: p.published_at ?? p.created_at,
      };
    }
    if (type === 'vehicle') {
      const v = item as Vehicle;
      return {
        id: v.id,
        label: [v.brand, v.model].filter(Boolean).join(' ') || 'Véhicule',
        reference: v.reference,
        status: v.status,
        statusLabel: v.status_label ?? this.statusLabel(v.status),
        ownerName: null,
        priceXof: v.price_per_day_xof,
        priceSuffix: '/ jour',
        date: v.published_at,
      };
    }
    const e = item as Experience;
    return {
      id: e.id,
      label: e.title,
      reference: e.reference,
      status: e.status,
      statusLabel: e.status_label ?? this.statusLabel(e.status),
      ownerName: null,
      priceXof: e.price_xof,
      priceSuffix: '',
      date: e.published_at,
    };
  }

  /** Libellé lisible d'un statut (repli quand la Resource n'en fournit pas). */
  private statusLabel(status: string | null): string {
    switch (status) {
      case 'en_attente_validation':
        return 'En attente';
      case 'publie':
        return 'Publié';
      case 'suspendu':
        return 'Suspendu';
      case 'rejete':
        return 'Rejeté';
      case 'archive':
        return 'Archivé';
      case 'brouillon':
        return 'Brouillon';
      default:
        return status ?? '—';
    }
  }

  /** Classe CSS du badge de statut. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'publie':
        return 'is-ok';
      case 'en_attente_validation':
        return 'is-pending';
      case 'suspendu':
      case 'archive':
        return 'is-warn';
      case 'rejete':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Montant formaté en FCFA (ou tiret si absent). */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Date courte (ou tiret). */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }
}
