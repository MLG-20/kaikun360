import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import {
  AccountingQuery,
  AccountingReport,
  AdminService,
  PaymentQuery,
} from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Payment } from '../../../models/payment.model';

/** Option de filtre par statut de paiement. */
interface StatusOption {
  value: string;
  label: string;
}

/** Onglet actif de l'écran. */
type PaymentsTab = 'supervision' | 'export';

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
 * **F7.3.h — acomptes & soldes.** La liste porte deux colonnes de plus : la
 * **nature** du règlement (acompte / solde / intégral, déduite du montant côté
 * serveur) et le **reste dû** sur la réservation. Sans elles, un versement de
 * 50 000 F sur une réservation de 180 000 F était indistinguable d'une erreur.
 *
 * L'écran reflète les garde-fous serveur (mode manuel requis pour confirmer,
 * statut `complete` requis pour rembourser) et rend les refus lisibles.
 *
 * **Onglet « Export comptable » (F7.3.d)** — CDC §6 module 11 : le endpoint
 * `GET /admin/reports/export` existait depuis B13.5 sans aucune interface. Il est
 * ici branché en deux temps : le rapport JSON est d'abord affiché à l'écran
 * (totaux + grand livre + reversements) pour que l'admin CONTRÔLE la période
 * avant de télécharger, puis le CSV est proposé sur la même période.
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

  // --- Onglet « Export comptable » (F7.3.d) -----------------------------------

  /** Onglet affiché ; la supervision reste l'entrée par défaut. */
  protected readonly tab = signal<PaymentsTab>('supervision');

  /** Bornes de période saisies (`YYYY-MM-DD`, vides = pas de borne). */
  protected exportFrom = '';
  protected exportTo = '';

  /** Rapport affiché, ou `null` tant qu'aucune période n'a été calculée. */
  protected readonly report = signal<AccountingReport | null>(null);
  protected readonly reportLoading = signal(false);
  protected readonly reportError = signal<string | null>(null);
  /** Téléchargement CSV en cours (verrouille le bouton). */
  protected readonly downloading = signal(false);

  constructor() {
    this.load();
  }

  /**
   * Bascule d'onglet. Le rapport est calculé au PREMIER affichage de l'onglet
   * export (sur le mois courant), puis conservé : rebasculer ne relance pas une
   * requête d'agrégation potentiellement lourde.
   */
  protected switchTab(tab: PaymentsTab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);

    if (tab === 'export' && this.report() === null && !this.reportLoading()) {
      this.thisMonth();
    }
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

  // --- Export comptable : périodes & chargement --------------------------------

  /** Raccourci « mois en cours » (du 1er à aujourd'hui). */
  protected thisMonth(): void {
    const now = new Date();
    this.exportFrom = this.isoDate(new Date(now.getFullYear(), now.getMonth(), 1));
    this.exportTo = this.isoDate(now);
    this.loadReport();
  }

  /** Raccourci « mois dernier » (période complète). */
  protected lastMonth(): void {
    const now = new Date();
    this.exportFrom = this.isoDate(new Date(now.getFullYear(), now.getMonth() - 1, 1));
    // Le jour 0 du mois courant = dernier jour du mois précédent.
    this.exportTo = this.isoDate(new Date(now.getFullYear(), now.getMonth(), 0));
    this.loadReport();
  }

  /** Raccourci « année en cours ». */
  protected thisYear(): void {
    const now = new Date();
    this.exportFrom = this.isoDate(new Date(now.getFullYear(), 0, 1));
    this.exportTo = this.isoDate(now);
    this.loadReport();
  }

  /** Raccourci « tout l'historique » : aucune borne envoyée au serveur. */
  protected allTime(): void {
    this.exportFrom = '';
    this.exportTo = '';
    this.loadReport();
  }

  /** Calcule le rapport de la période saisie. */
  protected loadReport(): void {
    // Garde-fou local : le serveur exige `to >= from` (422 sinon), autant le dire
    // tout de suite plutôt que de laisser partir une requête vouée à l'échec.
    if (this.exportFrom && this.exportTo && this.exportTo < this.exportFrom) {
      this.reportError.set('La date de fin doit être postérieure à la date de début.');
      return;
    }

    this.reportLoading.set(true);
    this.reportError.set(null);

    this.admin.accountingReport(this.periodQuery()).subscribe({
      next: (report) => {
        this.report.set(report);
        this.reportLoading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.reportLoading.set(false);
        this.reportError.set(this.messageFor(err));
      },
    });
  }

  /**
   * Télécharge le grand livre des réservations en CSV sur la MÊME période que le
   * rapport affiché. Le serveur répond en `streamDownload` : on passe par un blob
   * puis un lien synthétique, comme l'export de la pointeuse.
   */
  protected downloadCsv(): void {
    if (this.downloading()) return;
    this.downloading.set(true);
    this.reportError.set(null);

    this.admin.accountingCsv(this.periodQuery()).subscribe({
      next: (blob) => {
        this.downloading.set(false);
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `export-comptable-${this.exportFrom || 'debut'}_${this.exportTo || 'aujourdhui'}.csv`;
        link.click();
        URL.revokeObjectURL(url);
      },
      error: (err: HttpErrorResponse) => {
        this.downloading.set(false);
        this.reportError.set(this.messageFor(err));
      },
    });
  }

  /** Bornes courantes, les champs vides étant omis (= pas de borne). */
  private periodQuery(): AccountingQuery {
    return {
      from: this.exportFrom || undefined,
      to: this.exportTo || undefined,
    };
  }

  /** Date locale → `YYYY-MM-DD` (sans passer par l'UTC, qui décalerait le jour). */
  private isoDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
  }

  /** Période du rapport en clair, bornes ouvertes comprises. */
  protected periodLabel(): string {
    const period = this.report()?.period;
    if (!period) return '—';
    if (!period.from && !period.to) return 'Tout l’historique';
    if (period.from && !period.to) return `Depuis le ${this.shortDate(period.from)}`;
    if (!period.from && period.to) return `Jusqu’au ${this.shortDate(period.to)}`;
    return `Du ${this.shortDate(period.from)} au ${this.shortDate(period.to)}`;
  }

  /**
   * Libellé du type réservé. Le serveur renvoie le nom court du modèle
   * (`class_basename`) : on le traduit pour l'écran.
   */
  protected typeLabel(type: string): string {
    switch (type) {
      case 'Stay':
        return 'Nuitée';
      case 'Vehicle':
        return 'Véhicule';
      case 'Experience':
        return 'Expérience';
      case 'Trip':
        return 'Trajet';
      case 'Property':
        return 'Bien';
      default:
        return type;
    }
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

  /**
   * Classe du badge de nature (F7.3.h). Un acompte laisse un reliquat à
   * percevoir : il se repère au premier coup d'œil.
   */
  protected kindClass(kind: string | null): string {
    return kind === 'acompte' ? 'is-warn' : 'is-ok';
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
