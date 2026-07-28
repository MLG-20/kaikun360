import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import {
  AdminService,
  ConstructionDossier,
  ConstructionQuery,
  MandateDossier,
  MandateQuery,
} from '../../../core/api/admin.service';

/** Onglet actif de l'écran Dossiers. */
type DossierTab = 'construction' | 'mandate';

/** Option de filtre par statut. */
interface StatusOption {
  value: string;
  label: string;
}

/**
 * Écran **Dossiers** du back-office (F7.2.e) — supervision des suivis longs.
 *
 * Deux familles de dossiers transverses, en **lecture seule** (l'exploitation
 * fine reste dans les espaces métier des modules Build/Manage) :
 *   - **Construction** (`GET /admin/construction-requests`) : demandes de
 *     construction/rénovation de tous les clients, avec objectif, budget, coût
 *     estimé, avancement (rapports / jalons) et statut du cycle ;
 *   - **Gestion locative** (`GET /admin/mandates`) : mandats de gestion de tous
 *     les propriétaires, avec le bien géré, la commission, la période et les
 *     agrégats financiers (loyers payés/impayés, dépenses, reversements,
 *     incidents ouverts).
 *
 * Chaque onglet est chargé à la demande (première ouverture), avec filtre par
 * statut, filtre ville (construction) et pagination. Les deux endpoints exigent
 * seulement `consulter:dashboard-admin` → pas de garde-fou d'action ici.
 */
@Component({
  selector: 'app-backoffice-dossiers-page',
  imports: [FormsModule],
  templateUrl: './backoffice-dossiers-page.html',
  styleUrl: './backoffice-dossiers-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeDossiersPageComponent {
  private readonly admin = inject(AdminService);

  /** Onglet courant. */
  protected readonly tab = signal<DossierTab>('construction');

  // --- État Construction ------------------------------------------------------
  protected readonly buildRows = signal<ConstructionDossier[]>([]);
  protected readonly buildTotal = signal(0);
  protected readonly buildPage = signal(1);
  protected readonly buildLastPage = signal(1);
  /** L'onglet a-t-il déjà été chargé au moins une fois ? */
  protected readonly buildLoaded = signal(false);
  protected buildStatus = '';
  protected buildCity = '';

  // --- État Mandats -----------------------------------------------------------
  protected readonly mandateRows = signal<MandateDossier[]>([]);
  protected readonly mandateTotal = signal(0);
  protected readonly mandatePage = signal(1);
  protected readonly mandateLastPage = signal(1);
  protected readonly mandateLoaded = signal(false);
  protected mandateStatus = '';

  /** Communs aux deux onglets. */
  protected readonly loading = signal(false);
  protected readonly error = signal(false);

  protected readonly buildStatusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'soumise', label: 'Soumise' },
    { value: 'en_etude', label: 'En étude' },
    { value: 'devis_envoye', label: 'Devis envoyé' },
    { value: 'acceptee', label: 'Acceptée' },
    { value: 'en_chantier', label: 'En chantier' },
    { value: 'terminee', label: 'Terminée' },
    { value: 'annulee', label: 'Annulée' },
  ];

  protected readonly mandateStatusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'actif', label: 'Actif' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'termine', label: 'Terminé' },
  ];

  constructor() {
    this.loadBuild();
  }

  /** Bascule d'onglet (charge l'onglet cible à sa première ouverture). */
  protected switchTab(tab: DossierTab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);
    if (tab === 'construction' && !this.buildLoaded()) this.loadBuild();
    if (tab === 'mandate' && !this.mandateLoaded()) this.loadMandates();
  }

  // --- Construction -----------------------------------------------------------

  /** Applique les filtres construction depuis la première page. */
  protected applyBuildFilters(): void {
    this.buildPage.set(1);
    this.loadBuild();
  }

  protected loadBuild(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: ConstructionQuery = {
      status: this.buildStatus || undefined,
      city: this.buildCity.trim() || undefined,
      page: this.buildPage(),
    };

    this.admin.adminConstructionRequests(query).subscribe({
      next: (paginated) => {
        this.buildRows.set(paginated.data);
        this.buildTotal.set(paginated.meta.total);
        this.buildLastPage.set(paginated.meta.last_page);
        this.buildLoaded.set(true);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  protected goToBuild(page: number): void {
    if (page < 1 || page > this.buildLastPage() || page === this.buildPage()) return;
    this.buildPage.set(page);
    this.loadBuild();
  }

  // --- Mandats ----------------------------------------------------------------

  protected applyMandateFilters(): void {
    this.mandatePage.set(1);
    this.loadMandates();
  }

  protected loadMandates(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: MandateQuery = {
      status: this.mandateStatus || undefined,
      page: this.mandatePage(),
    };

    this.admin.adminMandates(query).subscribe({
      next: (paginated) => {
        this.mandateRows.set(paginated.data);
        this.mandateTotal.set(paginated.meta.total);
        this.mandateLastPage.set(paginated.meta.last_page);
        this.mandateLoaded.set(true);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  protected goToMandate(page: number): void {
    if (page < 1 || page > this.mandateLastPage() || page === this.mandatePage()) return;
    this.mandatePage.set(page);
    this.loadMandates();
  }

  // --- Présentation -----------------------------------------------------------

  /** Classe CSS du badge de statut construction. */
  protected buildStatusClass(status: string | null): string {
    switch (status) {
      case 'terminee':
        return 'is-ok';
      case 'en_chantier':
      case 'acceptee':
        return 'is-active';
      case 'soumise':
      case 'en_etude':
      case 'devis_envoye':
        return 'is-pending';
      case 'annulee':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Classe CSS du badge de statut mandat. */
  protected mandateStatusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'en_attente':
        return 'is-pending';
      case 'suspendu':
        return 'is-warn';
      case 'termine':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Localisation courte du bien d'un mandat (commune / département / région). */
  protected placeOf(m: MandateDossier): string {
    const loc = m.property.location;
    if (!loc) return '—';
    const parts = [loc.commune, loc.department, loc.region].filter((p): p is string => !!p);
    return parts.length ? parts.join(' · ') : '—';
  }

  /** Taux de commission formaté en pourcentage. */
  protected rate(value: number | string | null): string {
    if (value === null || value === undefined) return '—';
    const n = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(n)) return '—';
    return `${n} %`;
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Surface formatée en m². */
  protected surface(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return `${new Intl.NumberFormat('fr-FR').format(value)} m²`;
  }

  /** Date courte (jour/mois/année). */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }
}
