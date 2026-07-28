import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import {
  AdminService,
  CreateReportPayload,
  DiasporaPriority,
  DiasporaProject,
  DiasporaReport,
  DiasporaStatus,
  ReportType,
  TeamMember,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';

/** Option générique d'un menu déroulant (valeur + libellé). */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * **Fiche d'un dossier diaspora** (F7.2.i) — `/back-office/diaspora/:id`.
 *
 * Alimentée par `GET /diaspora-projects/{id}` (+ `…/reports`). Le back-office y
 * pilote le dossier de bout en bout (CDC §6 *Diaspora*) :
 *   - **Priorité** (`PATCH …` — sans effet de bord) : hisser un dossier à forte
 *     valeur (stratégique / haute / normale) ;
 *   - **Agent dédié** (`PATCH …/assign`) : affecter explicitement ou laisser le
 *     système choisir l'agent le moins chargé (bascule « en cours ») ;
 *   - **Statut** (`PATCH …`) : faire progresser / clôturer / annuler ;
 *   - **Rapports de suivi** (`GET/POST …/reports`) : vérification terrain,
 *     avancement chantier, reporting (photo/vidéo/mixte + commentaire).
 *
 * Le pilotage exige d'être admin ou l'agent affecté (garde serveur `update`) ;
 * l'affectation d'un agent est réservée aux admins (`assign`).
 */
@Component({
  selector: 'app-backoffice-diaspora-detail-page',
  imports: [FormsModule],
  templateUrl: './backoffice-diaspora-detail-page.html',
  styleUrl: './backoffice-diaspora-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeDiasporaDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly project = signal<DiasporaProject | null>(null);
  protected readonly reports = signal<DiasporaReport[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Erreur d'une action de pilotage (priorité / agent / statut). */
  protected readonly actionError = signal<string | null>(null);
  /** Action en cours (verrouille les boutons). */
  protected readonly busy = signal(false);

  protected readonly isAdmin = computed(
    () => this.auth.hasRole('admin') || this.auth.hasRole('super_admin'),
  );
  /** Peut écrire (piloter statut/priorité, ajouter un rapport) : admin ou agent affecté. */
  protected readonly canWrite = computed(() => {
    const p = this.project();
    return this.isAdmin() || (!!p && p.agent_id === (this.auth.user()?.id ?? -1));
  });

  // --- Agents (pour l'affectation) --------------------------------------------
  protected readonly agents = signal<TeamMember[]>([]);
  protected selectedAgentId: number | 'auto' = 'auto';

  // --- Ajout d'un rapport -----------------------------------------------------
  protected reportType: ReportType = 'photo';
  protected reportDate = new Date().toISOString().slice(0, 10);
  protected reportComment = '';
  protected reportVideoUrl = '';
  protected readonly addingReport = signal(false);
  protected readonly reportError = signal<string | null>(null);

  protected readonly priorityOptions: readonly SelectOption[] = [
    { value: 'normale', label: 'Normale' },
    { value: 'haute', label: 'Haute' },
    { value: 'strategique', label: 'Stratégique' },
  ];

  protected readonly reportTypeOptions: readonly SelectOption[] = [
    { value: 'photo', label: 'Photos' },
    { value: 'video', label: 'Vidéo' },
    { value: 'mixte', label: 'Mixte (photos + vidéo)' },
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
    this.admin.diasporaProject(id).subscribe({
      next: (project) => {
        this.project.set(project);
        this.selectedAgentId = project.agent_id ?? 'auto';
        this.loading.set(false);
        this.loadReports(id);
        if (this.isAdmin() && this.agents().length === 0) this.loadAgents();
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  private loadReports(id: number): void {
    this.admin.diasporaReports(id).subscribe({
      next: (paginated) => this.reports.set(paginated.data),
      error: () => this.reports.set([]),
    });
  }

  private loadAgents(): void {
    this.admin.team({ role: 'agent_kaikun' }).subscribe({
      next: (paginated) => this.agents.set(paginated.data),
      error: () => this.agents.set([]),
    });
  }

  protected back(): void {
    void this.router.navigate(['/back-office', 'diaspora']);
  }

  // --- Pilotage ---------------------------------------------------------------

  /** Change la priorité (sans effet de bord). */
  protected setPriority(priority: DiasporaPriority): void {
    const p = this.project();
    if (!p || this.busy() || p.priority === priority) return;
    this.patch({ priority });
  }

  /** Fait progresser / clôture / annule le dossier. */
  protected setStatus(status: DiasporaStatus): void {
    const p = this.project();
    if (!p || this.busy() || p.status === status) return;
    this.patch({ status });
  }

  private patch(payload: { status?: DiasporaStatus; priority?: DiasporaPriority }): void {
    const p = this.project();
    if (!p) return;
    this.busy.set(true);
    this.actionError.set(null);
    this.admin.updateDiasporaProject(p.id, payload).subscribe({
      next: (updated) => {
        this.busy.set(false);
        this.project.set(updated);
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.actionError.set(this.messageFor(err));
      },
    });
  }

  /** Affecte l'agent choisi (ou auto si « auto »). */
  protected assignAgent(): void {
    const p = this.project();
    if (!p || this.busy()) return;
    this.busy.set(true);
    this.actionError.set(null);
    const payload = this.selectedAgentId === 'auto' ? {} : { agent_id: this.selectedAgentId };
    this.admin.assignDiasporaAgent(p.id, payload).subscribe({
      next: (updated) => {
        this.busy.set(false);
        this.project.set(updated);
        this.selectedAgentId = updated.agent_id ?? 'auto';
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.actionError.set(this.messageFor(err));
      },
    });
  }

  // --- Rapports ---------------------------------------------------------------

  protected addReport(): void {
    const p = this.project();
    if (!p || this.addingReport()) return;
    if (!this.reportDate) {
      this.reportError.set('Indiquez la date du rapport.');
      return;
    }
    this.addingReport.set(true);
    this.reportError.set(null);

    const payload: CreateReportPayload = {
      type: this.reportType,
      reported_at: this.reportDate,
      comment: this.reportComment.trim() || undefined,
      video_url: this.reportVideoUrl.trim() || undefined,
    };

    this.admin.addDiasporaReport(p.id, payload).subscribe({
      next: (report) => {
        this.addingReport.set(false);
        this.reports.update((list) => [report, ...list]);
        this.reportComment = '';
        this.reportVideoUrl = '';
      },
      error: (err: HttpErrorResponse) => {
        this.addingReport.set(false);
        this.reportError.set(this.messageFor(err));
      },
    });
  }

  // --- Présentation -----------------------------------------------------------

  protected priorityClass(priority: string | null): string {
    switch (priority) {
      case 'strategique':
        return 'is-strat';
      case 'haute':
        return 'is-high';
      default:
        return 'is-normal';
    }
  }

  protected statusClass(status: string | null): string {
    switch (status) {
      case 'termine':
        return 'is-ok';
      case 'en_cours':
        return 'is-info';
      case 'nouveau':
        return 'is-new';
      case 'annule':
        return 'is-off';
      default:
        return 'is-pending';
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
