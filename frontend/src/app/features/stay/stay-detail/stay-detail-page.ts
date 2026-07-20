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
import { Stay, BookedRange } from '../../../models/stay.model';
import { ReviewsComponent } from '../../../shared/components/reviews/reviews';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';
import { DetailLayoutComponent } from '../../../shared/components/detail-layout/detail-layout';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/** Une case du calendrier (jour du mois affiché, ou vide pour l'alignement). */
interface CalendarCell {
  iso: string | null;
  day: number | null;
  booked: boolean;
  past: boolean;
}

/**
 * Fiche détaillée d'une nuitée (F2.3) — route `/nuitees/:id`.
 *
 * Charge en parallèle la nuitée, sa disponibilité (`GET /stays/{id}/availability`)
 * et ses avis publiés. Présente équipements, règles, tarifs, un **calendrier de
 * disponibilité** (jours réservés grisés) et un formulaire de demande de
 * réservation (`POST /requests`, service_type = stay). La réservation ferme
 * (paiement + caution) relève des phases ultérieures ; ici l'utilisateur
 * exprime son besoin de dates, qu'un conseiller confirme.
 */
@Component({
  selector: 'app-stay-detail-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    ReviewsComponent,
    WhatsAppButtonComponent,
    DetailLayoutComponent,
  ],
  templateUrl: './stay-detail-page.html',
  styleUrl: './stay-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StayDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly reviewsApi = inject(ReviewService);
  private readonly requests = inject(RequestService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  private readonly id = toSignal(this.route.paramMap.pipe(map((p) => p.get('id'))));

  readonly state = signal<LoadState>('loading');
  readonly stay = signal<Stay | null>(null);
  /** Ensemble des jours réservés (ISO `YYYY-MM-DD`), pour griser le calendrier. */
  private readonly bookedDays = signal<Set<string>>(new Set());
  readonly reviews = signal<ReviewList | null>(null);

  readonly isAuthenticated = this.auth.isAuthenticated;
  readonly loginQueryParams = computed(() => ({ redirect: `/nuitees/${this.id() ?? ''}` }));

  /**
   * Charge nuitée + disponibilité + avis en une passe. La disponibilité et les
   * avis sont tolérants à l'échec (repli sur vide) : seule l'absence de la
   * nuitée elle-même bascule en « introuvable ».
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((p) => p.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.stay.set(null);
        this.bookedDays.set(new Set());
        this.reviews.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.catalog.stay(id).pipe(
          switchMap((env) => {
            this.stay.set(env.data);
            this.state.set('ready');
            this.prefillMessage(env.data);
            // Disponibilité + avis en parallèle, chacun résilient.
            return forkJoin({
              availability: this.catalog.stayAvailability(id).pipe(
                map((a) => a.data.booked),
                catchError(() => of<BookedRange[]>([])),
              ),
              reviews: this.reviewsApi.forEntity('stay', id).pipe(
                map((r) => r.data),
                catchError(() => of<ReviewList | null>(null)),
              ),
            });
          }),
          tap((extra) => {
            if (extra) {
              this.bookedDays.set(this.expandRanges(extra.availability));
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
  readonly property = computed(() => this.stay()?.property ?? null);

  /**
   * URLs des photos pour la galerie : une nuitée est illustrée par les photos de
   * SON bien (couverture en tête, ordre donné par l'API). Liste vide → galerie
   * masquée d'elle-même.
   */
  readonly photoUrls = computed(
    () =>
      this.property()
        ?.photos?.map((photo) => photo.url)
        .filter((url): url is string => !!url) ?? [],
  );

  readonly verified = computed(() => {
    const level = this.property()?.verification_level;
    return !!level && level !== 'unverified' && level !== 'aucun';
  });

  readonly priceLabel = computed(() => formatFcfa(this.stay()?.price_per_night_xof));
  readonly cautionLabel = computed(() => formatFcfa(this.stay()?.caution_xof));

  readonly locationLabel = computed(() => {
    const loc = this.property()?.location;
    if (!loc) {
      return null;
    }
    return [loc.commune, loc.department, loc.region].filter(Boolean).join(' · ') || null;
  });

  // --- Calendrier de disponibilité -----------------------------------------
  /** Décalage en mois par rapport au mois courant (0 = mois affiché initial). */
  private readonly monthOffset = signal(0);

  private readonly viewMonth = computed(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth() + this.monthOffset(), 1);
  });

  /** Libellé « juillet 2026 » du mois affiché. */
  readonly monthLabel = computed(() =>
    this.viewMonth().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }),
  );

  /** On n'autorise pas la navigation dans le passé. */
  readonly canGoPrev = computed(() => this.monthOffset() > 0);

  /** Grille du mois affiché : semaines de 7 cases (lundi → dimanche). */
  readonly calendar = computed<CalendarCell[][]>(() => {
    const first = this.viewMonth();
    const year = first.getFullYear();
    const month = first.getMonth();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    // getDay : 0 = dimanche → on ramène à une semaine commençant le lundi.
    const startWeekday = (first.getDay() + 6) % 7;

    const todayIso = this.toIso(new Date());
    const booked = this.bookedDays();

    const cells: CalendarCell[] = [];
    for (let i = 0; i < startWeekday; i++) {
      cells.push({ iso: null, day: null, booked: false, past: false });
    }
    for (let day = 1; day <= daysInMonth; day++) {
      const iso = this.toIso(new Date(year, month, day));
      cells.push({
        iso,
        day,
        booked: booked.has(iso),
        past: iso < todayIso,
      });
    }
    // Découpe en semaines de 7.
    const weeks: CalendarCell[][] = [];
    for (let i = 0; i < cells.length; i += 7) {
      weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
  });

  readonly weekDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

  prevMonth(): void {
    if (this.canGoPrev()) {
      this.monthOffset.update((m) => m - 1);
    }
  }

  nextMonth(): void {
    this.monthOffset.update((m) => m + 1);
  }

  /**
   * Étale les intervalles réservés en un ensemble de jours occupés. Le jour de
   * départ (`end_date`) reste disponible (départ le matin) → borne exclue.
   */
  private expandRanges(ranges: BookedRange[]): Set<string> {
    const days = new Set<string>();
    for (const range of ranges) {
      const cursor = new Date(range.start_date);
      const end = new Date(range.end_date);
      while (cursor < end) {
        days.add(this.toIso(cursor));
        cursor.setDate(cursor.getDate() + 1);
      }
    }
    return days;
  }

  private toIso(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  // --- Formulaire de demande de réservation ---------------------------------
  readonly form = this.fb.nonNullable.group({
    message: ['', [Validators.required, Validators.maxLength(2000)]],
    arrival: [''],
    departure: [''],
  });

  readonly submitting = signal(false);
  readonly createdReference = signal<string | null>(null);
  readonly formError = signal<string | null>(null);

  private prefillMessage(stay: Stay): void {
    const title = stay.property?.title ?? 'cette nuitée';
    this.form.controls.message.setValue(
      `Bonjour, je souhaite réserver « ${title} ». Merci de me confirmer la disponibilité.`,
    );
  }

  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const stay = this.stay();
    if (!stay) {
      return;
    }

    const raw = this.form.getRawValue();
    const dates =
      raw.arrival || raw.departure
        ? `\n\nSéjour souhaité : du ${raw.arrival || '?'} au ${raw.departure || '?'}.`
        : '';

    this.submitting.set(true);
    this.formError.set(null);

    this.requests
      .create({
        service_type: 'stay',
        message: `${raw.message}${dates}`,
        city: stay.property?.location?.commune ?? null,
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
