import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { MediaReviewComponent } from '../shared/media-review/media-review';
import {
  AdminService,
  QueueEntry,
  ValidationDecision,
  ValidationQueueOverview,
  ValidationType,
} from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';

/** Métadonnées d'affichage d'un type validable (onglet + libellés). */
interface TypeMeta {
  key: ValidationType;
  /** Libellé pluriel de l'onglet (« Biens »). */
  label: string;
  /** Nom singulier pour les messages (« bien »). */
  singular: string;
}

/**
 * Écran **Validation** du back-office (F7.2.a) — le cœur du poste de commandement.
 *
 * Regroupe la file d'attente de TOUTES les ressources soumises à approbation
 * (biens, véhicules, expériences, prestataires) derrière l'endpoint générique
 * `GET /admin/queue`. L'agent choisit un type (onglet), parcourt les éléments en
 * attente et tranche : **valider** (publie/active) ou **refuser** (avec motif
 * facultatif), via `PATCH /admin/validate/{type}/{id}`.
 *
 * L'autorisation fine est côté serveur : un agent sans la permission d'un type
 * (`valider:bien`…) reçoit un 403, rendu lisible ici. La vue d'ensemble sert de
 * compteur par onglet ; la liste détaillée est paginée par type.
 */
@Component({
  selector: 'app-backoffice-validation-page',
  imports: [FormsModule, RouterLink, MediaReviewComponent],
  templateUrl: './backoffice-validation-page.html',
  styleUrl: './backoffice-validation-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeValidationPageComponent {
  private readonly admin = inject(AdminService);

  /** Ordre et libellés des onglets (aligné sur `ValidatorRegistry`). */
  protected readonly types: readonly TypeMeta[] = [
    { key: 'property', label: 'Biens', singular: 'bien' },
    { key: 'vehicle', label: 'Véhicules', singular: 'véhicule' },
    { key: 'experience', label: 'Expériences', singular: 'expérience' },
    { key: 'provider', label: 'Prestataires', singular: 'prestataire' },
  ];

  /** Vue d'ensemble (compteurs par type) — null tant que non chargée. */
  protected readonly overview = signal<ValidationQueueOverview | null>(null);
  protected readonly loadingOverview = signal(true);
  protected readonly overviewError = signal(false);

  /** Type actuellement affiché. */
  protected readonly selected = signal<ValidationType>('property');

  /** Liste paginée du type sélectionné. */
  protected readonly entries = signal<QueueEntry[]>([]);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loadingList = signal(false);
  protected readonly listError = signal(false);

  /** Élément en cours de traitement (approbation/refus) — verrouille ses boutons. */
  protected readonly processingId = signal<number | null>(null);
  /** Élément dont le champ « motif de refus » est ouvert. */
  protected readonly rejectingId = signal<number | null>(null);
  /** Motif de refus saisi (lié au champ ouvert). */
  protected reason = '';
  /** Message d'erreur d'une action (403/422/500), effacé au changement d'onglet. */
  protected readonly actionError = signal<string | null>(null);
  /** Confirmation éphémère après une décision (« Bien publié. »). */
  protected readonly actionDone = signal<string | null>(null);

  /** Métadonnées du type sélectionné (pour les libellés). */
  protected readonly currentMeta = computed(
    () => this.types.find((t) => t.key === this.selected()) ?? this.types[0],
  );

  constructor() {
    this.loadOverview();
  }

  /** Nombre d'éléments en attente d'un type (compteur d'onglet). */
  protected count(type: ValidationType): number {
    return this.overview()?.queue[type]?.count ?? 0;
  }

  /** Charge la vue d'ensemble puis ouvre le premier type non vide. */
  private loadOverview(): void {
    this.loadingOverview.set(true);
    this.overviewError.set(false);

    this.admin.validationQueue().subscribe({
      next: (data) => {
        this.overview.set(data);
        this.loadingOverview.set(false);
        // Ouvre d'emblée le premier onglet qui a des éléments à traiter.
        const firstBusy = this.types.find((t) => (data.queue[t.key]?.count ?? 0) > 0);
        this.select((firstBusy ?? this.types[0]).key);
      },
      error: () => {
        this.overviewError.set(true);
        this.loadingOverview.set(false);
      },
    });
  }

  /** Sélectionne un type et charge sa première page. */
  protected select(type: ValidationType): void {
    this.selected.set(type);
    this.page.set(1);
    this.rejectingId.set(null);
    this.actionError.set(null);
    this.actionDone.set(null);
    this.loadList();
  }

  /** Charge la page courante du type sélectionné. */
  protected loadList(): void {
    this.loadingList.set(true);
    this.listError.set(false);

    this.admin.validationQueueByType(this.selected(), this.page()).subscribe({
      next: (paginated) => {
        this.entries.set(paginated.data);
        this.lastPage.set(paginated.meta.last_page);
        this.loadingList.set(false);
      },
      error: () => {
        this.listError.set(true);
        this.loadingList.set(false);
      },
    });
  }

  /** Page précédente / suivante (bornée). */
  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.loadList();
  }

  /** Ouvre/ferme le champ de motif de refus d'une ligne. */
  protected toggleReject(entry: QueueEntry): void {
    this.actionError.set(null);
    if (this.rejectingId() === entry.id) {
      this.rejectingId.set(null);
    } else {
      this.rejectingId.set(entry.id);
      this.reason = '';
    }
  }

  /** Valide une ressource (publie / active). */
  protected approve(entry: QueueEntry): void {
    this.decide(entry, 'approve');
  }

  /** Refuse une ressource avec le motif saisi (facultatif). */
  protected reject(entry: QueueEntry): void {
    this.decide(entry, 'reject', this.reason.trim() || undefined);
  }

  /** Envoie la décision puis retire l'élément traité de la file. */
  private decide(entry: QueueEntry, decision: ValidationDecision, reason?: string): void {
    if (this.processingId() !== null) return;

    this.processingId.set(entry.id);
    this.actionError.set(null);
    this.actionDone.set(null);

    this.admin.decide(entry.type, entry.id, decision, reason).subscribe({
      next: () => {
        this.processingId.set(null);
        this.rejectingId.set(null);
        this.afterDecision(entry, decision);
      },
      error: (error: HttpErrorResponse) => {
        this.processingId.set(null);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Retire la ligne traitée, décrémente le compteur, recharge si la page se vide. */
  private afterDecision(entry: QueueEntry, decision: ValidationDecision): void {
    // Retire l'élément de la liste affichée.
    this.entries.update((list) => list.filter((e) => e.id !== entry.id));

    // Décrémente le compteur de l'onglet dans la vue d'ensemble.
    const current = this.overview();
    if (current) {
      const bucket = current.queue[entry.type];
      const nextCount = Math.max(0, (bucket?.count ?? 1) - 1);
      this.overview.set({
        ...current,
        total_pending: Math.max(0, current.total_pending - 1),
        queue: { ...current.queue, [entry.type]: { ...bucket, count: nextCount } },
      });
    }

    const verb = decision === 'approve' ? 'validé' : 'refusé';
    this.actionDone.set(`« ${entry.label} » ${verb}.`);

    // Si la page courante est vide mais qu'il reste des éléments, recharge.
    if (this.entries().length === 0 && this.count(entry.type) > 0) {
      // Revient d'une page si on était au-delà de la dernière.
      if (this.page() > 1) this.page.update((p) => p - 1);
      this.loadList();
    }
  }

  /**
   * Lien vers le dossier complet d'un élément (F8.1).
   *
   * La file suffit pour un cas évident ; dès qu'il y a un doute, l'agent ouvre
   * le dossier pour voir toute la galerie, masquer une photo au besoin et lire
   * les caractéristiques avant de publier sur le site vitrine.
   */
  protected detailLink(entry: QueueEntry): unknown[] {
    return ['/back-office', 'validation', entry.type, entry.id];
  }

  /** Formate une date de soumission (jour + heure courts). */
  protected submittedAt(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      return "Vous n'avez pas le droit de valider ce type. Demandez la délégation à un administrateur.";
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Cet élément a déjà été traité.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
