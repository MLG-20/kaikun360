import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AdminService } from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import {
  PartnerDue,
  PartnerPayout,
  PayoutBeneficiaryLine,
  PayoutTotals,
} from '../../../models/payout.model';

/** Onglet actif de l'écran. */
type PayoutsTab = 'a-payer' | 'versements';

/**
 * Écran **Reversements** du back-office (F8.16.a) — ce que Kaikun doit à ses
 * partenaires, et comment on le solde.
 *
 * **Le trou que cet écran comble.** Kaikun encaisse et prélève sa commission sur
 * tous les univers depuis F8.4, mais ne reversait qu'en gestion locative :
 * `owner_payouts.mandate_id` est non nullable, la table ne peut structurellement
 * pas porter le reversement d'un hôte, d'un loueur ou d'un organisateur.
 * Jusqu'ici, **si un partenaire demandait ce qu'on lui devait, personne ne
 * pouvait répondre.**
 *
 * **Deux onglets, parce qu'il y a deux questions distinctes** :
 *   - « **À payer** » répond à *qui dois-je payer, et combien* — une ligne par
 *     PARTENAIRE, pas par dette : on ne vire pas à une réservation, on vire à
 *     quelqu'un. Le total agrégé vient du serveur en une requête.
 *   - « **Versements** » est l'archive : ce qui est parti, quand, par quel canal
 *     et avec quel justificatif.
 *
 * ⚠️ **Deux montants séparés sur chaque ligne, et c'est le cœur de l'écran** :
 * ce qui est *payable aujourd'hui* et ce qui est *acquis mais encore sous
 * délai*. Les additionner ferait virer de l'argent trop tôt — avant que le délai
 * d'annulation du client soit écoulé, un reversement peut devoir être repris.
 *
 * ⚠️ **Aucun virement n'est déclenché ici.** L'agent paie par Wave, Orange Money
 * ou virement, puis vient le CONSTATER avec son justificatif. C'est le choix
 * assumé de la tranche : aucun argent ne bouge sans un geste humain.
 */
