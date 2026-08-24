import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import {
  AdminService,
  QueueEntryDetail,
  ValidationDecision,
  ValidationType,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { MediaReviewComponent } from '../../shared/media-review/media-review';

/** Libellé lisible de chaque type validable. */
const TYPE_LABELS: Record<ValidationType, string> = {
  property: 'Bien immobilier',
  vehicle: 'Véhicule',
  mobility_service: 'Départ programmé',
  experience: 'Expérience touristique',
  provider: 'Prestataire',
  provider_category: 'Catégorie prestataire',
};

/**
 * **Dossier de validation** (F8.1) — la revue complète avant publication.
 *
 * La file de validation permet de trancher vite les cas évidents. Cet écran
 * sert aux cas douteux : il montre la galerie ENTIÈRE (médias masqués compris),
 * les caractéristiques du type et le déposant, et permet d'**écarter une photo
 * précise** plutôt que de refuser toute l'annonce et renvoyer le déposant à
 * zéro.
 *
 * Reste consultable après décision : un agent doit pouvoir rouvrir un dossier
 * qu'il vient de trancher pour vérifier son geste — les boutons d'action
 * disparaissent alors, `is_pending` faisant foi.
 */
@Component({
  selector: 'app-backoffice-validation-detail-page',
  imports: [FormsModule, RouterLink, MediaReviewComponent],
  templateUrl: './backoffice-validation-detail-page.html',
  styleUrl: './backoffice-validation-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeValidationDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  /** Type et identifiant lus dans l'URL. */
  private readonly type = this.route.snapshot.paramMap.get('type') as ValidationType;
  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly entry = signal<QueueEntryDetail | null>(null);
  /** Faux si l'élément a déjà été tranché : on affiche sans permettre d'agir. */
  protected readonly isPending = signal(false);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Décision en cours (verrouille les boutons). */
  protected readonly processing = signal(false);
  /** Champ de motif de refus déplié. */
  protected readonly rejecting = signal(false);
  protected reason = '';
  protected readonly actionError = signal<string | null>(null);

  /** Libellé du type, pour le fil d'ariane et le titre. */
  protected readonly typeLabel = computed(() => TYPE_LABELS[this.type] ?? this.type);

  /**
   * Caractéristiques à contrôler, en paires libellé/valeur.
   *
   * Les libellés viennent du serveur (chaque validateur décide de ce qui
   * compte pour son type) : on n'en réécrit aucun ici. Les valeurs vides sont
   * écartées pour ne pas noyer l'agent sous des tirets.
   */
  protected readonly fields = computed(() => {
    const raw = this.entry()?.fields ?? {};
    return Object.entries(raw)
      .filter(([, value]) => value !== null && value !== '' && value !== undefined)
      .map(([label, value]) => ({ label, value: String(value) }));
  });

  constructor() {
    this.load();
  }

  /** Charge le dossier complet. */
  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.validationDetail(this.type, this.id).subscribe({
      next: (data) => {
        this.entry.set(data.entry);
        this.isPending.set(data.is_pending);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /**
   * Recharge après une modération de média.
   *
   * Le composant de galerie met déjà à jour sa vignette ; on recharge pour que
   * les COMPTEURS du dossier (« 2 masqué(s) ») restent exacts.
   */
  protected onModerated(): void {
    this.load();
  }

  protected approve(): void {
    this.decide('approve');
  }

  protected reject(): void {
    this.decide('reject', this.reason.trim() || undefined);
  }

  /** Envoie la décision puis revient à la file, le dossier étant traité. */
  private decide(decision: ValidationDecision, reason?: string): void {
    if (this.processing()) return;

    this.processing.set(true);
    this.actionError.set(null);

    this.admin.decide(this.type, this.id, decision, reason).subscribe({
      next: () => {
        this.processing.set(false);
        void this.router.navigate(['/back-office', 'validation']);
      },
      error: (error: HttpErrorResponse) => {
        this.processing.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Date de soumission (jour + heure courts). */
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
