import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import {
  AdminService,
  AssignProviderPayload,
  PackCategory,
  ProviderMissionItem,
  QuoteComponent,
  TeamBuildingQuote,
  TeamBuildingRequestDetail,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';
import { Provider } from '../../../../models/provider.model';
import { FicheFlag, FicheSignalsComponent } from '../../shared/fiche-signals/fiche-signals';

/** Option de catégorie de pack (partagée devis ↔ affectation). */
interface CategoryOption {
  value: PackCategory;
  label: string;
}

/** Une ligne de composition de devis en cours de saisie. */
interface DraftLine {
  category: PackCategory;
  label: string;
  quantity: number;
  unit_price_xof: number | null;
}

/**
 * **Fiche d'une demande de team building** (F7.2.h) —
 * `/back-office/team-building/:id`.
 *
 * Trois zones, alimentées par `GET /team-building-requests/{id}` (demande +
 * devis + prestataires affectés) :
 *   - **La demande** : participants, ville, période, budget, besoins, entreprise ;
 *   - **Devis (pack)** : composition ligne par ligne (`POST .../quotes`) puis
 *     envoi à l'entreprise (`PATCH /team-building-quotes/{id}/send`) ;
 *   - **Affectation prestataires** (exigence CDC « affectation prestataires ») :
 *     affecte un prestataire **validé** à une brique du pack
 *     (`POST .../assignments`) → crée une mission Pro qui suit son cycle propre.
 *
 * La composition, l'envoi et l'affectation exigent le rôle admin côté serveur ;
 * l'écran masque ces actions aux profils sans le rôle et reflète les refus.
 */
@Component({
  selector: 'app-backoffice-team-building-detail-page',
  imports: [FormsModule, FicheSignalsComponent],
  templateUrl: './backoffice-team-building-detail-page.html',
  // Briques communes des fiches hiérarchisées en F8.3 (chiffres clés, volets).
  styleUrls: ['./backoffice-team-building-detail-page.scss', '../../shared/fiche-blocks.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeTeamBuildingDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly request = signal<TeamBuildingRequestDetail | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Seul un admin/super_admin peut composer un devis ou affecter (garde serveur). */
  protected readonly canManage = computed(
    () => this.auth.hasRole('admin') || this.auth.hasRole('super_admin'),
  );

  protected readonly categoryOptions: readonly CategoryOption[] = [
    { value: 'lieu', label: 'Lieu' },
    { value: 'hebergement', label: 'Hébergement' },
    { value: 'restauration', label: 'Restauration' },
    { value: 'activite', label: 'Activité' },
    { value: 'mobilite', label: 'Mobilité' },
    { value: 'animation', label: 'Animation' },
  ];

  // --- Composition d'un devis -------------------------------------------------
  protected readonly draftLines = signal<DraftLine[]>([this.emptyLine()]);
  protected marginRate = 15;
  protected readonly composing = signal(false);
  protected readonly composeError = signal<string | null>(null);

  /** Envoi d'un devis en cours (verrouille son bouton). */
  protected readonly sendingId = signal<number | null>(null);

  // --- Affectation d'un prestataire -------------------------------------------
  protected readonly providers = signal<Provider[]>([]);
  protected readonly providersLoaded = signal(false);
  protected assignProviderId: number | null = null;
  protected assignCategory: PackCategory = 'animation';
  protected assignTitle = '';
  protected assignAmount: number | null = null;
  protected readonly assigning = signal(false);
  protected readonly assignError = signal<string | null>(null);

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
    this.admin.teamBuildingRequest(id).subscribe({
      next: (request) => {
        this.request.set(request);
        this.loading.set(false);
        // Charge les prestataires validés une seule fois (pour l'affectation).
        if (this.canManage() && !this.providersLoaded()) this.loadProviders();
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  protected back(): void {
    void this.router.navigate(['/back-office', 'team-building']);
  }

  // --- Devis ------------------------------------------------------------------

  private emptyLine(): DraftLine {
    return { category: 'hebergement', label: '', quantity: 1, unit_price_xof: null };
  }

  protected addLine(): void {
    this.draftLines.update((lines) => [...lines, this.emptyLine()]);
  }

  protected removeLine(index: number): void {
    this.draftLines.update((lines) => (lines.length > 1 ? lines.filter((_, i) => i !== index) : lines));
  }

  /** Sous-total en direct des lignes valides (aperçu avant marge). */
  protected readonly draftSubtotal = computed(() =>
    this.draftLines().reduce((sum, line) => {
      const price = line.unit_price_xof ?? 0;
      const qty = line.quantity > 0 ? line.quantity : 0;
      return sum + price * qty;
    }, 0),
  );

  /** Total estimé (sous-total + marge) affiché avant envoi. */
  protected readonly draftTotal = computed(() => {
    const rate = this.marginRate >= 0 ? this.marginRate : 0;
    return Math.round(this.draftSubtotal() * (1 + rate / 100));
  });

  /** Compose le devis à partir des lignes saisies. */
  protected composeQuote(): void {
    const req = this.request();
    if (!req || this.composing()) return;

    const components: QuoteComponent[] = this.draftLines()
      .filter((line) => line.unit_price_xof !== null && line.quantity > 0)
      .map((line) => ({
        category: line.category,
        label: line.label.trim() || undefined,
        quantity: line.quantity,
        unit_price_xof: line.unit_price_xof as number,
      }));

    if (components.length === 0) {
      this.composeError.set('Ajoutez au moins une ligne avec une quantité et un prix.');
      return;
    }

    this.composing.set(true);
    this.composeError.set(null);

    this.admin.composeTeamBuildingQuote(req.id, components, this.marginRate).subscribe({
      next: () => {
        this.composing.set(false);
        this.draftLines.set([this.emptyLine()]);
        this.load(req.id);
      },
      error: (err: HttpErrorResponse) => {
        this.composing.set(false);
        this.composeError.set(this.messageFor(err));
      },
    });
  }

  /** Envoie un devis brouillon à l'entreprise. */
  protected sendQuote(quote: TeamBuildingQuote): void {
    const req = this.request();
    if (!req || this.sendingId() !== null) return;
    this.sendingId.set(quote.id);
    this.admin.sendTeamBuildingQuote(quote.id).subscribe({
      next: () => {
        this.sendingId.set(null);
        this.load(req.id);
      },
      error: () => {
        this.sendingId.set(null);
        this.load(req.id);
      },
    });
  }

  protected canSend(quote: TeamBuildingQuote): boolean {
    return quote.status === 'brouillon';
  }

  // --- Ce qui appelle une décision (F8.3) --------------------------------------

  /** Missions du dossier, hors annulées / refusées. */
  protected readonly missionsOfActive = computed(() =>
    this.missionsOf().filter((mission) => mission.status !== 'annulee' && mission.status !== 'refusee'),
  );

  /** Total engagé auprès des prestataires : ce que le pack coûte réellement. */
  protected readonly committed = computed(() =>
    this.missionsOfActive().reduce((sum, mission) => sum + (mission.amount_xof || 0), 0),
  );

  /** Devis accepté par l'entreprise : le montant qui fait foi. */
  protected readonly acceptedQuote = computed<TeamBuildingQuote | null>(
    () => this.request()?.quotes.find((quote) => quote.status === 'accepte') ?? null,
  );

  /** Un devis composé attend d'être envoyé. */
  protected readonly hasDraftQuote = computed(
    () => this.request()?.quotes.some((quote) => quote.status === 'brouillon') ?? false,
  );

  /** Marge réelle du pack : ce que l'entreprise paie moins ce qu'on engage. */
  protected readonly margin = computed<number | null>(() => {
    const accepted = this.acceptedQuote();
    return accepted ? accepted.total_xof - this.committed() : null;
  });

  /**
   * Briques du devis accepté auxquelles aucun prestataire n'est affecté.
   *
   * C'est l'angle mort du pack : un devis vendu « hébergement + traiteur +
   * animation » dont seule l'animation est pourvue paraît complet dans les deux
   * tableaux de la fiche — il faut les confronter pour voir le trou.
   */
  protected readonly unstaffedCategories = computed<string[]>(() => {
    const accepted = this.acceptedQuote();
    if (!accepted) {
      return [];
    }
    const staffed = new Set(this.missionsOfActive().map((mission) => mission.category));
    const sold = new Set(accepted.lines.map((line) => line.category));
    return [...sold].filter((category) => !staffed.has(category)).map((c) => this.categoryLabel(c));
  });

  /**
   * **Le bandeau qui manquait.** Trois cartes, deux tableaux et deux
   * formulaires : le devis jamais envoyé et la brique sans prestataire étaient
   * lisibles — à condition de croiser soi-même deux tableaux distants.
   */
  protected readonly flags = computed<FicheFlag[]>(() => {
    const req = this.request();
    if (!req) {
      return [];
    }
    const flags: FicheFlag[] = [];

    if (this.hasDraftQuote()) {
      flags.push({
        level: 'alerte',
        text: 'Un devis est composé mais n’a jamais été envoyé à l’entreprise.',
        anchor: 'tb-devis',
        cta: 'Envoyer',
      });
    }

    const unstaffed = this.unstaffedCategories();
    if (unstaffed.length) {
      flags.push({
        level: 'alerte',
        text: `Pack accepté : ${unstaffed.length} brique(s) sans prestataire (${unstaffed.join(', ')}).`,
        anchor: 'tb-prestataires',
        cta: 'Affecter',
      });
    }

    // Le pack coûte plus cher qu'il n'a été vendu.
    const margin = this.margin();
    if (margin !== null && margin < 0) {
      flags.push({
        level: 'alerte',
        text: `Marge négative : ${this.xof(this.committed())} engagés pour un pack vendu ${this.xof(this.acceptedQuote()!.total_xof)}.`,
        anchor: 'tb-prestataires',
        cta: 'Vérifier',
      });
    }

    // L'événement commence, et rien n'est validé.
    const start = req.start_date;
    if (start && start.slice(0, 10) < new Date().toISOString().slice(0, 10) && !this.acceptedQuote()) {
      flags.push({
        level: 'alerte',
        text: `Période commencée le ${this.shortDate(start)} sans devis accepté.`,
        anchor: 'tb-demande',
        cta: 'Voir la demande',
      });
    }

    if (!req.quotes.length) {
      flags.push({
        level: 'vigilance',
        text: 'Aucun devis composé pour cette demande.',
        anchor: 'tb-devis',
        cta: 'Composer',
      });
    }

    // Le devis dépasse ce que l'entreprise avait annoncé pouvoir mettre.
    const proposed = this.acceptedQuote() ?? req.quotes[0] ?? null;
    if (proposed && req.budget_xof && proposed.total_xof > req.budget_xof) {
      flags.push({
        level: 'vigilance',
        text: `Devis (${this.xof(proposed.total_xof)}) supérieur au budget indicatif annoncé (${this.xof(req.budget_xof)}).`,
        anchor: 'tb-devis',
        cta: 'Voir le devis',
      });
    }

    return flags;
  });

  // --- Affectation prestataires -----------------------------------------------

  private loadProviders(): void {
    this.providersLoaded.set(true);
    this.admin.adminProviders({ status: 'valide' }).subscribe({
      next: (paginated) => this.providers.set(paginated.data),
      error: () => this.providers.set([]),
    });
  }

  /** Affecte le prestataire sélectionné à la brique choisie. */
  protected assign(): void {
    const req = this.request();
    if (!req || this.assigning()) return;

    if (!this.assignProviderId) {
      this.assignError.set('Choisissez un prestataire validé.');
      return;
    }
    if (this.assignAmount === null || this.assignAmount < 0) {
      this.assignError.set('Indiquez un montant (FCFA).');
      return;
    }

    const payload: AssignProviderPayload = {
      provider_id: this.assignProviderId,
      category: this.assignCategory,
      title: this.assignTitle.trim() || undefined,
      amount_xof: this.assignAmount,
    };

    this.assigning.set(true);
    this.assignError.set(null);

    this.admin.assignTeamBuildingProvider(req.id, payload).subscribe({
      next: () => {
        this.assigning.set(false);
        this.assignProviderId = null;
        this.assignTitle = '';
        this.assignAmount = null;
        this.load(req.id);
      },
      error: (err: HttpErrorResponse) => {
        this.assigning.set(false);
        this.assignError.set(this.messageFor(err));
      },
    });
  }

  // --- Présentation -----------------------------------------------------------

  /** Libellé d'une catégorie de pack. */
  /**
   * Libellé d'une brique de pack.
   *
   * ⚠️ Accepte une chaîne quelconque depuis F7.3.e3 : la colonne `category` d'une
   * mission est PARTAGÉE côté serveur (brique de pack ici, lot BTP pour une
   * mission de chantier). Sur cet écran on ne voit que des missions team building,
   * mais le type de `ProviderMissionItem` est désormais l'union des deux — et une
   * valeur inconnue est rendue telle quelle plutôt que masquée.
   */
  protected categoryLabel(category: string | null): string {
    return this.categoryOptions.find((c) => c.value === category)?.label ?? category ?? '—';
  }

  /** Besoins déclarés → liste de puces lisibles (clés à true / valeurs). */
  protected readonly needsList = computed<string[]>(() => {
    const needs = this.request()?.needs;
    if (!needs) return [];
    return Object.entries(needs)
      .filter(([, value]) => value !== false && value !== null && value !== '')
      .map(([key, value]) => (value === true ? key : `${key} : ${value}`));
  });

  /** Classe CSS du badge de statut de la demande. */
  protected requestStatusClass(status: string | null): string {
    switch (status) {
      case 'accepte':
        return 'is-ok';
      case 'devis_envoye':
      case 'nouveau':
        return 'is-info';
      case 'en_etude':
        return 'is-pending';
      case 'annule':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Classe CSS du badge de statut d'un devis. */
  protected quoteStatusClass(status: string | null): string {
    switch (status) {
      case 'accepte':
        return 'is-ok';
      case 'envoye':
        return 'is-info';
      case 'refuse':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Classe CSS du badge de statut d'une mission (affectation). */
  protected missionStatusClass(status: string | null): string {
    switch (status) {
      case 'terminee':
      case 'acceptee':
        return 'is-ok';
      case 'en_cours':
        return 'is-info';
      case 'refusee':
      case 'annulee':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Prestataires affectés regroupés par catégorie (pour l'affichage). */
  protected missionsOf(): ProviderMissionItem[] {
    return this.request()?.provider_missions ?? [];
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Période « du … au … ». */
  protected period(): string {
    const req = this.request();
    if (!req) return '—';
    return `${this.shortDate(req.start_date)} → ${this.shortDate(req.end_date)}`;
  }

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
      return "Vous n'avez pas le droit d'effectuer cette action.";
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Données invalides.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
