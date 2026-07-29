import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import {
  AdminService,
  ConstructionDossier,
  ConstructionReport,
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
 *  - **l'avancement** : les jalons du chantier, dans l'ordre, avec dates prévue
 *    et réelle ;
 *  - **les comptes rendus** : photos / vidéos datées et commentées, avec
 *    **dépôt** d'un nouveau compte rendu (`gerer:chantiers`).
 *
 * ⚠️ **Les jalons sont en LECTURE SEULE** : ils sont semés à la création de la
 * demande, mais **aucun endpoint ne permet de les faire avancer** — c'est un
 * trou backend identifié à l'audit CDC, pas un oubli d'écran. Idem pour les
 * devis et l'affectation de prestataires BTP. L'écran le dit explicitement
 * plutôt que d'afficher un planning qu'on croirait pilotable.
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
      return 'Publication réservée aux comptes disposant du droit « chantiers ».';
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
      return first ?? body?.message ?? 'Données invalides.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
