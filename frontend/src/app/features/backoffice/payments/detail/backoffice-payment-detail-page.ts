import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminService, PaymentDossier } from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';

/** Panneau d'action ouvert sous l'en-tête. */
type PanelKind = 'confirm' | 'refund' | null;

/**
 * **Dossier d'un règlement** (F8.2.d) — l'écran le plus sensible du back-office.
 *
 * Un paiement, c'est de l'argent réel : le confirmer à tort crédite une
 * réservation jamais payée, le rembourser à tort fait sortir des fonds. Ces deux
 * gestes se prenaient depuis une ligne de tableau, sans voir ce qui les
 * justifie. Cette fiche existe pour qu'ils se prennent en connaissance de cause.
 *
 * Elle réunit :
 *   - **les preuves** — référence PSP, signature du webhook vérifiée, référence
 *     de la transaction Wave/OM saisie à la confirmation manuelle, montant déjà
 *     remboursé. Une signature non vérifiée est signalée en clair : c'est la
 *     différence entre « le PSP a confirmé » et « quelqu'un l'a affirmé » ;
 *   - **la réservation payée**, son client, son reste dû ;
 *   - **l'échéancier** : tous les règlements de la réservation. Un acompte isolé
 *     ne dit rien ; le même à côté d'un solde encaissé raconte autre chose ;
 *   - **le journal** : qui a confirmé, qui a remboursé, de combien.
 *
 * **Le remboursement ne se fait QU'ICI** (retiré de la liste, F8.2.d) : il est
 * irréversible et sort de l'argent. On ne rembourse pas depuis un tableau sans
 * avoir vu la réservation. La confirmation d'un règlement Wave/OM, elle, reste
 * possible depuis la liste — c'est un geste quotidien, à faible enjeu, qui ne
 * demande aucune information supplémentaire.
 *
 * Les deux boutons ne s'affichent que si le SERVEUR le permet (`can_confirm`,
 * `can_refund`) : l'écran ne redéclare pas les règles, il les suit.
 */
@Component({
  selector: 'app-backoffice-payment-detail-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './backoffice-payment-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficePaymentDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<PaymentDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected readonly processing = signal(false);
  protected readonly actionError = signal<string | null>(null);
  protected readonly actionDone = signal<string | null>(null);

  /** Panneau de saisie ouvert (preuve Wave/OM, ou montant à rembourser). */
  protected readonly panel = signal<PanelKind>(null);
  protected proofReference = '';
  protected refundAmount: number | null = null;

  /**
   * Encaissement **non prouvé** : paiement complété alors que la signature du
   * webhook PSP n'a pas été vérifiée, et sans preuve manuelle saisie.
   *
   * Ce n'est pas une erreur en soi — un règlement manuel confirmé sans référence
   * tombe dans ce cas —, mais c'est ce qu'un contrôle comptable cherchera :
   * autant le dire à l'écran plutôt que de le laisser découvrir six mois plus
   * tard.
   */
  protected readonly unproven = computed(() => {
    const p = this.dossier()?.payment;
    if (!p || p.status !== 'complete') return false;
    return !p.signature_verified && !p.manual_proof_reference;
  });

  /** Part de la réservation que ce seul règlement couvre. */
  protected readonly shareOfBooking = computed(() => {
    const d = this.dossier();
    const total = d?.booking?.amount_xof ?? 0;
    if (!d || !total) return null;
    return Math.round((d.payment.amount_xof / total) * 100);
  });

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.paymentDossier(this.id).subscribe({
      next: (dossier) => {
        this.dossier.set(dossier);
        // Le remboursement est proposé pour le montant TOTAL par défaut :
        // c'est le cas courant, et un montant partiel se saisit sciemment.
        this.refundAmount = dossier.payment.amount_xof;
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  // --- Gestes ------------------------------------------------------------------

  protected openPanel(kind: Exclude<PanelKind, null>): void {
    this.actionError.set(null);
    this.actionDone.set(null);
    this.panel.update((current) => (current === kind ? null : kind));
  }

  protected closePanel(): void {
    this.panel.set(null);
    this.proofReference = '';
  }

  /** Confirme un règlement manuel reçu sur le numéro officiel Wave/OM. */
  protected confirm(): void {
    if (this.processing()) return;

    this.processing.set(true);
    this.actionError.set(null);

    this.admin.confirmPayment(this.id, this.proofReference.trim() || undefined).subscribe({
      next: () => {
        this.processing.set(false);
        this.closePanel();
        this.actionDone.set('Règlement confirmé. La réservation liée a été confirmée.');
        // Rechargement complet : le statut, l'échéancier, le reste dû de la
        // réservation ET le journal ont tous bougé d'un coup.
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.processing.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Rembourse tout ou partie du règlement (geste irréversible). */
  protected refund(): void {
    if (this.processing()) return;

    const payment = this.dossier()?.payment;
    const amount = this.refundAmount;

    if (!payment || !amount || amount < 1) {
      this.actionError.set('Indiquez le montant à rembourser.');
      return;
    }
    if (amount > payment.amount_xof) {
      this.actionError.set('Le montant remboursé ne peut dépasser le montant payé.');
      return;
    }

    this.processing.set(true);
    this.actionError.set(null);

    this.admin.refundPayment(this.id, amount).subscribe({
      next: () => {
        this.processing.set(false);
        this.closePanel();
        this.actionDone.set('Remboursement enregistré et tracé au journal.');
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.processing.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  // --- Libellés ----------------------------------------------------------------

  /** Classe CSS du badge de statut d'un règlement. */
  protected statusClass(status: string): string {
    switch (status) {
      case 'complete':
        return 'is-ok';
      case 'rembourse':
        return 'is-warn';
      case 'refuse':
      case 'annule':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Mode d'encaissement, en clair. */
  protected modeLabel(mode: string | null): string {
    switch (mode) {
      case 'manuel':
        return 'Manuel (Wave / Orange Money)';
      case 'paytech':
        return 'En ligne (PayTech)';
      default:
        return mode ?? '—';
    }
  }

  /** Libellé lisible d'un statut de réservation. */
  protected bookingStatusLabel(status: string): string {
    switch (status) {
      case 'en_attente':
        return 'En attente';
      case 'confirmee':
        return 'Confirmée';
      case 'en_cours':
        return 'En cours';
      case 'terminee':
        return 'Terminée';
      case 'annulee_client':
      case 'annulee_prestataire':
      case 'annulee_admin':
        return 'Annulée';
      default:
        return status;
    }
  }

  /** Type de ressource réservée, en clair. */
  protected resourceTypeLabel(type: string): string {
    switch (type) {
      case 'Stay':
        return 'Nuitée';
      case 'Vehicle':
        return 'Véhicule';
      case 'TourismExperience':
        return 'Circuit touristique';
      case 'MobilityService':
        return 'Trajet programmé';
      default:
        return type;
    }
  }

  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

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
    if (error.status === 403) {
      return "Vous n'avez pas le droit de gérer les paiements. Cette délégation relève d'un administrateur.";
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? "Opération impossible dans l'état actuel du règlement.";
    }
    if (error.status === 502) {
      return "Le prestataire de paiement a refusé l'opération. Réessayez plus tard ; rien n'a été modifié.";
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
