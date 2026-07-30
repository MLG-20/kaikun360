import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import {
  AccountQuery,
  AdminDocument,
  AdminService,
  DocumentType,
  DocumentsOverview,
} from '../../../core/api/admin.service';
import { User } from '../../../models/user.model';

/** Onglet actif de l'écran Comptes & documents. */
type AccountsTab = 'accounts' | 'documents';

/** Option générique d'un menu déroulant (valeur + libellé). */
interface SelectOption {
  value: string;
  label: string;
}

/** Une carte de la vue d'ensemble documentaire (famille + libellé + compteur). */
interface DocCard {
  type: DocumentType;
  label: string;
  hint: string;
}

/**
 * Écran **Comptes & documents** du back-office (F7.2.f) — CDC §6 *Utilisateurs*
 * + *Documents*.
 *
 * Deux onglets :
 *   - **Comptes** (`GET /admin/users`) : annuaire de tous les comptes (clients,
 *     propriétaires, prestataires, entreprises, équipe), filtrable par rôle /
 *     statut / recherche. Un clic sur une ligne ouvre la **fiche détaillée**
 *     (`/back-office/comptes/:id`) où l'on voit toutes ses informations et où
 *     l'on pilote son statut / rôle / demande de pièces.
 *   - **Documents** (`GET /admin/documents`) : vue transverse des pièces
 *     éparpillées dans les modules (KYC, documents de biens, certifications
 *     prestataires, preuves de reversement). Compteurs par famille puis liste
 *     normalisée paginée, en lecture seule.
 */
@Component({
  selector: 'app-backoffice-accounts-page',
  imports: [FormsModule],
  templateUrl: './backoffice-accounts-page.html',
  styleUrl: './backoffice-accounts-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeAccountsPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  /** Onglet courant. */
  protected readonly tab = signal<AccountsTab>('accounts');

  // --- Onglet Comptes ---------------------------------------------------------
  protected readonly rows = signal<User[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected role = '';
  protected status = '';
  protected search = '';

  // --- Onglet Documents -------------------------------------------------------
  protected readonly overview = signal<DocumentsOverview | null>(null);
  protected readonly overviewLoaded = signal(false);
  protected readonly docType$ = signal<DocumentType | null>(null);
  protected readonly docRows = signal<AdminDocument[]>([]);
  protected readonly docPage = signal(1);
  protected readonly docLastPage = signal(1);
  protected readonly docTotal = signal(0);
  protected readonly docLoading = signal(false);
  protected readonly docError = signal(false);

  protected readonly roleOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les rôles' },
    { value: 'client', label: 'Client' },
    { value: 'proprietaire', label: 'Propriétaire' },
    { value: 'prestataire', label: 'Prestataire' },
    { value: 'entreprise', label: 'Entreprise' },
    { value: 'agent_kaikun', label: 'Agent Kaikun' },
    { value: 'admin', label: 'Administrateur' },
    { value: 'super_admin', label: 'Super administrateur' },
  ];

  protected readonly statusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'actif', label: 'Actif' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'desactive', label: 'Désactivé' },
    { value: 'en_attente_verification', label: 'En attente de vérification' },
  ];

  /** Cartes de la vue d'ensemble documentaire. */
  protected readonly docCards: readonly DocCard[] = [
    { type: 'kyc', label: "Pièces d'identité (KYC)", hint: 'Vérification des comptes' },
    { type: 'property', label: 'Documents de biens', hint: 'Titres, plans, diagnostics' },
    { type: 'certification', label: 'Certifications', hint: 'Prestataires vérifiés' },
    { type: 'payout_proof', label: 'Preuves de reversement', hint: 'Propriétaires payés' },
    // F7.4.c — Les deux familles ajoutées pour couvrir toute la ligne CDC.
    // « Sans fichier joint » est dit explicitement plutôt que laissé deviner :
    // un mandat porte ses clauses en texte, pas en PDF signé.
    { type: 'mandate', label: 'Mandats & contrats', hint: 'Clauses en fiche, sans fichier joint' },
    { type: 'report', label: 'Rapports de suivi', hint: 'Chantiers & dossiers diaspora' },
  ];

  constructor() {
    this.load();
  }

  /** Bascule d'onglet (charge la vue documentaire à sa première ouverture). */
  protected switchTab(tab: AccountsTab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);
    if (tab === 'documents' && !this.overviewLoaded()) this.loadOverview();
  }

  // --- Comptes ----------------------------------------------------------------

  /** Applique les filtres depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: AccountQuery = {
      role: this.role || undefined,
      status: this.status || undefined,
      q: this.search.trim() || undefined,
      page: this.page(),
    };

    this.admin.users(query).subscribe({
      next: (paginated) => {
        this.rows.set(paginated.data);
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

  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /** Ouvre la fiche détaillée d'un compte. */
  protected openAccount(user: User): void {
    void this.router.navigate(['/back-office', 'comptes', user.id]);
  }

  // --- Documents --------------------------------------------------------------

  protected loadOverview(): void {
    this.overviewLoaded.set(true);
    this.admin.documentsOverview().subscribe({
      next: (overview) => this.overview.set(overview),
      error: () => this.overview.set(null),
    });
  }

  /** Ouvre la liste normalisée d'une famille de pièces. */
  protected openDocType(type: DocumentType): void {
    this.docType$.set(type);
    this.docPage.set(1);
    this.loadDocuments();
  }

  protected loadDocuments(): void {
    const type = this.docType$();
    if (!type) return;
    this.docLoading.set(true);
    this.docError.set(false);

    this.admin.documents(type, this.docPage()).subscribe({
      next: (paginated) => {
        this.docRows.set(paginated.data);
        this.docTotal.set(paginated.meta.total);
        this.docLastPage.set(paginated.meta.last_page);
        this.docLoading.set(false);
      },
      error: () => {
        this.docError.set(true);
        this.docLoading.set(false);
      },
    });
  }

  protected goToDoc(page: number): void {
    if (page < 1 || page > this.docLastPage() || page === this.docPage()) return;
    this.docPage.set(page);
    this.loadDocuments();
  }

  /** Compteur d'une famille dans la vue d'ensemble (0 si non chargée). */
  protected countOf(type: DocumentType): number {
    return this.overview()?.[type] ?? 0;
  }

  /** Libellé d'une famille de pièces (pour l'en-tête de la liste). */
  protected docCardLabel(type: DocumentType | null): string {
    return this.docCards.find((card) => card.type === type)?.label ?? '';
  }

  // --- Présentation -----------------------------------------------------------

  /** Libellé lisible du rôle principal d'un compte. */
  protected roleLabel(user: User): string {
    const primary = user.roles[0];
    const match = this.roleOptions.find((option) => option.value === primary);
    return match?.label ?? primary ?? '—';
  }

  /** Classe CSS du badge de statut d'un compte. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'suspendu':
        return 'is-warn';
      case 'desactive':
        return 'is-off';
      case 'en_attente_verification':
        return 'is-pending';
      default:
        return 'is-pending';
    }
  }

  /** Vérification e-mail/téléphone en une puce lisible. */
  protected verifiedLabel(user: User): string {
    const parts: string[] = [];
    if (user.email_verified_at) parts.push('e-mail');
    if (user.phone_verified_at) parts.push('téléphone');
    return parts.length ? parts.join(' · ') : 'non vérifié';
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
