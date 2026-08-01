import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import {
  AdminService,
  AssignConstructionProviderPayload,
  ComposeQuoteLine,
  ConstructionDossier,
  ConstructionLot,
  ConstructionMilestone,
  ConstructionQuote,
  ConstructionReport,
  MilestonePayload,
  ProviderMissionItem,
} from '../../../../core/api/admin.service';
import { Provider } from '../../../../models/provider.model';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { FicheFlag, FicheSignalsComponent } from '../../shared/fiche-signals/fiche-signals';

/**
 * Fiche **demande de construction** du back-office (F7.3.b) — CDC §6
 * *Construction*.
 *
 * L'onglet Construction (F7.2.e) n'affichait qu'un tableau : objectif, ville,
 * surface, budget, compteurs. Illisible pour un dossier de chantier, dont
 * l'essentiel — qui a demandé quoi, où en est le chantier, ce qui a été
 * constaté sur place — ne tient pas dans une ligne. Cette fiche restitue le
 * dossier en trois temps :
 *
 *  - **le projet** : demandeur (nom + contact, exposé au serveur pour l'occasion),
 *    objectif, localisation, surface, budget annoncé vs coût estimé, finition,
 *    description ;
 *  - **l'avancement** : le planning des jalons, **pilotable** depuis F7.3.e1 ;
 *  - **les comptes rendus** : photos / vidéos datées et commentées, avec
 *    **dépôt** d'un nouveau compte rendu (`gerer:chantiers`).
 *
 * **F7.3.e1 — les jalons deviennent pilotables.** Ils étaient semés au dépôt de
 * la demande puis figés, faute d'endpoint (trou backend comblé dans le module
 * Build). Deux gestes distincts, parce que ce sont deux métiers :
 *  - *faire avancer* : démarrer une étape, l'achever, la rouvrir. Le serveur
 *    tient la cohérence statut ↔ date réelle (achevé sans date = daté du jour,
 *    réouverture = date effacée) ; l'écran n'en refait pas la logique.
 *  - *replanifier* : ajouter, renommer, redater, réordonner, retirer — car aucun
 *    chantier ne suit exactement le gabarit posé à la création.
 *
 * Le dossier n'est **pas rechargé** après une écriture sur un jalon, à la
 * différence de la fiche mandat (F7.3.a) : le serveur renvoie le jalon à jour, et
 * la jauge d'avancement est un `computed` local. Rien d'autre à l'écran ne dépend
 * des jalons, donc recharger toute la fiche serait un aller-retour pour rien.
 *
 * **F8.3 — la fiche cesse d'être une pile.** Six cartes de même poids : rien ne
 * disait par où commencer. Un bandeau de signaux ouvre désormais la fiche, une
 * bande de chiffres clés confronte budget / estimation / devis accepté /
 * engagements, et les trois sections d'archive (devis, prestataires, comptes
 * rendus) se replient derrière un résumé chiffré.
 */
