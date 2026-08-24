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
 * Présente caractéristiques (type, capacité, chauffeur), la note
 * moyenne, puis le formulaire de **location ferme**
 * (`POST /vehicles/{id}/bookings`, F8.10).
 *
 * ⚠️ Cette page ne déposait qu'une *demande*, dates recopiées dans le corps
 * d'un message qu'un conseiller relisait à la main. Elle crée désormais une
 * vraie location. Le contrôle **anti double-location** a été ajouté au serveur
 * dans la même tranche : il manquait, et deux clients pouvaient repartir avec
 * le même véhicule le même jour.
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
  private readonly bookings = inject(BookingService);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);
  private readonly seo = inject(SeoService);
  /** Panier : garde la saisie du visiteur le temps qu'il se connecte (F8.13). */
  private readonly intents = inject(BookingIntentStore);

  private readonly id = toSignal(this.route.paramMap.pipe(map((p) => p.get('id'))));

  readonly state = signal<LoadState>('loading');
  readonly vehicle = signal<Vehicle | null>(null);
  readonly reviews = signal<ReviewList | null>(null);

  readonly isAuthenticated = this.auth.isAuthenticated;
  readonly loginQueryParams = computed(() => ({ redirect: `/transport/${this.id() ?? ''}` }));

  /**
   * URLs de la galerie (F8.18) — les photos déposées par le partenaire. Le
   * composant de fiche recevait `[galleryAlt]` mais jamais `[images]` : la
   * galerie était montée et systématiquement vide.
   */
  readonly photoUrls = computed(
    () =>
      this.vehicle()
        ?.photos?.map((photo) => photo.url)
        .filter((url): url is string => !!url) ?? [],
  );


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
            this.referencer(env.data);
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

  /**
   * Affine les balises de référencement avec le véhicule chargé (F9.1).
   *
   * ⚠️ Un véhicule n'a pas de titre : son nom se compose de la marque et du
   * modèle, avec le type en repli — exactement comme `vehicleName` plus haut.
   * Les deux doivent rester d'accord, sinon l'onglet et la page nommeraient la
   * même voiture différemment.
   */
  private referencer(vehicule: Vehicle): void {
    const nom = [vehicule.brand, vehicule.model].filter(Boolean).join(' ') ||
      vehicule.type_label ||
      'Véhicule';
    const conduite = vehicule.has_driver ? 'avec chauffeur' : 'sans chauffeur';
    const description =
      vehicule.description?.trim() ||
      `${nom} en location ${conduite}, ${vehicule.capacity} places, ${formatFcfa(
        vehicule.price_per_day_xof,
      )} par jour.`;

    this.seo.apply({
      // ⚠️ Le mode de conduite reste dans la DESCRIPTION, pas dans le titre :
      // quand la marque et le modèle manquent, `nom` retombe sur le libellé de
      // type — qui vaut « Chauffeur » pour un véhicule avec chauffeur. Le titre
      // bégayait alors (« Chauffeur en location avec chauffeur »). La mention
      // géographique, elle, a sa place ici : c'est un terme de recherche.
      title: `${nom} en location au Sénégal`,
      description,
      type: 'product',
      canonicalPath: `/transport/${vehicule.id}`,
      image: vehicule.photo_url ?? null,
    });
    this.seo.setJsonLd(
      'offre',
      schemaOffre({
        nom,
        description: vehicule.description,
        image: vehicule.photo_url,
        chemin: `/transport/${vehicule.id}`,
        prixXof: vehicule.price_per_day_xof,
        unite: 'jour',
      }),
    );
    this.seo.setJsonLd(
      'ariane',
      schemaFilAriane([
        { nom: 'Accueil', chemin: '/' },
        { nom: 'Transport', chemin: '/transport' },
        { nom, chemin: `/transport/${vehicule.id}` },
      ]),
    );
  }


  // --- Formulaire de demande de réservation ---------------------------------
  readonly form = this.fb.nonNullable.group({
    start_date: ['', [Validators.required]],
    end_date: ['', [Validators.required]],
    guests: [1, [Validators.min(1)]],
  });

  /** Valeurs suivies en signal, pour chiffrer la location en direct. */
  private readonly formValue = toSignal(this.form.valueChanges, {
    initialValue: this.form.getRawValue(),
  });

  readonly submitting = signal(false);
  readonly formError = signal<string | null>(null);

  constructor() {
    // F8.13 — reprise du panier : le visiteur retrouve ses dates après s'être
    // connecté. Une seule reprise par instance : le panier se consomme.
    let repris = false;
    effect(() => {
      const id = this.id();
      if (repris || !id) {
        return;
      }
      repris = true;
      const saisie = this.intents.take('vehicle', id);
      if (saisie) {
        this.form.patchValue({
          start_date: String(saisie['start_date'] ?? ''),
          end_date: String(saisie['end_date'] ?? ''),
          guests: Number(saisie['guests'] ?? 1),
        });
      }
    });
  }

  /** Aujourd'hui (`YYYY-MM-DD`) : borne `min` des deux champs de date. */
  readonly today = new Date().toISOString().slice(0, 10);

  /**
   * Nombre de journées facturées. ⚠️ Une location d'UN seul jour est permise et
   * facturée une journée (`max(1, …)` côté serveur) : rendre le véhicule le
   * jour même n'annule pas la mise à disposition.
   */
  readonly days = computed(() => {
    const { start_date, end_date } = this.formValue();
    if (!start_date || !end_date) {
      return 0;
    }
    const ecart = new Date(end_date).getTime() - new Date(start_date).getTime();
    return ecart < 0 ? 0 : Math.max(1, Math.round(ecart / 86_400_000));
  });

  readonly total = computed(() => this.days() * (this.vehicle()?.price_per_day_xof ?? 0));
  readonly totalLabel = computed(() => formatFcfa(this.total()));

  /** Règle dite AVANT le clic plutôt que renvoyée en 422 après. */
  readonly bookingHint = computed<string | null>(() => {
    const vehicle = this.vehicle();
    const { start_date, end_date } = this.formValue();
    if (!vehicle || !start_date || !end_date) {
      return null;
    }
    if (new Date(end_date) < new Date(start_date)) {
      return 'La date de fin ne peut pas précéder le début.';
    }
    const passagers = Number(this.formValue().guests ?? 0);
    if (passagers > vehicle.capacity) {
      return `Ce véhicule transporte ${vehicle.capacity} personne(s) au maximum.`;
    }
    return null;
  });

  readonly canQuote = computed(() => this.days() > 0 && !this.bookingHint());

  /**
   * Loue le véhicule (F8.10).
   *
   * ⚠️ Ce bouton déposait une simple DEMANDE : les dates étaient recopiées dans
   * le corps d'un message qu'un conseiller relisait à la main. Il crée
   * désormais une vraie location, et emmène le client la régler.
   */
  submit(): void {
    const vehicle = this.vehicle();
    if (this.submitting() || !vehicle) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();

    // Visiteur non connecté (F8.13) : sa saisie est mise de côté, il revient ici
    // avec ses dates après la connexion.
    if (!this.isAuthenticated()) {
      this.intents.remember('vehicle', String(vehicle.id), {
        start_date: raw.start_date,
        end_date: raw.end_date,
        guests: Number(raw.guests) || 1,
      });
      void this.router.navigate(['/auth/connexion'], { queryParams: this.loginQueryParams() });
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.bookings
      .createVehicleBooking(vehicle.id, {
        start_date: raw.start_date,
        end_date: raw.end_date,
        guests: Number(raw.guests) || 1,
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
   * Les 422 du serveur sont déjà écrits pour un client (« Ce véhicule est déjà
   * loué sur cette période. ») : on les affiche tels quels.
   */
  private messageFor(err: { status?: number; error?: ValidationErrorBody }): string {
    if (err?.status === 422) {
      const premier = err.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
      return premier ?? 'Ces dates ne peuvent pas être réservées.';
    }
    if (err?.status === 403) {
      return 'Confirmez votre e-mail ou votre téléphone depuis votre profil pour pouvoir réserver.';
    }
    return "La location n'a pas pu être enregistrée. Réessayez.";
  }
}
