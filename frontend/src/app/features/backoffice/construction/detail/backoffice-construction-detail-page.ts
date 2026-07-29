import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import {
  AdminService,
  ConstructionDossier,
  ConstructionMilestone,
  ConstructionReport,
  MilestonePayload,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';

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
 * ⚠️ Restent hors périmètre de cette tranche : les **devis** (F7.3.e2) et
 * l'**affectation de prestataires BTP** (F7.3.e3).
 */
@Component({
  selector: 'app-backoffice-construction-detail-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './backoffice-construction-detail-page.html',
  styleUrl: './backoffice-construction-detail-page.scss',
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
