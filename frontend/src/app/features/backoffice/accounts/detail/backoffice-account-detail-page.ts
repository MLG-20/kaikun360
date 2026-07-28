import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import {
  AccountActivity,
  AccountDocument,
  AdminService,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';
import { User } from '../../../../models/user.model';

/** Option générique d'un menu déroulant (valeur + libellé). */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * **Fiche détaillée d'un compte** (F7.2.f) — `/back-office/comptes/:id`.
 *
 * Charge toutes les informations d'un utilisateur (`GET /admin/users/{id}`) :
 * identité, contact, localisation structurée, profil, dates de vérification,
 * ainsi que ses pièces justificatives (KYC). Le back-office y pilote le compte :
 *   - changer le **statut** (activer / réactiver / suspendre / désactiver) ;
 *   - changer le **rôle** (escalade admin réservée au super_admin) ;
 *   - **demander une pièce** (notification + relais n8n/WhatsApp).
 *
 * Les garde-fous de hiérarchie sont serveur (pas d'auto-modification, super_admin
 * protégé, escalade réservée) → l'écran reflète les refus (403 / 422).
 */
@Component({
  selector: 'app-backoffice-account-detail-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './backoffice-account-detail-page.html',
  styleUrl: './backoffice-account-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeAccountDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly user = signal<User | null>(null);
  protected readonly documents = signal<AccountDocument[]>([]);
  protected readonly activity = signal<AccountActivity[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Erreur d'une action (statut / rôle). */
  protected readonly actionError = signal<string | null>(null);
  /** Retours de la demande de pièce. */
  protected readonly requestSuccess = signal<string | null>(null);
  protected readonly requestError = signal<string | null>(null);
  protected readonly requesting = signal(false);

  /** Saisie du formulaire « demander une pièce ». */
  protected docType = 'cni';
  protected docNote = '';

  /** Seul un super_admin peut attribuer un rôle d'administration. */
  protected readonly isSuperAdmin = computed(() => this.auth.hasRole('super_admin'));
  protected readonly currentUserId = computed(() => this.auth.user()?.id ?? null);

  /** Le compte est-il pilotable ? (pas soi-même ; super_admin réservé au super_admin). */
  protected readonly canManage = computed(() => {
    const target = this.user();
    if (!target) return false;
    if (target.id === this.currentUserId()) return false;
    if (target.roles.includes('super_admin') && !this.isSuperAdmin()) return false;
    return true;
  });

  protected readonly assignableRoles: readonly SelectOption[] = [
    { value: 'client', label: 'Client' },
    { value: 'proprietaire', label: 'Propriétaire' },
    { value: 'prestataire', label: 'Prestataire' },
    { value: 'entreprise', label: 'Entreprise' },
    { value: 'agent_kaikun', label: 'Agent Kaikun' },
    { value: 'admin', label: 'Administrateur' },
    { value: 'super_admin', label: 'Super administrateur' },
  ];

  /** Types de pièces demandables (envoyés tels quels en `document_type`). */
  protected readonly documentTypes: readonly SelectOption[] = [
    { value: 'cni', label: "Carte d'identité (CNI)" },
    { value: 'passeport', label: 'Passeport' },
    { value: 'justificatif_domicile', label: 'Justificatif de domicile' },
    { value: 'registre_commerce', label: 'Registre du commerce (RCCM)' },
    { value: 'permis_conduire', label: 'Permis de conduire' },
    { value: 'autre', label: 'Autre pièce' },
  ];

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (Number.isNaN(id)) {
      this.error.set(true);
      this.loading.set(false);
    } else {
      this.load(id);
    }
  }

  protected load(id: number): void {
    this.loading.set(true);
    this.error.set(false);
    this.admin.userDetail(id).subscribe({
      next: (detail) => {
        this.user.set(detail.user);
        this.documents.set(detail.documents);
        this.activity.set(detail.activity);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Retour à l'annuaire. */
  protected back(): void {
    void this.router.navigate(['/back-office', 'comptes']);
  }

  /** Change le statut du compte (puis recharge la fiche pour rafraîchir l'historique). */
  protected setStatus(status: string): void {
    const target = this.user();
    if (!target) return;
    this.actionError.set(null);
    this.admin.updateUser(target.id, { status }).subscribe({
      next: (updated) => {
        this.user.set(updated);
        this.load(updated.id);
      },
      error: (error: HttpErrorResponse) => this.actionError.set(this.messageFor(error)),
    });
  }

  /** Change le rôle du compte (puis recharge la fiche pour rafraîchir l'historique). */
  protected setRole(role: string): void {
    const target = this.user();
    if (!target || !role || target.roles.includes(role)) return;
    this.actionError.set(null);
    this.admin.updateUser(target.id, { role }).subscribe({
      next: (updated) => {
        this.user.set(updated);
        this.load(updated.id);
      },
      error: (error: HttpErrorResponse) => this.actionError.set(this.messageFor(error)),
    });
  }

  /** Envoie la demande de pièce. */
  protected sendDocumentRequest(): void {
    const target = this.user();
    if (!target || this.requesting()) return;
    this.requesting.set(true);
    this.requestError.set(null);
    this.requestSuccess.set(null);
    this.admin.requestDocument(target.id, this.docType, this.docNote.trim() || undefined).subscribe({
      next: (message) => {
        this.requesting.set(false);
        this.requestSuccess.set(message || `Demande envoyée à ${target.name}.`);
        this.docNote = '';
      },
      error: (error: HttpErrorResponse) => {
        this.requesting.set(false);
        this.requestError.set(this.messageFor(error));
      },
    });
  }

  // --- Présentation -----------------------------------------------------------

  /** Libellé lisible du rôle principal. */
  protected roleLabel(user: User): string {
    const primary = user.roles[0];
    return this.assignableRoles.find((option) => option.value === primary)?.label ?? primary ?? '—';
  }

  /** Localisation structurée en une ligne (commune · département · région). */
  protected placeLabel(user: User): string {
    const parts = [user.commune, user.department, user.region].filter((p): p is string => !!p);
    return parts.length ? parts.join(' · ') : user.city || '—';
  }

  /** Classe CSS du badge de statut. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'suspendu':
        return 'is-warn';
      case 'desactive':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Initiale de l'avatar. */
  protected initial(name: string | null): string {
    return (name || '?').charAt(0).toUpperCase();
  }

  /** Date longue (jour mois année). */
  protected longDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    });
  }

  /** Date + heure (pour l'horodatage de l'historique). */
  protected dateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Données invalides.';
    }
    if (error.status === 403) {
      const body = error.error as { message?: string } | null;
      return body?.message ?? 'Action non autorisée pour votre rôle.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