@Component({
  selector: 'app-backoffice-construction-detail-page',
  imports: [FormsModule, RouterLink, FicheSignalsComponent],
  templateUrl: './backoffice-construction-detail-page.html',
  // La feuille des volets repliables est COMMUNE aux fiches hiérarchisées en
  // F8.3 : Angular l'encapsule pour chacune, le style ne se recopie pas.
  styleUrls: ['./backoffice-construction-detail-page.scss', '../../shared/fiche-blocks.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeConstructionDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly requestId = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<ConstructionDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly notFound = signal(false);
  protected readonly forbidden = signal(false);

  protected readonly reports = signal<ConstructionReport[]>([]);
  protected readonly reportsTotal = signal(0);
  protected readonly reportsPage = signal(1);
  protected readonly reportsLastPage = signal(1);
  protected readonly reportsLoading = signal(false);

  protected readonly actionError = signal<string | null>(null);
  protected readonly actionMessage = signal<string | null>(null);
  protected readonly saving = signal(false);

  /** Formulaire de dépôt d'un compte rendu (déplié à la demande). */
  protected readonly reportFormOpen = signal(false);
  protected reportForm = {
    type: 'photo',
    reported_at: '',
    comment: '',
    video_url: '',
    photos: '',
  };

  protected readonly reportTypes = [
    { value: 'photo', label: 'Photos' },
    { value: 'video', label: 'Vidéo' },
    { value: 'mixte', label: 'Photos + vidéo' },
  ];

  // --- Pilotage des jalons (F7.3.e1) ------------------------------------------

  /** Jalon en cours d'écriture : verrouille SES boutons, pas ceux des autres. */
  protected readonly busyMilestoneId = signal<number | null>(null);
  /** Jalon dont le panneau de replanification est ouvert. */
  protected readonly editingMilestoneId = signal<number | null>(null);
  /** Saisie du panneau de replanification. */
  protected milestoneEdit = { name: '', planned_date: '', actual_date: '' };

  /** Formulaire d'ajout d'un jalon (déplié à la demande). */
  protected readonly milestoneFormOpen = signal(false);
  protected milestoneForm = { name: '', planned_date: '' };
  protected readonly addingMilestone = signal(false);

  // --- Devis de chantier (F7.3.e2) --------------------------------------------

  protected readonly quotes = signal<ConstructionQuote[]>([]);
  protected readonly quotesLoading = signal(false);
  /** Devis dont les lignes sont dépliées (un seul à la fois). */
  protected readonly openQuoteId = signal<number | null>(null);
  /** Devis en cours d'envoi. */
  protected readonly sendingQuoteId = signal<number | null>(null);

  /** Composeur : lignes en cours de saisie. */
  protected readonly composerOpen = signal(false);
  protected readonly composerLines = signal<ComposeQuoteLine[]>([]);
  protected composerMarginRate: number | null = null;
  protected composerValidUntil = '';
  protected readonly composing = signal(false);

  /** Lots proposés, dans l'ordre d'exécution du chantier (miroir de l'enum). */
  protected readonly lots: readonly { value: ConstructionLot; label: string }[] = [
    { value: 'etudes', label: 'Études & permis' },
    { value: 'terrassement', label: 'Terrassement' },
    { value: 'fondations', label: 'Fondations' },
    { value: 'gros_oeuvre', label: 'Gros œuvre' },
    { value: 'charpente_couverture', label: 'Charpente & couverture' },
    { value: 'menuiserie', label: 'Menuiserie' },
    { value: 'plomberie', label: 'Plomberie' },
    { value: 'electricite', label: 'Électricité' },
    { value: 'finitions', label: 'Finitions' },
    { value: 'amenagements_exterieurs', label: 'Aménagements extérieurs' },
    { value: 'main_oeuvre', label: 'Main d’œuvre' },
    { value: 'divers', label: 'Divers' },
  ];

  /** Unités courantes d'un devis BTP (saisie libre possible). */
  protected readonly units = ['m2', 'm3', 'ml', 'u', 'forfait', 'jour'];

  // --- Prestataires BTP affectés (F7.3.e3) -------------------------------------

  protected readonly assignments = signal<ProviderMissionItem[]>([]);
  protected readonly assignmentsLoading = signal(false);

  /** Formulaire d'affectation (déplié à la demande). */
  protected readonly assignFormOpen = signal(false);
  protected assignForm = { provider_id: 0, lot: 'gros_oeuvre' as ConstructionLot, amount_xof: 0, scheduled_at: '' };
  protected readonly assigning = signal(false);

  /** Prestataires VALIDÉS proposés au sélecteur (chargés à l'ouverture). */
  protected readonly providers = signal<Provider[]>([]);
  protected readonly providersLoading = signal(false);
  /** Vrai si le compte n'a pas le droit de lister les prestataires (403). */
  protected readonly providersForbidden = signal(false);

  /**
   * Sous-total du devis en cours de composition, recalculé à la saisie.
   * Le serveur refait ce calcul à l'enregistrement — c'est lui qui fait foi ;
   * ici c'est un aperçu, pour que l'agent voie où il en est.
   */
  protected readonly composerSubtotal = computed(() =>
    this.composerLines().reduce(
      (sum, line) => sum + Math.round((line.quantity || 0) * (line.unit_price_xof || 0)),
      0,
    ),
  );

  /**
   * Avancement du chantier en pourcentage, d'après les jalons terminés.
   * `null` quand le dossier n'a aucun jalon (rien à jauger).
   */
  protected readonly progress = computed<number | null>(() => {
    const milestones = this.dossier()?.milestones ?? [];
    if (!milestones.length) return null;
    const done = milestones.filter((milestone) => milestone.status === 'termine').length;
    return Math.round((done / milestones.length) * 100);
  });

  // --- Ce qui appelle une décision (F8.3) --------------------------------------

  /**
   * Total engagé auprès des prestataires (missions non annulées).
   * Confronté au devis accepté, c'est la marge réelle du chantier.
   */
  protected readonly committed = computed(() =>
    this.assignments()
      .filter((mission) => mission.status !== 'annulee' && mission.status !== 'refusee')
      .reduce((sum, mission) => sum + (mission.amount_xof || 0), 0),
  );

  /**
   * Un devis attend d'être envoyé. Seule condition qui déplie le volet « Devis »
   * d'office : il y a un geste en attente, pas une archive à consulter.
   */
  protected readonly hasDraftQuote = computed(() =>
    this.quotes().some((quote) => quote.status === 'brouillon'),
  );

  /** Devis accepté par le client, s'il y en a un : le montant qui fait foi. */
  protected readonly acceptedQuote = computed<ConstructionQuote | null>(
    () => this.quotes().find((quote) => quote.status === 'accepte') ?? null,
  );

  /** Jalons dont la date prévue est passée sans que l'étape soit achevée. */
  protected readonly lateMilestones = computed(() => {
    const today = new Date().toISOString().slice(0, 10);
    return (this.dossier()?.milestones ?? []).filter(
      (milestone) =>
        milestone.status !== 'termine' &&
        !!milestone.planned_date &&
        milestone.planned_date.slice(0, 10) < today,
    );
  });

  /**
   * **Le bandeau qui manquait.** Six cartes de même poids : l'agent devait les
   * parcourir toutes pour découvrir qu'un devis chiffré dormait sans avoir été
   * envoyé, ou qu'un chantier vendu n'avait aucun prestataire. Ces signaux ne
   * s'ajoutent pas au dossier — ils y étaient déjà, dispersés. On les remonte.
   *
   * `alerte` = le dossier est bloqué ou part de travers ; `vigilance` = à
   * surveiller. Aucun signal n'est inventé : chacun se relit dans la section
   * vers laquelle il renvoie.
   */
  protected readonly flags = computed<FicheFlag[]>(() => {
    const dossier = this.dossier();
    if (!dossier) {
      return [];
    }
    const flags: FicheFlag[] = [];

    // Un devis chiffré mais jamais envoyé bloque tout le cycle : le client
    // attend un prix, l'équipe croit l'avoir transmis.
    const drafts = this.quotes().filter((quote) => quote.status === 'brouillon');
    if (drafts.length) {
      flags.push({
        level: 'alerte',
        text: `${drafts.length} devis chiffré${drafts.length > 1 ? 's' : ''} jamais envoyé au client.`,
        anchor: 'cs-devis',
        cta: 'Voir les devis',
      });
    }

    // Chantier vendu, mais personne sur le terrain.
    if (this.acceptedQuote() && !this.assignments().length) {
      flags.push({
        level: 'alerte',
        text: 'Devis accepté par le client, aucun prestataire affecté.',
        anchor: 'cs-prestataires',
        cta: 'Affecter',
      });
    }

    // Le planning a décroché du réel.
    const late = this.lateMilestones();
    if (late.length) {
      flags.push({
        level: 'alerte',
        text: `${late.length} étape${late.length > 1 ? 's' : ''} en retard sur le planning (${late[0].name}${late.length > 1 ? '…' : ''}).`,
        anchor: 'cs-avancement',
        cta: 'Replanifier',
      });
    }

    // Le devis engage plus que ce que le client a accepté de payer.
    const accepted = this.acceptedQuote();
    if (accepted && this.committed() > accepted.total_xof) {
      flags.push({
        level: 'alerte',
        text: `Engagements prestataires (${this.money(this.committed())}) supérieurs au devis accepté (${this.money(accepted.total_xof)}).`,
        anchor: 'cs-prestataires',
        cta: 'Vérifier',
      });
    }

    // Signal historique de la carte « Le projet », désormais en tête.
    const gap = this.budgetGap(dossier);
    if (gap !== null && gap < 0) {
      flags.push({
        level: 'vigilance',
        text: `Budget annoncé inférieur de ${this.money(-gap)} à l’estimation.`,
        anchor: 'cs-projet',
        cta: 'Voir le projet',
      });
    }

    // Un devis envoyé sans réponse n'est pas un dossier en cours : c'est un
    // dossier à relancer.
    const stale = this.quotes().filter(
      (quote) => quote.status === 'envoye' && this.daysSince(quote.sent_at) > 14,
    );
    if (stale.length) {
      flags.push({
        level: 'vigilance',
        text: `Devis envoyé il y a plus de 14 jours sans réponse du client.`,
        anchor: 'cs-devis',
        cta: 'Relancer',
      });
    }

    // Un chantier en cours qui ne produit plus de constat n'est plus suivi.
    if (dossier.status === 'en_chantier' && this.daysSince(this.lastReportDate()) > 30) {
      flags.push({
        level: 'vigilance',
        text: 'Aucun compte rendu depuis plus de 30 jours sur un chantier en cours.',
        anchor: 'cs-comptes-rendus',
        cta: 'Publier un constat',
      });
    }

    return flags;
  });

  /** Ce jalon est-il en retard ? (marque posée sur l'étape elle-même) */
  protected isLate(milestone: ConstructionMilestone): boolean {
    return this.lateMilestones().some((late) => late.id === milestone.id);
  }

  /** Date du constat le plus récent, `null` si le chantier n'en a aucun. */
  protected lastReportDate(): string | null {
    const dates = this.reports()
      .map((report) => report.reported_at)
      .filter((date): date is string => !!date)
      .sort();
    return dates.length ? dates[dates.length - 1] : null;
  }

  /**
   * Nombre de jours écoulés depuis une date. `Infinity` si la date est absente :
   * « jamais de compte rendu » doit se comporter comme « très ancien », sinon
   * le dossier le moins suivi serait justement celui qui ne remonte rien.
   */
  private daysSince(iso: string | null | undefined): number {
    if (!iso) {
      return Number.POSITIVE_INFINITY;
    }
    return Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);
  }


  constructor() {
    if (Number.isNaN(this.requestId)) {
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

    this.admin.constructionRequest(this.requestId).subscribe({
      next: (dossier) => {
        this.dossier.set(dossier);
        this.loading.set(false);
        this.loadReports();
        this.loadQuotes();
        this.loadAssignments();
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        if (error.status === 403) this.forbidden.set(true);
        else this.notFound.set(true);
      },
    });
  }

  protected loadReports(): void {
    this.reportsLoading.set(true);
    this.admin.constructionReports(this.requestId, this.reportsPage()).subscribe({
      next: (paginated) => {
        this.reports.set(paginated.data);
        this.reportsTotal.set(paginated.meta.total);
        this.reportsLastPage.set(paginated.meta.last_page);
        this.reportsLoading.set(false);
      },
      error: () => this.reportsLoading.set(false),
    });
  }

  protected goToReports(page: number): void {
    if (page < 1 || page > this.reportsLastPage() || page === this.reportsPage()) return;
    this.reportsPage.set(page);
    this.loadReports();
  }

  protected toggleReportForm(): void {
    this.actionError.set(null);
    this.actionMessage.set(null);
    this.reportFormOpen.update((open) => !open);
  }

  /** Publie un compte rendu de chantier. */
  protected addReport(): void {
    if (!this.reportForm.reported_at) {
      this.actionError.set('Un compte rendu demande une date de constat.');
      return;
    }

    // Les photos sont saisies une par ligne (chemins de fichiers déjà déposés).
    const photos = this.reportForm.photos
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => !!line);

    this.saving.set(true);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin
      .addConstructionReport(this.requestId, {
        type: this.reportForm.type as 'photo' | 'video' | 'mixte',
        reported_at: this.reportForm.reported_at,
        comment: this.reportForm.comment.trim() || undefined,
        video_url: this.reportForm.video_url.trim() || undefined,
        photos: photos.length ? photos : undefined,
      })
      .subscribe({
        next: () => {
          this.saving.set(false);
          this.actionMessage.set('Compte rendu publié.');
          this.reportFormOpen.set(false);
          this.reportForm = { type: 'photo', reported_at: '', comment: '', video_url: '', photos: '' };
          // On revient en tête de liste : le nouveau compte rendu y figure.
          this.reportsPage.set(1);
          this.loadReports();
        },
        error: (error: HttpErrorResponse) => {
          this.saving.set(false);
          this.actionError.set(this.messageFor(error));
        },
      });
  }

  // --- Prestataires BTP : lecture & affectation --------------------------------

  protected loadAssignments(): void {
    this.assignmentsLoading.set(true);
    this.admin.constructionAssignments(this.requestId).subscribe({
      next: (missions) => {
        this.assignments.set(missions);
        this.assignmentsLoading.set(false);
      },
      error: () => this.assignmentsLoading.set(false),
    });
  }

  /**
   * Ouvre le formulaire d'affectation et charge les prestataires VALIDÉS — seuls
   * eux sont affectables (le serveur le refuse sinon).
   *
   * ⚠️ `GET /admin/providers` exige `valider:prestataire`. Un compte qui ne l'a
   * pas verra un message clair au lieu d'un sélecteur vide inexplicable.
   */
  protected toggleAssignForm(): void {
    this.actionError.set(null);
    this.actionMessage.set(null);

    const opening = !this.assignFormOpen();
    this.assignFormOpen.set(opening);

    if (opening && this.providers().length === 0 && !this.providersForbidden()) {
      this.providersLoading.set(true);
      this.admin.adminProviders({ status: 'valide' }).subscribe({
        next: (paginated) => {
          this.providers.set(paginated.data);
          this.providersLoading.set(false);
        },
        error: (error: HttpErrorResponse) => {
          this.providersLoading.set(false);
          if (error.status === 403) this.providersForbidden.set(true);
        },
      });
    }
  }

  /** Affecte le prestataire choisi au lot choisi. */
  protected assignProvider(): void {
    if (!this.assignForm.provider_id) {
      this.actionError.set('Choisissez un prestataire à affecter.');
      return;
    }

    this.assigning.set(true);
    this.actionError.set(null);
    this.actionMessage.set(null);

    const payload: AssignConstructionProviderPayload = {
      provider_id: +this.assignForm.provider_id,
      lot: this.assignForm.lot,
      amount_xof: this.assignForm.amount_xof || 0,
      scheduled_at: this.assignForm.scheduled_at || undefined,
    };

    this.admin.assignConstructionProvider(this.requestId, payload).subscribe({
      next: (mission) => {
        this.assigning.set(false);
        this.assignments.update((list) => [mission, ...list]);
        this.assignFormOpen.set(false);
        this.assignForm = { provider_id: 0, lot: 'gros_oeuvre', amount_xof: 0, scheduled_at: '' };
        this.actionMessage.set(
          `${mission.provider?.business_name ?? 'Prestataire'} affecté — mission ${mission.reference} créée.`,
        );
      },
      error: (error: HttpErrorResponse) => {
        this.assigning.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Libellé du lot d'une mission (la colonne `category` porte le lot ici). */
  protected lotLabel(category: string | null): string {
    return this.lots.find((lot) => lot.value === category)?.label ?? category ?? '—';
  }

  /** Classe CSS du badge de statut d'une mission. */
  protected missionClass(status: string | null): string {
    switch (status) {
      case 'terminee':
        return 'is-ok';
      case 'acceptee':
      case 'en_cours':
        return 'is-progress';
      case 'refusee':
      case 'annulee':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  // --- Devis : lecture & envoi -------------------------------------------------

  protected loadQuotes(): void {
    this.quotesLoading.set(true);
    this.admin.constructionQuotes(this.requestId).subscribe({
      next: (quotes) => {
        this.quotes.set(quotes);
        this.quotesLoading.set(false);
      },
      error: () => this.quotesLoading.set(false),
    });
  }

  /** Déplie / replie les lignes d'un devis. */
  protected toggleQuote(quote: ConstructionQuote): void {
    this.openQuoteId.update((id) => (id === quote.id ? null : quote.id));
  }

  /** Seul un brouillon s'envoie (le serveur refuse le reste). */
  protected canSend(quote: ConstructionQuote): boolean {
    return quote.status === 'brouillon';
  }

  /** Envoie le devis au client. */
  protected sendQuote(quote: ConstructionQuote): void {
    if (this.sendingQuoteId() !== null) return;

    this.sendingQuoteId.set(quote.id);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin.sendConstructionQuote(quote.id).subscribe({
      next: (updated) => {
        this.sendingQuoteId.set(null);
        this.quotes.update((list) => list.map((q) => (q.id === updated.id ? updated : q)));
        this.actionMessage.set(`Devis ${updated.reference} envoyé au client.`);
        // L'envoi fait passer le DOSSIER en « devis envoyé » : contrairement aux
        // jalons, l'en-tête de la fiche change → on la recharge.
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.sendingQuoteId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  // --- Devis : composition ----------------------------------------------------

  protected toggleComposer(): void {
    this.actionError.set(null);
    this.actionMessage.set(null);

    const opening = !this.composerOpen();
    this.composerOpen.set(opening);

    // À l'ouverture, une première ligne vide : un devis vide n'a pas de sens et
    // l'agent n'a pas à cliquer « ajouter » avant de pouvoir saisir.
    if (opening && this.composerLines().length === 0) {
      this.addComposerLine();
    }
  }

  protected addComposerLine(): void {
    this.composerLines.update((lines) => [
      ...lines,
      { lot: 'gros_oeuvre', label: '', unit: 'm2', quantity: 1, unit_price_xof: 0 },
    ]);
  }

  protected removeComposerLine(index: number): void {
    this.composerLines.update((lines) => lines.filter((_, i) => i !== index));
  }

  /**
   * Met à jour un champ d'une ligne. Les signaux exigent un nouveau tableau : on
   * ne mute pas la ligne en place, sinon `composerSubtotal` ne se recalculerait
   * pas.
   */
  protected updateComposerLine(index: number, patch: Partial<ComposeQuoteLine>): void {
    this.composerLines.update((lines) =>
      lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
    );
  }

  /** Montant d'une ligne (aperçu ; le serveur refait le calcul). */
  protected lineAmount(line: ComposeQuoteLine): number {
    return Math.round((line.quantity || 0) * (line.unit_price_xof || 0));
  }

  /**
   * Marge de l'aperçu, ou `null` quand le taux est laissé vide : dans ce cas
   * c'est le réglage `build.margin_rate` du back-office qui s'appliquera, et on
   * ne le connaît pas ici — `GET /admin/settings` exige `gerer:parametres`, qu'un
   * agent chantier n'a pas. Mieux vaut ne rien afficher qu'un chiffre faux.
   */
  protected composerMargin(): number | null {
    if (this.composerMarginRate === null || this.composerMarginRate === undefined) return null;
    return Math.round((this.composerSubtotal() * this.composerMarginRate) / 100);
  }

  protected composerTotal(): number | null {
    const margin = this.composerMargin();
    return margin === null ? null : this.composerSubtotal() + margin;
  }

  /** Chiffre le devis. */
  protected composeQuote(): void {
    const lines = this.composerLines();

    if (!lines.length) {
      this.actionError.set('Un devis doit contenir au moins une ligne.');
      return;
    }
    if (lines.some((line) => !line.quantity || line.quantity <= 0)) {
      this.actionError.set('Chaque ligne demande une quantité supérieure à zéro.');
      return;
    }

    this.composing.set(true);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin
      .composeConstructionQuote(this.requestId, {
        lines: lines.map((line) => ({
          ...line,
          label: line.label?.trim() || undefined,
          unit: line.unit?.trim() || undefined,
        })),
        margin_rate: this.composerMarginRate ?? undefined,
        valid_until: this.composerValidUntil || undefined,
      })
      .subscribe({
        next: (quote) => {
          this.composing.set(false);
          this.quotes.update((list) => [quote, ...list]);
          this.composerLines.set([]);
          this.composerMarginRate = null;
          this.composerValidUntil = '';
          this.composerOpen.set(false);
          this.openQuoteId.set(quote.id);
          this.actionMessage.set(
            `Devis ${quote.reference} chiffré (${this.money(quote.total_xof)}). Il reste à l’envoyer au client.`,
          );
          // Le chiffrage fait passer le dossier « en étude » → en-tête à jour.
          this.load();
        },
        error: (error: HttpErrorResponse) => {
          this.composing.set(false);
          this.actionError.set(this.messageFor(error));
        },
      });
  }

  /** Classe CSS du badge de statut d'un devis. */
  protected quoteClass(status: string | null): string {
    switch (status) {
      case 'accepte':
        return 'is-ok';
      case 'envoye':
        return 'is-progress';
      case 'refuse':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  // --- Jalons : faire avancer -------------------------------------------------

  /** Démarre une étape (à venir → en cours). */
  protected startMilestone(milestone: ConstructionMilestone): void {
    this.writeMilestone(milestone, { status: 'en_cours' }, 'Étape démarrée.');
  }

  /**
   * Achève une étape. La date de réalisation est laissée au serveur (aujourd'hui
   * par défaut) : la saisir ici dupliquerait une règle déjà tenue côté API.
   */
  protected finishMilestone(milestone: ConstructionMilestone): void {
    this.writeMilestone(milestone, { status: 'termine' }, 'Étape achevée.');
  }

  /** Rouvre une étape achevée (le serveur efface sa date de réalisation). */
  protected reopenMilestone(milestone: ConstructionMilestone): void {
    this.writeMilestone(milestone, { status: 'en_cours' }, 'Étape rouverte.');
  }

  // --- Jalons : replanifier ---------------------------------------------------

  /** Ouvre (ou referme) le panneau de replanification d'un jalon. */
  protected toggleMilestoneEdit(milestone: ConstructionMilestone): void {
    this.actionError.set(null);
    this.actionMessage.set(null);

    if (this.editingMilestoneId() === milestone.id) {
      this.editingMilestoneId.set(null);
      return;
    }

    // Les `<input type="date">` veulent un `YYYY-MM-DD` : on tronque l'ISO reçu.
    this.milestoneEdit = {
      name: milestone.name,
      planned_date: (milestone.planned_date ?? '').slice(0, 10),
      actual_date: (milestone.actual_date ?? '').slice(0, 10),
    };
    this.editingMilestoneId.set(milestone.id);
  }

  /** Enregistre le nom et les dates saisis dans le panneau. */
  protected saveMilestoneEdit(milestone: ConstructionMilestone): void {
    const name = this.milestoneEdit.name.trim();
    if (!name) {
      this.actionError.set('Un jalon a besoin d’un nom.');
      return;
    }

    this.writeMilestone(
      milestone,
      {
        name,
        // Champ vidé = date retirée : on envoie `null`, pas la chaîne vide.
        planned_date: this.milestoneEdit.planned_date || null,
        actual_date: this.milestoneEdit.actual_date || null,
      },
      'Jalon replanifié.',
      () => this.editingMilestoneId.set(null),
    );
  }

  protected toggleMilestoneForm(): void {
    this.actionError.set(null);
    this.actionMessage.set(null);
    this.milestoneFormOpen.update((open) => !open);
  }

  /** Ajoute un jalon en fin de planning (position calculée par le serveur). */
  protected addMilestone(): void {
    const name = this.milestoneForm.name.trim();
    if (!name) {
      this.actionError.set('Un jalon a besoin d’un nom.');
      return;
    }

    this.addingMilestone.set(true);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin
      .addMilestone(this.requestId, {
        name,
        planned_date: this.milestoneForm.planned_date || undefined,
      })
      .subscribe({
        next: (created) => {
          this.addingMilestone.set(false);
          this.patchMilestones((list) => [...list, created]);
          this.milestoneForm = { name: '', planned_date: '' };
          this.milestoneFormOpen.set(false);
          this.actionMessage.set('Jalon ajouté au planning.');
        },
        error: (error: HttpErrorResponse) => {
          this.addingMilestone.set(false);
          this.actionError.set(this.messageFor(error));
        },
      });
  }

  /**
   * Déplace un jalon d'un cran. On envoie la liste ordonnée complète : échanger
   * deux positions en deux requêtes créerait un doublon transitoire, et un ordre
   * indéterminé si la seconde échouait.
   */
  protected moveMilestone(milestone: ConstructionMilestone, direction: -1 | 1): void {
    const list = [...(this.dossier()?.milestones ?? [])];
    const from = list.findIndex((item) => item.id === milestone.id);
    const to = from + direction;
    if (from < 0 || to < 0 || to >= list.length) return;

    [list[from], list[to]] = [list[to], list[from]];

    this.busyMilestoneId.set(milestone.id);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin.reorderMilestones(this.requestId, list.map((item) => item.id)).subscribe({
      next: (ordered) => {
        this.busyMilestoneId.set(null);
        // Le serveur renvoie le planning réordonné : on le prend tel quel plutôt
        // que de faire confiance à notre permutation locale.
        this.patchMilestones(() => ordered);
      },
      error: (error: HttpErrorResponse) => {
        this.busyMilestoneId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Retire un jalon du planning, après confirmation. */
  protected removeMilestone(milestone: ConstructionMilestone): void {
    if (!confirm(`Retirer le jalon « ${milestone.name} » du planning ?`)) return;

    this.busyMilestoneId.set(milestone.id);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin.deleteMilestone(milestone.id).subscribe({
      next: () => {
        this.busyMilestoneId.set(null);
        this.editingMilestoneId.set(null);
        this.patchMilestones((list) => list.filter((item) => item.id !== milestone.id));
        this.actionMessage.set('Jalon retiré.');
      },
      error: (error: HttpErrorResponse) => {
        this.busyMilestoneId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Écriture sur un jalon + remplacement de la ligne par la version serveur. */
  private writeMilestone(
    milestone: ConstructionMilestone,
    payload: MilestonePayload,
    done: string,
    after?: () => void,
  ): void {
    if (this.busyMilestoneId() !== null) return;

    this.busyMilestoneId.set(milestone.id);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.admin.updateMilestone(milestone.id, payload).subscribe({
      next: (updated) => {
        this.busyMilestoneId.set(null);
        this.patchMilestones((list) =>
          list.map((item) => (item.id === updated.id ? updated : item)),
        );
        this.actionMessage.set(done);
        after?.();
      },
      error: (error: HttpErrorResponse) => {
        this.busyMilestoneId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /**
   * Remplace les jalons du dossier. La jauge d'avancement étant un `computed`
   * sur ce signal, elle se met à jour d'elle-même.
   */
  private patchMilestones(
    change: (list: ConstructionMilestone[]) => ConstructionMilestone[],
  ): void {
    this.dossier.update((dossier) =>
      dossier ? { ...dossier, milestones: change(dossier.milestones ?? []) } : dossier,
    );
  }

  // --- Présentation -----------------------------------------------------------

  protected money(amount: number | null | undefined): string {
    if (amount === null || amount === undefined) return '—';
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
  }

  protected shortDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  /**
   * Écart entre le budget annoncé par le client et le coût estimé par le
   * simulateur. C'est le premier signal à voir sur un dossier : un projet
   * sous-budgété part mal.
   */
  protected budgetGap(dossier: ConstructionDossier): number | null {
    if (!dossier.budget_xof || !dossier.estimated_cost_xof) return null;
    return dossier.budget_xof - dossier.estimated_cost_xof;
  }

  protected statusClass(status: string | null): string {
    switch (status) {
      case 'terminee':
        return 'is-ok';
      case 'en_chantier':
      case 'acceptee':
        return 'is-progress';
      case 'annulee':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  protected milestoneClass(status: string | null): string {
    switch (status) {
      case 'termine':
        return 'is-done';
      case 'en_cours':
        return 'is-current';
      default:
        return 'is-todo';
    }
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      // Vaut pour la publication d'un compte rendu comme pour le pilotage des
      // jalons : les deux exigent la permission `gerer:chantiers`.
      return 'Action réservée aux comptes disposant du droit « chantiers ».';
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
      return first ?? body?.message ?? 'Données invalides.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
