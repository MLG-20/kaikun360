import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { CatalogService } from '../../../core/api/catalog.service';
import { RequestService } from '../../../core/api/request.service';
import { ReviewService, ReviewList } from '../../../core/api/review.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { Experience, ExperienceAvailability } from '../../../models/experience.model';
import { ReviewsComponent } from '../../../shared/components/reviews/reviews';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';
import { DetailLayoutComponent } from '../../../shared/components/detail-layout/detail-layout';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Fiche détaillée d'une expérience touristique (F2.4) — route `/tourisme/:id`.
 *
 * Charge en parallèle l'expérience, sa disponibilité
 * (`GET /experiences/{id}/availability` → places restantes) et ses avis publiés.
 * Présente le programme (durée, destination, inclusions), un indicateur de
 * places restantes, la note moyenne, puis un formulaire de demande de
 * réservation (`POST /requests`, service_type = explore). La réservation ferme
 * (places décomptées + paiement) relève des phases ultérieures ; ici
 * l'utilisateur exprime son besoin, qu'un conseiller confirme.
 */
@Component({
  selector: 'app-experience-detail-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    ReviewsComponent,
    WhatsAppButtonComponent,
    DetailLayoutComponent,
  ],
  templateUrl: './experience-detail-page.html',
  styleUrl: './experience-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ExperienceDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly reviewsApi = inject(ReviewService);
  private readonly requests = inject(RequestService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  private readonly id = toSignal(this.route.paramMap.pipe(map((p) => p.get('id'))));

  readonly state = signal<LoadState>('loading');
  readonly experience = signal<Experience | null>(null);
  /** Places restantes (issu de l'endpoint de disponibilité, null si indisponible). */
  readonly availability = signal<ExperienceAvailability | null>(null);
  readonly reviews = signal<ReviewList | null>(null);

  readonly isAuthenticated = this.auth.isAuthenticated;
  readonly loginQueryParams = computed(() => ({ redirect: `/tourisme/${this.id() ?? ''}` }));

  /**
   * Charge expérience + disponibilité + avis en une passe. Disponibilité et
   * avis sont tolérants à l'échec (repli sur vide) : seule l'absence de
   * l'expérience elle-même bascule en « introuvable ».
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((p) => p.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.experience.set(null);
        this.availability.set(null);
        this.reviews.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.catalog.experience(id).pipe(
          switchMap((env) => {
            this.experience.set(env.data);
            this.state.set('ready');
            this.prefillMessage(env.data);
            return forkJoin({
              availability: this.catalog.experienceAvailability(id).pipe(
                map((a) => a.data),
                catchError(() => of<ExperienceAvailability | null>(null)),
              ),
              reviews: this.reviewsApi.forEntity('experience', id).pipe(
                map((r) => r.data),
                catchError(() => of<ReviewList | null>(null)),
              ),
            });
          }),
          tap((extra) => {
            if (extra) {
              this.availability.set(extra.availability);
              this.reviews.set(extra.reviews);
            }
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );

  // --- Dérivés d'affichage --------------------------------------------------
  readonly priceLabel = computed(() => formatFcfa(this.experience()?.price_xof));

  /** Libellés lisibles de quelques clés d'inclusion connues. */
  private static readonly INCLUSION_LABELS: Record<string, string> = {
    restauration: 'Restauration',
    guide: 'Guide',
    transport: 'Transport',
    hebergement: 'Hébergement',
    assurance: 'Assurance',
  };

  /**
   * Liste des inclusions effectivement fournies (valeur vraie), avec un libellé
   * lisible. Les inclusions sont un objet clé→booléen ; un tableau vide (aucune
   * inclusion) est toléré.
   */
  readonly inclusionList = computed<string[]>(() => {
    const inclusions = this.experience()?.inclusions;
    if (!inclusions || Array.isArray(inclusions)) {
      return [];
    }
    return Object.entries(inclusions)
      .filter(([, included]) => included)
      .map(([key]) => ExperienceDetailPageComponent.INCLUSION_LABELS[key] ?? this.humanize(key));
  });

  /** Transforme une clé technique en libellé lisible (repli). */
  private humanize(key: string): string {
    const spaced = key.replace(/[_-]+/g, ' ');
    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
  }

  /** Vrai s'il ne reste plus aucune place (réservation complète). */
  readonly soldOut = computed(() => this.availability()?.seats_left === 0);

  // --- Formulaire de demande de réservation ---------------------------------
  readonly form = this.fb.nonNullable.group({
    message: ['', [Validators.required, Validators.maxLength(2000)]],
    seats: [1, [Validators.min(1)]],
    preferred_date: [''],
  });

  readonly submitting = signal(false);
  readonly createdReference = signal<string | null>(null);
  readonly formError = signal<string | null>(null);

  private prefillMessage(experience: Experience): void {
    this.form.controls.message.setValue(
      `Bonjour, je souhaite réserver l'expérience « ${experience.title} » à ${experience.destination}. Merci de me confirmer les disponibilités.`,
    );
  }

  /** Dépose la demande de réservation (POST /requests, service_type = explore). */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const experience = this.experience();
    if (!experience) {
      return;
    }

    // Nombre de participants et date souhaitée fusionnés dans le message :
    // l'API générique de demande ne porte pas de champ dédié.
    const raw = this.form.getRawValue();
    const extras: string[] = [];
    if (raw.seats) {
      extras.push(`Participants : ${raw.seats}.`);
    }
    if (raw.preferred_date) {
      extras.push(`Date souhaitée : ${raw.preferred_date}.`);
    }
    const message = extras.length ? `${raw.message}\n\n${extras.join(' ')}` : raw.message;

    this.submitting.set(true);
    this.formError.set(null);

    this.requests
      .create({
        service_type: 'explore',
        message,
        city: experience.destination,
      })
      .subscribe({
        next: (env) => {
          this.submitting.set(false);
          this.createdReference.set(env.data.request.reference);
        },
        error: (err: { status?: number; error?: ValidationErrorBody }) => {
          this.submitting.set(false);
          const firstError = err?.error?.errors
            ? Object.values(err.error.errors)[0]?.[0]
            : null;
          this.formError.set(
            firstError ?? "Votre demande n'a pas pu être envoyée. Réessayez.",
          );
        },
      });
  }
}
