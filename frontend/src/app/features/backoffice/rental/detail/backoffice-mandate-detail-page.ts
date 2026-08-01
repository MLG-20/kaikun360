import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import {
  AdminService,
  MandateDossier,
  MandateIncident,
  MandatePayout,
  MandateRent,
  MandateReport,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { FicheFlag, FicheSignalsComponent } from '../../shared/fiche-signals/fiche-signals';

/** Formulaire actuellement déplié dans la fiche (un seul à la fois). */
type OpenForm = 'rent' | 'incident' | 'expense' | 'payout' | null;

/**
 * Fiche **mandat de gestion locative** pilotable du back-office (F7.3.a) —
 * CDC §6 *Gestion locative* : « contrats, loyers, incidents, dépenses,
 * reversements, rapport mensuel ». Les six y sont.
 *
 * Jusqu'ici l'écran Dossiers (F7.2.e) ne faisait que **superviser** les mandats
 * en lecture seule, alors que tout le backend de pilotage existait depuis B4.6
 * sous la permission `gerer:gestion-locative` — un agent ne pouvait donc pas
 * encaisser un loyer ni clore un incident depuis l'interface. Cette fiche
 * branche les huit actions du module Manage :
 *
 * - loyers : ajouter une échéance, la marquer encaissée ;
 * - incidents : signaler, **résoudre** (ce qui comble aussi le dernier trou du
 *   module CDC *Avis et qualité*, dont les incidents relèvent) ;
 * - dépenses : enregistrer, éventuellement rattachées à un incident ;
 * - reversements : préparer, marquer effectué.
 *
 * ⚠️ Les routes visées sont sous `/manage`, **pas** `/admin` : elles vivent dans
 * le module métier. La lecture de la fiche passe par la policy `view` (le
 * propriétaire OU un agent/admin) ; toute écriture exige
 * `gerer:gestion-locative`, d'où le 403 explicitement géré.
 *
 * ⚠️ Le serveur ne renvoie que les **12 dernières** lignes de chaque catégorie
 * (bornage anti-charge d'un mandat ancien) : l'écran le dit, pour qu'on ne
 * prenne pas cette liste pour l'historique complet.
 */
@Component({
  selector: 'app-backoffice-mandate-detail-page',
  imports: [FormsModule, RouterLink, FicheSignalsComponent],
  templateUrl: './backoffice-mandate-detail-page.html',
  // Feuille des volets repliables COMMUNE aux fiches hiérarchisées en F8.3.
  styleUrls: ['./backoffice-mandate-detail-page.scss', '../../shared/fiche-blocks.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeMandateDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  /** Identifiant du mandat, lu une fois depuis la route (fiche non réutilisée). */
  private readonly mandateId = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly mandate = signal<MandateDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly notFound = signal(false);
  protected readonly forbidden = signal(false);

  /** Message d'erreur de la dernière action tentée. */
  protected readonly actionError = signal<string | null>(null);
  /** Confirmation courte après une action réussie. */
  protected readonly actionMessage = signal<string | null>(null);
  protected readonly saving = signal(false);

  /** Formulaire déplié (un seul à la fois, pour ne pas noyer la fiche). */
  protected readonly openForm = signal<OpenForm>(null);

  protected rentForm = { due_date: '', amount_xof: 0, tenant_name: '', period_label: '' };
  protected incidentForm = { title: '', description: '', priority: 'p3' };
  protected expenseForm = {
    label: '',
    category: 'maintenance',
    amount_xof: 0,
    spent_at: '',
    incident_id: '' as string,
  };
  protected payoutForm = { amount_xof: 0, period_label: '' };

  // --- Rapport mensuel --------------------------------------------------------

  protected readonly report = signal<MandateReport | null>(null);
  protected readonly reportLoading = signal(false);
  /** Mois ciblé au format `YYYY-MM` ; vide = mois courant côté serveur. */
  protected reportMonth = '';

  protected readonly priorityOptions = [
    { value: 'p1', label: 'P1 — critique' },
    { value: 'p2', label: 'P2 — élevée' },
    { value: 'p3', label: 'P3 — normale' },
    { value: 'p4', label: 'P4 — faible' },
  ];

  protected readonly expenseCategories = [
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'reparation', label: 'Réparation' },
    { value: 'autre', label: 'Autre' },
  ];

  /** Incidents encore ouverts — proposés au rattachement d'une dépense. */
  protected readonly openIncidents = computed(() =>
    (this.mandate()?.incidents ?? []).filter((incident) => incident.status !== 'resolu'),
  );

  // --- Ce qui appelle une décision (F8.3) --------------------------------------

  /**
   * Échéances passées et toujours impayées.
   *
   * Un loyer « en attente » dont la date est à venir est normal ; le même
   * après l'échéance est un impayé. La liste ne faisait pas la différence :
   * les deux portaient le même badge, à quinze lignes du haut de page.
   */
  protected readonly lateRents = computed(() => {
    const today = new Date().toISOString().slice(0, 10);
    return (this.mandate()?.rents ?? []).filter(
      (rent) => rent.status !== 'paye' && !!rent.due_date && rent.due_date.slice(0, 10) < today,
    );
  });

  /** Cette échéance est-elle échue et impayée ? (marque posée sur la ligne) */
  protected isLateRent(rent: MandateRent): boolean {
    return this.lateRents().some((late) => late.id === rent.id);
  }

  /** Reversements préparés mais jamais exécutés : de l'argent dû, en attente. */
  protected readonly pendingPayouts = computed(() =>
    (this.mandate()?.payouts ?? []).filter((payout) => payout.status !== 'effectue'),
  );

  /** Incidents ouverts en priorité critique ou élevée. */
  protected readonly urgentIncidents = computed(() =>
    this.openIncidents().filter((incident) => incident.priority === 'p1' || incident.priority === 'p2'),
  );

  /** Total encore dû au propriétaire sur le mois affiché. */
  protected readonly leftToPayout = computed(() => {
    const report = this.report();
    if (!report) {
      return 0;
    }
    return Math.max(0, report.net_owner_xof - report.payouts.total_xof);
  });

  /**
   * **Le bandeau qui manquait.** Sept cartes empilées, dont deux tableaux de
   * douze lignes : un incident critique ouvert et un reversement en attente se
   * lisaient au même niveau que les clauses du contrat, six écrans plus bas.
   *
   * Rien d'inventé ici : chaque signal se relit dans la section vers laquelle
   * il renvoie.
   */
  protected readonly flags = computed<FicheFlag[]>(() => {
    const mandate = this.mandate();
    if (!mandate) {
      return [];
    }
    const flags: FicheFlag[] = [];

    const late = this.lateRents();
    if (late.length) {
      const total = late.reduce((sum, rent) => sum + (rent.amount_xof || 0), 0);
      flags.push({
        level: 'alerte',
        text: `${late.length} loyer${late.length > 1 ? 's' : ''} échu${late.length > 1 ? 's' : ''} et impayé${late.length > 1 ? 's' : ''} — ${this.money(total)}.`,
        anchor: 'mn-loyers',
        cta: 'Encaisser',
      });
    }

    const urgent = this.urgentIncidents();
    if (urgent.length) {
      flags.push({
        level: 'alerte',
        text: `${urgent.length} incident${urgent.length > 1 ? 's' : ''} prioritaire${urgent.length > 1 ? 's' : ''} ouvert${urgent.length > 1 ? 's' : ''} (${urgent[0].title}).`,
        anchor: 'mn-incidents',
        cta: 'Traiter',
      });
    }

    const pending = this.pendingPayouts();
    if (pending.length) {
      const total = pending.reduce((sum, payout) => sum + (payout.amount_xof || 0), 0);
      flags.push({
        level: 'alerte',
        text: `${pending.length} reversement${pending.length > 1 ? 's' : ''} préparé${pending.length > 1 ? 's' : ''} mais non exécuté${pending.length > 1 ? 's' : ''} — ${this.money(total)} dus au propriétaire.`,
        anchor: 'mn-reversements',
        cta: 'Exécuter',
      });
    }

    const others = this.openIncidents().length - urgent.length;
    if (others > 0) {
      flags.push({
        level: 'vigilance',
        text: `${others} autre${others > 1 ? 's' : ''} incident${others > 1 ? 's' : ''} ouvert${others > 1 ? 's' : ''}.`,
        anchor: 'mn-incidents',
        cta: 'Voir',
      });
    }

    // Un mois déficitaire n'est pas anormal (gros travaux) — mais il se décide,
    // il ne se découvre pas au moment de reverser.
    const report = this.report();
    if (report && report.net_owner_xof < 0) {
      flags.push({
        level: 'vigilance',
        text: `Net propriétaire négatif sur ${report.period.label} (${this.money(report.net_owner_xof)}) : les dépenses dépassent les loyers encaissés.`,
        anchor: 'mn-rapport',
        cta: 'Voir le rapport',
      });
    }

    // Un mandat qui expire sans renouvellement fait perdre le bien à la gestion.
    const daysLeft = this.daysUntil(mandate.end_date);
    if (daysLeft !== null && daysLeft <= 60) {
      flags.push({
        level: daysLeft < 0 ? 'alerte' : 'vigilance',
        text:
          daysLeft < 0
            ? `Mandat arrivé à terme le ${this.shortDate(mandate.end_date)}.`
            : `Mandat arrivant à terme dans ${daysLeft} jour(s), le ${this.shortDate(mandate.end_date)}.`,
        anchor: 'mn-contrat',
        cta: 'Voir le contrat',
      });
    }

    return flags;
  });

  /** Jours restants avant une date ; `null` si le mandat est sans terme. */
  private daysUntil(iso: string | null): number | null {
    if (!iso) {
      return null;
    }
    return Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000);
  }

  /** Total des dépenses listées, affiché dans le résumé du volet. */
  protected readonly expensesTotal = computed(() =>
    (this.mandate()?.expenses ?? []).reduce((sum, expense) => sum + (expense.amount_xof || 0), 0),
  );


  constructor() {
    if (Number.isNaN(this.mandateId)) {
      this.notFound.set(true);
      this.loading.set(false);
    } else {
      this.load();
    }
  }

  protected load(): void {
    this.loading.set(true);
    this.notFound.set(false);
    this.forbidden.set(false);

    this.admin.mandate(this.mandateId).subscribe({
      next: (mandate) => {
        this.mandate.set(mandate);
        this.loading.set(false);
        this.loadReport();
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        if (error.status === 403) this.forbidden.set(true);
        else this.notFound.set(true);
      },
    });
  }

  protected loadReport(): void {
    this.reportLoading.set(true);
    this.admin.mandateReport(this.mandateId, this.reportMonth || undefined).subscribe({
      next: (report) => {
        this.report.set(report);
        this.reportLoading.set(false);
      },
      error: () => this.reportLoading.set(false),
    });
  }

  /** Ouvre / referme un formulaire (et réinitialise les messages). */
  protected toggleForm(form: OpenForm): void {
    this.actionError.set(null);
    this.actionMessage.set(null);
    this.openForm.update((current) => (current === form ? null : form));
  }

  // --- Loyers -----------------------------------------------------------------

  protected addRent(): void {
    const amount = Number(this.rentForm.amount_xof);
    if (!this.rentForm.due_date || !(amount > 0)) {
      this.actionError.set('Une échéance demande une date et un montant supérieur à zéro.');
      return;
    }

    this.run(
      this.admin.addRent(this.mandateId, {
        due_date: this.rentForm.due_date,
        amount_xof: amount,
        tenant_name: this.rentForm.tenant_name.trim() || null,
        period_label: this.rentForm.period_label.trim() || null,
      }),
      'Échéance ajoutée.',
      () => {
        this.rentForm = { due_date: '', amount_xof: 0, tenant_name: '', period_label: '' };
      },
    );
  }

  protected markRentPaid(rent: MandateRent): void {
    this.run(this.admin.markRentPaid(rent.id), 'Loyer encaissé.');
  }

  // --- Incidents --------------------------------------------------------------

  protected addIncident(): void {
    const title = this.incidentForm.title.trim();
    if (!title) {
      this.actionError.set('Un incident demande au moins un intitulé.');
      return;
    }

    this.run(
      this.admin.addIncident(this.mandateId, {
        title,
        description: this.incidentForm.description.trim() || null,
        priority: this.incidentForm.priority,
      }),
      'Incident signalé.',
      () => {
        this.incidentForm = { title: '', description: '', priority: 'p3' };
      },
    );
  }

  protected resolveIncident(incident: MandateIncident): void {
    this.run(this.admin.resolveIncident(incident.id), 'Incident clos.');
  }

  // --- Dépenses ---------------------------------------------------------------

  protected addExpense(): void {
    const amount = Number(this.expenseForm.amount_xof);
    if (!this.expenseForm.label.trim() || !this.expenseForm.spent_at || !(amount > 0)) {
      this.actionError.set('Une dépense demande un intitulé, une date et un montant.');
      return;
    }

    this.run(
      this.admin.addExpense(this.mandateId, {
        label: this.expenseForm.label.trim(),
        category: this.expenseForm.category,
        amount_xof: amount,
        spent_at: this.expenseForm.spent_at,
        incident_id: this.expenseForm.incident_id ? Number(this.expenseForm.incident_id) : null,
      }),
      'Dépense enregistrée.',
      () => {
        this.expenseForm = {
          label: '',
          category: 'maintenance',
          amount_xof: 0,
          spent_at: '',
          incident_id: '',
        };
      },
    );
  }

  // --- Reversements -----------------------------------------------------------

  protected addPayout(): void {
    const amount = Number(this.payoutForm.amount_xof);
    if (!(amount > 0)) {
      this.actionError.set('Un reversement demande un montant supérieur à zéro.');
      return;
    }

    this.run(
      this.admin.addPayout(this.mandateId, {
        amount_xof: amount,
        period_label: this.payoutForm.period_label.trim() || null,
      }),
      'Reversement préparé.',
      () => {
        this.payoutForm = { amount_xof: 0, period_label: '' };
      },
    );
  }

  protected markPayoutPaid(payout: MandatePayout): void {
    this.run(this.admin.markPayoutPaid(payout.id), 'Reversement marqué effectué.');
  }

  /**
   * Exécute une action puis **recharge la fiche entière**.
   *
   * Choix assumé : chaque écriture déplace des agrégats (loyers encaissés,
   * dépenses, incidents ouverts) ET le rapport mensuel. Recoller la réponse
   * partielle dans la liste locale laisserait ces totaux faux à l'écran ; on
   * préfère un aller-retour de plus à un tableau de bord qui ment.
   */
  private run<T>(
    request$: { subscribe: (o: { next: (v: T) => void; error: (e: HttpErrorResponse) => void }) => void },
    message: string,
    onSuccess?: () => void,
  ): void {
    this.saving.set(true);
    this.actionError.set(null);
    this.actionMessage.set(null);

    request$.subscribe({
      next: () => {
        this.saving.set(false);
        this.actionMessage.set(message);
        this.openForm.set(null);
        onSuccess?.();
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  // --- Présentation -----------------------------------------------------------

  /** Localisation lisible du bien géré (commune · département · région). */
  protected place(mandate: MandateDossier): string {
    const location = mandate.property?.location;
    if (!location) return '—';
    const parts = [location.commune, location.department, location.region].filter(
      (part): part is string => !!part,
    );
    return parts.length ? parts.join(' · ') : '—';
  }

  /** Montant en francs CFA, avec séparateurs de milliers. */
  protected money(amount: number | null | undefined): string {
    if (amount === null || amount === undefined) return '—';
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
  }

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  protected rentStatusClass(status: string | null): string {
    switch (status) {
      case 'paye':
        return 'is-ok';
      case 'en_retard':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  protected incidentStatusClass(status: string | null): string {
    return status === 'resolu' ? 'is-ok' : 'is-warn';
  }

  protected payoutStatusClass(status: string | null): string {
    return status === 'effectue' ? 'is-ok' : 'is-pending';
  }

  protected mandateStatusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'suspendu':
        return 'is-warn';
      case 'termine':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      return 'Action réservée aux comptes disposant du droit « gestion locative ».';
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
      return first ?? body?.message ?? 'Données invalides.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
