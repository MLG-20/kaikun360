import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AdminService, PaymentQuery } from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Payment } from '../../../models/payment.model';

/** Option de filtre par statut de paiement. */
interface StatusOption {
  value: string;
  label: string;
}

/**
 * Écran **Paiements** du back-office (F7.2.d) — supervision financière.
 *
 * Liste tous les paiements (`GET /admin/payments`) avec filtres statut +
 * référence. Deux actions sensibles (permission `gerer:paiements`, limitées par
 * un throttle côté serveur) :
 *   - **Confirmer** un règlement **manuel** Wave/OM (Phase 1 du CDC) : le client
 *     a payé au numéro officiel, l'admin valide → la réservation est confirmée ;
 *   - **Rembourser** tout ou partie d'un paiement encaissé (`complete`).
 *
 * L'écran reflète les garde-fous serveur (mode manuel requis pour confirmer,
 * statut `complete` requis pour rembourser) et rend les refus lisibles.
 */
@Component({
  selector: 'app-backoffice-payments-page',
  imports: [FormsModule],
  templateUrl: './backoffice-payments-page.html',
  styleUrl: './backoffice-payments-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficePaymentsPageComponent {
  private readonly admin = inject(AdminService);

  /** Paiements de la page courante. */
  protected readonly payments = signal<Payment[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Filtres. */
  protected status = '';
  protected reference = '';

  /** Ligne en cours d'action (verrouille ses boutons). */
  protected readonly processingId = signal<number | null>(null);
  /** Ligne dont le panneau (confirmation ou remboursement) est ouvert. */
  protected readonly openPanelId = signal<number | null>(null);
  /** Nature du panneau ouvert. */
  protected readonly panelKind = signal<'confirm' | 'refund' | null>(null);
  /** Saisie du panneau : preuve Wave/OM (confirm) ou montant (refund). */
  protected proofReference = '';
  protected refundAmount: number | null = null;

  protected readonly actionError = signal<string | null>(null);
  protected readonly actionDone = signal<string | null>(null);

  protected readonly statusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'initie', label: 'Initié' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'autorise', label: 'Autorisé' },
    { value: 'complete', label: 'Encaissé' },
    { value: 'refuse', label: 'Refusé' },
    { value: 'annule', label: 'Annulé' },
    { value: 'rembourse', label: 'Remboursé' },
  ];

  constructor() {
    this.load();
  }

  /** Applique les filtres depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  /** Charge la page courante. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(false);
    this.closePanel();

    const query: PaymentQuery = {
      status: this.status || undefined,
      reference: this.reference.trim() || undefined,
      page: this.page(),
    };

    this.admin.payments(query).subscribe({
      next: (paginated) => {
        this.payments.set(paginated.data);
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

  /** Un paiement manuel non encore encaissé peut être confirmé à la main. */
  protected canConfirm(p: Payment): boolean {
    return p.mode === 'manuel' && p.status !== 'complete';
  }

  /** Seul un paiement encaissé est remboursable. */
  protected canRefund(p: Payment): boolean {
    return p.status === 'complete';
  }

  /** Ouvre le panneau de confirmation manuelle d'une ligne. */
  protected openConfirm(p: Payment): void {
    this.resetPanelInputs();
    this.panelKind.set('confirm');
    this.openPanelId.set(p.id);
  }

  /** Ouvre le panneau de remboursement d'une ligne (montant prérempli = total). */
  protected openRefund(p: Payment): void {
    this.resetPanelInputs();
    this.refundAmount = p.amount_xof;
    this.panelKind.set('refund');
    this.openPanelId.set(p.id);
  }

  /** Ferme le panneau ouvert. */
  protected closePanel(): void {
    this.openPanelId.set(null);
    this.panelKind.set(null);
  }

  private resetPanelInputs(): void {
    this.proofReference = '';
    this.refundAmount = null;
    this.actionError.set(null);
    this.actionDone.set(null);
  }

  /** Confirme un règlement manuel Wave/OM. */
  protected confirm(p: Payment): void {
    if (this.processingId() !== null) return;
    this.processingId.set(p.id);
    this.actionError.set(null);
    this.actionDone.set(null);

    this.admin.confirmPayment(p.id, this.proofReference.trim() || undefined).subscribe({
      next: (updated) => {
        this.processingId.set(null);
        this.replace(updated);
        this.closePanel();
        this.actionDone.set(`Paiement ${updated.reference} confirmé. La réservation est validée.`);
      },
      error: (err: HttpErrorResponse) => {
        this.processingId.set(null);
        this.actionError.set(this.messageFor(err));
      },
    });
  }

  /** Rembourse tout ou partie d'un paiement encaissé. */
  protected refund(p: Payment): void {
    if (this.processingId() !== null) return;

    const amount = this.refundAmount ?? undefined;
    if (amount !== undefined && (amount < 1 || amount > p.amount_xof)) {
      this.actionError.set('Le montant doit être compris entre 1 et le montant payé.');
      return;
    }

    this.processingId.set(p.id);
    this.actionError.set(null);
    this.actionDone.set(null);

    this.admin.refundPayment(p.id, amount).subscribe({
      next: (updated) => {
        this.processingId.set(null);
        this.replace(updated);
        this.closePanel();
        this.actionDone.set(`Remboursement de ${updated.reference} effectué.`);
      },
      error: (err: HttpErrorResponse) => {
        this.processingId.set(null);
        this.actionError.set(this.messageFor(err));
      },
    });
  }

  /** Remplace un paiement dans la liste après action. */
  private replace(updated: Payment): void {
    this.payments.update((list) => list.map((p) => (p.id === updated.id ? updated : p)));
  }

  /** Classe CSS du badge de statut. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'complete':
        return 'is-ok';
      case 'autorise':
      case 'en_attente':
      case 'initie':
        return 'is-pending';
      case 'rembourse':
        return 'is-warn';
      case 'refuse':
      case 'annule':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Libellé du mode de paiement. */
  protected modeLabel(mode: string | null): string {
    switch (mode) {
      case 'manuel':
        return 'Manuel (Wave/OM)';
      case 'paytech':
        return 'PayTech';
      default:
        return mode ?? '—';
    }
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Date courte. */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      return "Vous n'avez pas le droit de gérer les paiements.";
    }
    if (error.status === 429) {
      return 'Trop de tentatives. Patientez un instant avant de réessayer.';
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Action impossible dans cet état.';
    }
    if (error.status === 502) {
      return 'Le remboursement a échoué côté prestataire de paiement.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
