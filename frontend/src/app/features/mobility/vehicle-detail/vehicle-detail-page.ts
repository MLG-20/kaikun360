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
import { Vehicle } from '../../../models/vehicle.model';
import { ReviewsComponent } from '../../../shared/components/reviews/reviews';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';
import { DetailLayoutComponent } from '../../../shared/components/detail-layout/detail-layout';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Fiche détaillée d'un véhicule (F2.4) — route `/transport/:id`.
 *
 * Charge le véhicule via `CatalogService.vehicle(id)` (un véhicule non publié
 * renvoie 404 → « introuvable ») et ses avis publiés (résilients à l'échec).
 * Présente caractéristiques (type, capacité, chauffeur, caution), la note
 * moyenne, puis un formulaire de demande de réservation (`POST /requests`,
 * service_type = mobility). La réservation ferme (caution + commission) relève
 * des phases ultérieures ; ici l'utilisateur exprime son besoin de dates.
 */
@Component({
  selector: 'app-vehicle-detail-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    ReviewsComponent,
    WhatsAppButtonComponent,
    DetailLayoutComponent,
  ],
  templateUrl: './vehicle-detail-page.html',
  styleUrl: './vehicle-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehicleDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly reviewsApi = inject(ReviewService);
  private readonly requests = inject(RequestService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  private readonly id = toSignal(this.route.paramMap.pipe(map((p) => p.get('id'))));

  readonly state = signal<LoadState>('loading');
  readonly vehicle = signal<Vehicle | null>(null);
  readonly reviews = signal<ReviewList | null>(null);

  readonly isAuthenticated = this.auth.isAuthenticated;
  readonly loginQueryParams = computed(() => ({ redirect: `/transport/${this.id() ?? ''}` }));

  /**
   * Charge le véhicule puis ses avis. Les avis sont tolérants à l'échec (repli
   * sur null) ; seule l'absence du véhicule bascule en « introuvable ».
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((p) => p.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.vehicle.set(null);
        this.reviews.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.catalog.vehicle(id).pipe(
          switchMap((env) => {
            this.vehicle.set(env.data);
            this.state.set('ready');
            this.prefillMessage(env.data);
            return forkJoin({
              reviews: this.reviewsApi.forEntity('vehicle', id).pipe(
                map((r) => r.data),
                catchError(() => of<ReviewList | null>(null)),
              ),
            });
          }),
          tap((extra) => {
            if (extra) {
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
  /** Nom lisible : « Toyota Hiace » ou, à défaut, le type. */
  readonly displayName = computed(() => {
    const v = this.vehicle();
    if (!v) {
      return 'Véhicule';
    }
    const name = [v.brand, v.model].filter(Boolean).join(' ');
    return name || v.type_label || 'Véhicule';
  });

  readonly priceLabel = computed(() => formatFcfa(this.vehicle()?.price_per_day_xof));
  readonly cautionLabel = computed(() => formatFcfa(this.vehicle()?.caution_xof));


  // --- Formulaire de demande de réservation ---------------------------------
  readonly form = this.fb.nonNullable.group({
    message: ['', [Validators.required, Validators.maxLength(2000)]],
    start_date: [''],
    end_date: [''],
  });

  readonly submitting = signal(false);
  readonly createdReference = signal<string | null>(null);
  readonly formError = signal<string | null>(null);

  private prefillMessage(vehicle: Vehicle): void {
    const name = [vehicle.brand, vehicle.model].filter(Boolean).join(' ') || vehicle.type_label || 'ce véhicule';
    this.form.controls.message.setValue(
      `Bonjour, je souhaite réserver « ${name} »${vehicle.has_driver ? ' avec chauffeur' : ''}. Merci de me confirmer la disponibilité.`,
    );
  }

  /** Dépose la demande de réservation (POST /requests, service_type = mobility). */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const vehicle = this.vehicle();
    if (!vehicle) {
      return;
    }

    // Dates souhaitées fusionnées dans le message (l'API générique ne porte pas
    // de champ dédié ; le conseiller les lit dans le corps de la demande).
    const raw = this.form.getRawValue();
    const dates =
      raw.start_date || raw.end_date
        ? `\n\nPériode souhaitée : du ${raw.start_date || '?'} au ${raw.end_date || '?'}.`
        : '';

    this.submitting.set(true);
    this.formError.set(null);

    this.requests
      .create({
        service_type: 'mobility',
        message: `${raw.message}${dates}`,
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