@Component({
  selector: 'app-backoffice-payouts-page',
  imports: [FormsModule],
  templateUrl: './backoffice-payouts-page.html',
  styleUrl: './backoffice-payouts-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficePayoutsPageComponent {
  private readonly admin = inject(AdminService);

  protected readonly tab = signal<PayoutsTab>('a-payer');

  protected readonly loading = signal(false);
  protected readonly failed = signal(false);
  /** Message d'erreur d'un geste (préparation, constat, échec). */
  protected readonly actionError = signal<string | null>(null);
  /** Confirmation brève après un geste réussi. */
  protected readonly flash = signal<string | null>(null);

  // --- Onglet « À payer » ---------------------------------------------------

  protected readonly lines = signal<PayoutBeneficiaryLine[]>([]);
  protected readonly totals = signal<PayoutTotals | null>(null);

  /** Partenaire déplié : ses dettes, pour choisir ce qui entre dans le lot. */
  protected readonly openedBeneficiary = signal<number | null>(null);
  protected readonly dues = signal<PartnerDue[]>([]);
  protected readonly duesLoading = signal(false);
  /** Dettes cochées (ids), remises à zéro à chaque changement de partenaire. */
  protected readonly selection = signal<ReadonlySet<number>>(new Set());

  /** Somme de ce qui a été coché — l'agent doit voir le montant qu'il engage. */
  protected readonly selectedTotal = computed(() =>
    this.dues()
      .filter((due) => this.selection().has(due.id))
      .reduce((sum, due) => sum + due.net_xof, 0),
  );

  protected readonly canPrepare = computed(() => this.selection().size > 0);

  // --- Onglet « Versements » ------------------------------------------------

  protected readonly payouts = signal<PartnerPayout[]>([]);
  /** Versement déplié pour être constaté ou déclaré en échec. */
  protected readonly openedPayout = signal<number | null>(null);

  /** Saisie du constat de virement. */
  protected method = 'wave';
  protected externalReference = '';
  protected proofFile: File | null = null;
  /** Motif d'échec. */
  protected failNote = '';

  protected readonly methods = [
    { value: 'wave', label: 'Wave' },
    { value: 'orange_money', label: 'Orange Money' },
    { value: 'virement', label: 'Virement bancaire' },
    { value: 'especes', label: 'Espèces' },
  ];

  constructor() {
    this.loadBeneficiaries();
  }

  protected switchTab(tab: PayoutsTab): void {
    this.tab.set(tab);
    this.actionError.set(null);
    this.flash.set(null);

    tab === 'a-payer' ? this.loadBeneficiaries() : this.loadPayouts();
  }

  // --- Chargements ----------------------------------------------------------

  protected loadBeneficiaries(): void {
    this.loading.set(true);
    this.failed.set(false);

    this.admin.payoutBeneficiaries().subscribe({
      next: (data) => {
        this.lines.set(data.beneficiaries);
        this.totals.set(data.totals);
        this.loading.set(false);
      },
      error: () => {
        this.failed.set(true);
        this.loading.set(false);
      },
    });
  }

  protected loadPayouts(): void {
    this.loading.set(true);
    this.failed.set(false);

    this.admin.partnerPayouts().subscribe({
      next: (page) => {
        this.payouts.set(page.data);
        this.loading.set(false);
      },
      error: () => {
        this.failed.set(true);
        this.loading.set(false);
      },
    });
  }

  /**
   * Déplie un partenaire et charge SES dettes.
   *
   * ⚠️ Chargées à l'ouverture et non toutes d'avance : un écran qui descend
   * toutes les dettes de tous les partenaires pour n'en afficher qu'une poignée
   * paierait le prix du registre entier à chaque visite.
   */
  protected toggleBeneficiary(id: number | null): void {
    if (id === null) {
      return;
    }

    if (this.openedBeneficiary() === id) {
      this.openedBeneficiary.set(null);
      return;
    }

    this.openedBeneficiary.set(id);
    // ⚠️ La sélection ne survit JAMAIS au changement de partenaire : un lot ne
    // concerne qu'un bénéficiaire (le serveur le refuse), et des cases restées
    // cochées d'un autre partenaire produiraient un 422 incompréhensible.
    this.selection.set(new Set());
    this.dues.set([]);
    this.duesLoading.set(true);
    this.actionError.set(null);

    this.admin.partnerDues({ beneficiary_id: id }).subscribe({
      next: (page) => {
        this.dues.set(page.data);
        this.duesLoading.set(false);
      },
      error: () => {
        this.actionError.set('Impossible de charger le détail des dettes.');
        this.duesLoading.set(false);
      },
    });
  }

  // --- Gestes ---------------------------------------------------------------

  protected isSelected(id: number): boolean {
    return this.selection().has(id);
  }

  protected toggleDue(due: PartnerDue): void {
    // ⚠️ `is_payable` est décidé par le SERVEUR (miroir du scope `payables()`) :
    // l'écran ne rejoue pas la règle « exigible ET sans lot ».
    if (!due.is_payable) {
      return;
    }

    const next = new Set(this.selection());
    next.has(due.id) ? next.delete(due.id) : next.add(due.id);
    this.selection.set(next);
  }

  /** Coche toutes les dettes payables du partenaire ouvert. */
  protected selectAllPayable(): void {
    this.selection.set(new Set(this.dues().filter((d) => d.is_payable).map((d) => d.id)));
  }

  protected prepare(): void {
    if (!this.canPrepare()) {
      return;
    }

    this.actionError.set(null);
    this.loading.set(true);

    this.admin.createPartnerPayout({ due_ids: [...this.selection()] }).subscribe({
      next: (payout) => {
        this.loading.set(false);
        this.selection.set(new Set());
        this.flash.set(
          `Versement ${payout.reference} préparé (${payout.amount_xof.toLocaleString('fr-FR')} F). ` +
            'Effectuez le virement, puis constatez-le dans l’onglet Versements.',
        );
        // Le lot retire ses dettes de l'encours : la vue d'entrée a changé.
        this.openedBeneficiary.set(null);
        this.loadBeneficiaries();
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        this.actionError.set(this.messageFrom(error, 'La préparation du versement a échoué.'));
      },
    });
  }

  protected togglePayout(id: number): void {
    this.openedPayout.set(this.openedPayout() === id ? null : id);
    this.actionError.set(null);
    this.method = 'wave';
    this.externalReference = '';
    this.proofFile = null;
    this.failNote = '';
  }

  protected onProofSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.proofFile = input.files?.[0] ?? null;
  }

  protected confirmPaid(payout: PartnerPayout): void {
    if (this.proofFile === null) {
      // ⚠️ Le justificatif est obligatoire côté serveur ; on le dit AVANT
      // l'appel plutôt que de laisser revenir un 422 sur un formulaire vidé.
      this.actionError.set('Joignez le justificatif du virement : c’est la seule preuve du paiement.');
      return;
    }

    this.actionError.set(null);
    this.loading.set(true);

    this.admin
      .payPartnerPayout(payout.id, {
        method: this.method,
        external_reference: this.externalReference || undefined,
        proof: this.proofFile,
      })
      .subscribe({
        next: () => {
          this.loading.set(false);
          this.openedPayout.set(null);
          this.flash.set(`Versement ${payout.reference} constaté.`);
          this.loadPayouts();
        },
        error: (error: HttpErrorResponse) => {
          this.loading.set(false);
          this.actionError.set(this.messageFrom(error, 'Le constat du virement a échoué.'));
        },
      });
  }

  protected declareFailed(payout: PartnerPayout): void {
    if (!this.failNote.trim()) {
      this.actionError.set('Indiquez le motif du rejet : il explique pourquoi les dettes reviennent à payer.');
      return;
    }

    this.actionError.set(null);
    this.loading.set(true);

    this.admin.failPartnerPayout(payout.id, this.failNote.trim()).subscribe({
      next: () => {
        this.loading.set(false);
        this.openedPayout.set(null);
        this.flash.set(
          `Versement ${payout.reference} déclaré en échec — les dettes redeviennent à payer.`,
        );
        this.loadPayouts();
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        this.actionError.set(this.messageFrom(error, 'La déclaration d’échec a échoué.'));
      },
    });
  }

  // --- Présentation ---------------------------------------------------------

  protected fcfa(value: number | null | undefined): string {
    if (value === null || value === undefined) {
      return '—';
    }
    return `${value.toLocaleString('fr-FR').replace(/ /g, ' ')} F`;
  }

  protected date(iso: string | null): string {
    if (!iso) {
      return '—';
    }
    const parsed = new Date(iso);
    return Number.isNaN(parsed.getTime())
      ? '—'
      : parsed.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  /** Libellé lisible de l'univers d'origine d'une dette. */
  protected sourceLabel(due: PartnerDue): string {
    return due.source.type === 'ProviderMission' ? 'Mission prestataire' : 'Réservation';
  }

  /**
   * Message d'erreur du serveur, sinon un repli lisible.
   *
   * Les 422 du registre sont explicites et utiles (« la dette DU-… n'est pas
   * exigible ou figure déjà dans un versement ») : les remplacer par un message
   * générique priverait l'agent de la seule information dont il a besoin.
   */
  private messageFrom(error: HttpErrorResponse, repli: string): string {
    const body = error.error as ValidationErrorBody | undefined;
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    return first ?? body?.message ?? repli;
  }
}
