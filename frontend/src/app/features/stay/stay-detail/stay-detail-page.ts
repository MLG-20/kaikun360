import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { CatalogService } from '../../../core/api/catalog.service';
import { BookingService } from '../../../core/api/booking.service';
import { ReviewService, ReviewList } from '../../../core/api/review.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { schemaFilAriane, schemaOffre } from '../../../core/seo/json-ld';
import { SeoService } from '../../../core/seo/seo.service';
import { BookingIntentStore } from '../../../core/state/booking-intent-store';
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
 * disponibilité** (jours réservés grisés) et le formulaire de
 * **réservation ferme** (`POST /stays/{id}/bookings`, F8.10).
 *
 * ⚠️ Cette page ne proposait qu'une *demande* (`POST /requests`) : le visiteur
 * croyait avoir réservé, et « Mes réservations » lui répondait qu'il n'avait
 * rien réservé. Le bouton crée désormais un vrai séjour — commission figée,
 * caution retenue, en attente de paiement — et emmène le client le régler.
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
  private readonly bookings = inject(BookingService);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);
  private readonly seo = inject(SeoService);
  /** Panier : garde la saisie du visiteur le temps qu'il se connecte (F8.13). */
  private readonly intents = inject(BookingIntentStore);

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
            this.referencer(env.data);
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

  /**
   * Affine les balises de référencement avec la nuitée chargée (F9.1).
   *
   * ⚠️ **Une nuitée n'a ni titre ni photo à elle** : elle décrit le mode
   * courte durée d'un BIEN, et c'est le bien qui porte le nom, la description,
   * la localisation et les photos. Chercher `stay.title` ne donnerait rien —
   * le champ n'existe pas.
   */
  private referencer(nuitee: Stay): void {
    const bien = nuitee.property ?? null;
    const lieu = [bien?.location?.commune, bien?.location?.region].filter(Boolean).join(', ') || null;
    const nom = bien?.title ?? 'Logement meublé';
    const description =
      bien?.description?.trim() ||
      `Logement meublé pour ${nuitee.capacity} personne${nuitee.capacity > 1 ? 's' : ''}${
        lieu ? ` à ${lieu}` : ''
      } — ${formatFcfa(nuitee.price_per_night_xof)} la nuit.`;

    this.seo.apply({
      title: [nom, lieu].filter(Boolean).join(' — '),
      description,
      type: 'product',
      canonicalPath: `/nuitees/${nuitee.id}`,
      image: bien?.photo_url ?? null,
    });
    this.seo.setJsonLd(
      'offre',
      schemaOffre({
        nom,
        description: bien?.description ?? null,
        image: bien?.photo_url,
        chemin: `/nuitees/${nuitee.id}`,
        prixXof: nuitee.price_per_night_xof,
        unite: 'nuit',
        lieu,
      }),
    );
    this.seo.setJsonLd(
      'ariane',
      schemaFilAriane([
        { nom: 'Accueil', chemin: '/' },
        { nom: 'Nuitées', chemin: '/nuitees' },
        { nom, chemin: `/nuitees/${nuitee.id}` },
      ]),
    );
  }

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

  // --- Réservation (F8.10) ---------------------------------------------------
  //
  // ⚠️ Ce bloc était un formulaire de DEMANDE (`POST /requests`) : le visiteur
  // cliquait « Demander une réservation », un prospect était créé, et il ne
  // trouvait rien dans « Mes réservations » — l'écran lui répondant même
  // « Aucune réservation : parcourez nos univers pour réserver », alors qu'il
  // venait de le faire. Le bouton produit désormais une VRAIE réservation.

  readonly form = this.fb.nonNullable.group({
    arrival: ['', [Validators.required]],
    departure: ['', [Validators.required]],
    guests: [1, [Validators.required, Validators.min(1)]],
  });

  /** Valeurs du formulaire suivies en signal, pour recalculer le devis en direct. */
  private readonly formValue = toSignal(this.form.valueChanges, {
    initialValue: this.form.getRawValue(),
  });

  readonly submitting = signal(false);
  readonly formError = signal<string | null>(null);

  constructor() {
    // F8.13 — reprise du panier : si le visiteur a saisi ses dates puis est allé
    // se connecter, il retrouve son formulaire tel qu'il l'avait laissé. Une
    // seule reprise par instance (`repris`) : le panier se consomme, revenir
    // plus tard sur la fiche ne doit pas ressusciter une saisie oubliée.
    let repris = false;
    effect(() => {
      const id = this.id();
      if (repris || !id) {
        return;
      }
      repris = true;
      const saisie = this.intents.take('stay', id);
      if (saisie) {
        this.form.patchValue({
          arrival: String(saisie['arrival'] ?? ''),
          departure: String(saisie['departure'] ?? ''),
          guests: Number(saisie['guests'] ?? 1),
        });
      }
    });
  }

  /** Aujourd'hui au format `YYYY-MM-DD` : borne `min` des deux champs de date. */
  readonly today = this.toIso(new Date());

  /**
   * Nombre de nuits. La date de départ est **exclue** — on ne dort pas la nuit
   * du départ. C'est exactement le calcul du serveur ; l'afficher évite au
   * client de découvrir le montant seulement à l'étape du paiement.
   */
  readonly nights = computed(() => {
    const { arrival, departure } = this.formValue();
    if (!arrival || !departure) {
      return 0;
    }
    const ecart = new Date(departure).getTime() - new Date(arrival).getTime();
    return ecart > 0 ? Math.round(ecart / 86_400_000) : 0;
  });

  /** Montant du séjour (hors caution, qui est un dépôt rendu, pas un revenu). */
  readonly total = computed(() => this.nights() * (this.stay()?.price_per_night_xof ?? 0));
  readonly totalLabel = computed(() => formatFcfa(this.total()));

  /**
   * Ce que le client doit savoir AVANT de cliquer : les règles que le serveur
   * appliquera. Les rejouer ici n'est pas une duplication de la validation —
   * le serveur reste seul juge, et refuse en 422 — c'est le refus qu'on évite.
   */
  readonly bookingHint = computed<string | null>(() => {
    const stay = this.stay();
    const nights = this.nights();
    if (!stay || nights === 0) {
      return null;
    }
    if (stay.min_nights && nights < stay.min_nights) {
      return `Séjour minimum : ${stay.min_nights} nuit(s).`;
    }
    if (stay.max_nights && nights > stay.max_nights) {
      return `Séjour maximum : ${stay.max_nights} nuit(s).`;
    }
    const guests = Number(this.formValue().guests ?? 0);
    if (guests > stay.capacity) {
      return `Ce logement accueille ${stay.capacity} personne(s) au maximum.`;
    }
    return null;
  });

  /** Le récapitulatif chiffré ne s'affiche que s'il a un sens. */
  readonly canQuote = computed(() => this.nights() > 0 && !this.bookingHint());

  /**
   * Réserve. Le succès ne renvoie pas un message : il **emmène le client
   * payer** — c'est la suite naturelle, et une réservation en attente de
   * paiement laissée sans indication ne se serait jamais soldée.
   */
  submit(): void {
    const stay = this.stay();
    if (this.submitting() || !stay) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();

    // Visiteur non connecté (F8.13) : on met sa saisie de côté et on l'emmène se
    // connecter — il revient sur cette fiche avec ses dates déjà en place. La
    // fiche ne lui cache plus son formulaire : on ne demande un compte qu'au
    // moment où il engage vraiment quelque chose.
    if (!this.isAuthenticated()) {
      this.intents.remember('stay', String(stay.id), {
        arrival: raw.arrival,
        departure: raw.departure,
        guests: Number(raw.guests),
      });
      void this.router.navigate(['/auth/connexion'], {
        queryParams: this.loginQueryParams(),
      });
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.bookings
      .createStayBooking(stay.id, {
        start_date: raw.arrival,
        end_date: raw.departure,
        guests: Number(raw.guests),
      })
      .subscribe({
        next: (booking) => {
          this.submitting.set(false);
          void this.router.navigate(['/mon-espace/reservations', booking.id, 'paiement']);
        },
        error: (err: { status?: number; error?: ValidationErrorBody & { message?: string } }) => {
          this.submitting.set(false);
          this.formError.set(this.messageFor(err));
        },
      });
  }

  /**
   * Les 422 du serveur sont déjà rédigés pour un client (« Ce créneau est déjà
   * réservé. », « Le séjour minimum est de 2 nuit(s). ») : on les affiche tels
   * quels plutôt que d'en écrire une version appauvrie.
   */
  private messageFor(err: {
    status?: number;
    error?: ValidationErrorBody & { message?: string };
  }): string {
    if (err?.status === 422) {
      const premier = err.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
      return premier ?? 'Ces dates ne peuvent pas être réservées.';
    }
    // Le compte existe mais n'est pas vérifié (middleware `verified.account`) :
    // on le dit franchement, avec la marche à suivre.
    if (err?.status === 403) {
      return 'Confirmez votre e-mail ou votre téléphone depuis votre profil pour pouvoir réserver.';
    }
    return "La réservation n'a pas pu être enregistrée. Réessayez.";
  }
}
