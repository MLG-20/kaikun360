import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  signal,
} from '@angular/core';
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
import { Experience, ExperienceAvailability } from '../../../models/experience.model';
import { ReviewsComponent } from '../../../shared/components/reviews/reviews';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';
import { DetailLayoutComponent } from '../../../shared/components/detail-layout/detail-layout';
import { GoogleMapEmbedComponent } from '../../../shared/components/google-map-embed/google-map-embed';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Fiche détaillée d'une expérience touristique (F2.4) — route `/tourisme/:id`.
 *
 * Charge en parallèle l'expérience, sa disponibilité
 * (`GET /experiences/{id}/availability` → places restantes) et ses avis publiés.
 * Présente le programme (durée, destination, inclusions), un indicateur de
 * places restantes, la note moyenne, puis le formulaire de
 * **réservation ferme** (`POST /experiences/{id}/bookings`, F8.10).
 *
 * ⚠️ Cette page ne déposait qu'une *demande*, avec une date « souhaitée »
 * facultative. Les places sont désormais réellement décomptées, et le client
 * est emmené payer. ⚠️ Un circuit n'a **pas de date de fin** : sa durée lui
 * appartient, le client ne choisit que son jour de départ.
 */
@Component({
  selector: 'app-experience-detail-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    ReviewsComponent,
    WhatsAppButtonComponent,
    DetailLayoutComponent,
    GoogleMapEmbedComponent,
  ],
  templateUrl: './experience-detail-page.html',
  styleUrl: './experience-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ExperienceDetailPageComponent {
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
  readonly experience = signal<Experience | null>(null);
  /** Places restantes (issu de l'endpoint de disponibilité, null si indisponible). */
  readonly availability = signal<ExperienceAvailability | null>(null);
  readonly reviews = signal<ReviewList | null>(null);

  readonly isAuthenticated = this.auth.isAuthenticated;
  readonly loginQueryParams = computed(() => ({ redirect: `/tourisme/${this.id() ?? ''}` }));

  /**
   * URLs de la galerie (F8.18) — les photos du circuit déposées par son
   * organisateur. La fiche montait sa galerie sans jamais l'alimenter.
   */
  readonly photoUrls = computed(
    () =>
      this.experience()
        ?.photos?.map((photo) => photo.url)
        .filter((url): url is string => !!url) ?? [],
  );

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
            this.referencer(env.data);
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

  /** Lien Google Maps collé par le prestataire (F5.10), ou `null`. */
  readonly mapLink = computed(() => this.experience()?.maps_link ?? null);

  /**
   * Affine les balises de référencement avec l'expérience chargée (F9.1).
   *
   * ⚠️ Le prix d'un circuit est **par personne** : l'annoncer comme un prix
   * global dans les données structurées afficherait un tarif de groupe dans les
   * résultats Google. D'où l'unité passée explicitement.
   */
  private referencer(experience: Experience): void {
    const duree = `${experience.duration_days} jour${experience.duration_days > 1 ? 's' : ''}`;
    const description =
      experience.description?.trim() ||
      `Circuit de ${duree} à ${experience.destination}, ${formatFcfa(
        experience.price_xof,
      )} par personne.`;

    this.seo.apply({
      title: `${experience.title} — ${experience.destination}`,
      description,
      type: 'product',
      canonicalPath: `/tourisme/${experience.id}`,
      image: experience.photo_url ?? null,
    });
    this.seo.setJsonLd(
      'offre',
      schemaOffre({
        nom: experience.title,
        description: experience.description,
        image: experience.photo_url,
        chemin: `/tourisme/${experience.id}`,
        prixXof: experience.price_xof,
        unite: 'personne',
        lieu: experience.destination,
      }),
    );
    this.seo.setJsonLd(
      'ariane',
      schemaFilAriane([
        { nom: 'Accueil', chemin: '/' },
        { nom: 'Tourisme', chemin: '/tourisme' },
        { nom: experience.title, chemin: `/tourisme/${experience.id}` },
      ]),
    );
  }

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

  // --- Réservation (F8.10) ---------------------------------------------------
  //
  // ⚠️ Ce bloc déposait une DEMANDE, avec la date « souhaitée » en champ
  // facultatif et le nombre de participants recopié dans un message. Le client
  // repartait avec une référence de suivi et rien à payer.
  //
  // ⚠️ Un circuit n'a pas de date de FIN : sa durée lui appartient. Le client
  // ne choisit que son jour de départ — c'est ce que le serveur attend
  // (`start_date` seul), et proposer une date de retour serait mensonger.

  readonly form = this.fb.nonNullable.group({
    start_date: ['', [Validators.required]],
    seats: [1, [Validators.required, Validators.min(1)]],
  });

  private readonly formValue = toSignal(this.form.valueChanges, {
    initialValue: this.form.getRawValue(),
  });

  readonly submitting = signal(false);
  readonly formError = signal<string | null>(null);

  constructor() {
    // F8.13 — reprise du panier après la connexion (une seule fois : il se consomme).
    let repris = false;
    effect(() => {
      const id = this.id();
      if (repris || !id) {
        return;
      }
      repris = true;
      const saisie = this.intents.take('experience', id);
      if (saisie) {
        this.form.patchValue({
          start_date: String(saisie['start_date'] ?? ''),
          seats: Number(saisie['seats'] ?? 1),
        });
      }
    });
  }

  /** Aujourd'hui (`YYYY-MM-DD`) : on ne part pas dans le passé. */
  readonly today = new Date().toISOString().slice(0, 10);

  readonly total = computed(
    () => Number(this.formValue().seats ?? 0) * (this.experience()?.price_xof ?? 0),
  );
  readonly totalLabel = computed(() => formatFcfa(this.total()));

  /**
   * Le nombre de places restantes est déjà connu de l'écran (`availability`) :
   * autant le dire avant le clic plutôt que de laisser le serveur refuser.
   */
  readonly bookingHint = computed<string | null>(() => {
    const places = Number(this.formValue().seats ?? 0);
    const restantes = this.availability()?.seats_left;
    if (places < 1) {
      return 'Indiquez au moins un participant.';
    }
    if (restantes !== undefined && restantes !== null && places > restantes) {
      return `Il ne reste que ${restantes} place(s) disponible(s).`;
    }
    return null;
  });

  readonly canQuote = computed(
    () => !!this.formValue().start_date && !this.bookingHint() && !this.soldOut(),
  );

  /**
   * Réserve des places sur le circuit (F8.10) puis emmène payer.
   */
  submit(): void {
    const experience = this.experience();
    if (this.submitting() || !experience) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();

    // Visiteur non connecté (F8.13) : date de départ et places sont conservées.
    if (!this.isAuthenticated()) {
      this.intents.remember('experience', String(experience.id), {
        start_date: raw.start_date,
        seats: Number(raw.seats),
      });
      void this.router.navigate(['/auth/connexion'], { queryParams: this.loginQueryParams() });
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.bookings
      .createExperienceBooking(experience.id, {
        start_date: raw.start_date,
        guests: Number(raw.seats),
      })
      .subscribe({
        next: (booking) => {
          this.submitting.set(false);
          void this.router.navigate(['/mon-espace/reservations', booking.id, 'paiement']);
        },
        error: (err: { status?: number; error?: ValidationErrorBody }) => {
          this.submitting.set(false);
          this.formError.set(this.messageFor(err));
        },
      });
  }

  /**
   * Le serveur annonce lui-même combien de places restent (« Il ne reste que 3
   * place(s) disponible(s). ») : son message vaut mieux que le nôtre.
   */
  private messageFor(err: { status?: number; error?: ValidationErrorBody }): string {
    if (err?.status === 422) {
      const premier = err.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
      return premier ?? 'Cette réservation ne peut pas être enregistrée.';
    }
    if (err?.status === 403) {
      return 'Confirmez votre e-mail ou votre téléphone depuis votre profil pour pouvoir réserver.';
    }
    return "La réservation n'a pas pu être enregistrée. Réessayez.";
  }
}
