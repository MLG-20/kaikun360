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
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { BookingService } from '../../../core/api/booking.service';
import { CatalogService } from '../../../core/api/catalog.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { schemaFilAriane, schemaOffre } from '../../../core/seo/json-ld';
import { SeoService } from '../../../core/seo/seo.service';
import { BookingIntentStore } from '../../../core/state/booking-intent-store';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { MobilityService } from '../../../models/mobility-service.model';
import { DetailLayoutComponent } from '../../../shared/components/detail-layout/detail-layout';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Fiche d'un **départ programmé** (F8.10) — route `/mobilite/:id`.
 *
 * ⚠️ **Cette page n'existait pas, et l'endpoint qui l'alimente non plus.**
 * L'univers Mobilité se limitait à un catalogue : le code assumait alors que
 * « la réservation d'un trajet se fait via un conseiller », renvoyant vers
 * WhatsApp — alors que `POST /mobility-services/{id}/bookings` était livré
 * depuis B7.3 et n'avait jamais eu d'appelant. Les trajets étaient le dernier
 * univers où réserver était impossible.
 *
 * **Un trajet ne se réserve pas comme le reste.** Il est déjà *daté* : le
 * client ne choisit ni période ni jour de départ, seulement un **nombre de
 * places** sur un départ existant. D'où un formulaire à un seul champ — et
 * aucune date à saisir, ce qui aurait laissé croire qu'on peut en changer.
 *
 * Le **remplissage** vient du serveur avec la fiche : annoncer « 30 places »
 * sur un départ où il n'en reste qu'une ferait découvrir le refus après le
 * clic.
 */
@Component({
  selector: 'app-trip-detail-page',
  imports: [ReactiveFormsModule, RouterLink, DetailLayoutComponent, WhatsAppButtonComponent],
  templateUrl: './trip-detail-page.html',
  styleUrl: './trip-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TripDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly bookings = inject(BookingService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  private readonly seo = inject(SeoService);
  /** Panier : garde la saisie du visiteur le temps qu'il se connecte (F8.13). */
  private readonly intents = inject(BookingIntentStore);

  private readonly id = toSignal(this.route.paramMap.pipe(map((p) => p.get('id'))));

  readonly state = signal<LoadState>('loading');
  readonly trip = signal<MobilityService | null>(null);
  /** Places restantes sur ce départ, telles que le serveur les compte. */
  readonly seatsLeft = signal<number | null>(null);

  readonly isAuthenticated = this.auth.isAuthenticated;
  readonly loginQueryParams = computed(() => ({ redirect: `/mobilite/${this.id() ?? ''}` }));

  /**
   * URLs de la galerie (F8.18) — les photos déposées par le partenaire. Le
   * composant de fiche recevait `[galleryAlt]` mais jamais `[images]` : la
   * galerie était montée et systématiquement vide.
   */
  readonly photoUrls = computed(
    () =>
      this.trip()
        ?.photos?.map((photo) => photo.url)
        .filter((url): url is string => !!url) ?? [],
  );


  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((p) => p.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.trip.set(null);
        this.seatsLeft.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.catalog.mobilityService(id).pipe(
          tap((env) => {
            this.trip.set(env.data.mobility_service);
            this.seatsLeft.set(env.data.seats_left);
            this.state.set('ready');
            this.referencer(env.data.mobility_service, env.data.seats_left);
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );

  // --- Présentation ----------------------------------------------------------

  /** « Dakar → Saly », l'identité d'un trajet : d'où l'on part, où l'on va. */
  readonly displayName = computed(() => {
    const trip = this.trip();
    if (!trip) {
      return 'Trajet';
    }
    return `${trip.departure} → ${trip.destination}`;
  });

  readonly priceLabel = computed(() => formatFcfa(this.trip()?.price_xof));

  /**
   * Affine les balises de référencement avec le départ chargé (F9.1).
   *
   * ⚠️ **Le seul écran du catalogue dont l'offre peut être `SoldOut`** : un
   * départ est daté et ses places s'épuisent (F8.23.a a d'ailleurs corrigé le
   * fait qu'un départ passé restait payable). Déclarer « disponible » un car
   * complet ferait promettre à Google une place qui n'existe plus.
   */
  private referencer(trajet: MobilityService, placesRestantes: number): void {
    const nom = `${trajet.departure} → ${trajet.destination}`;
    const passe = !!trajet.departure_at && new Date(trajet.departure_at).getTime() < Date.now();
    const quand = trajet.departure_at
      ? new Date(trajet.departure_at).toLocaleDateString('fr-FR', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        })
      : null;
    const description =
      trajet.description?.trim() ||
      `Départ ${nom}${quand ? ` le ${quand}` : ''} — ${formatFcfa(trajet.price_xof)} la place.`;

    this.seo.apply({
      title: `${nom}${quand ? ` — ${quand}` : ''}`,
      description,
      type: 'product',
      canonicalPath: `/mobilite/${trajet.id}`,
      image: trajet.photo_url ?? null,
    });
    this.seo.setJsonLd(
      'offre',
      schemaOffre({
        nom,
        description: trajet.description,
        image: trajet.photo_url,
        chemin: `/mobilite/${trajet.id}`,
        prixXof: trajet.price_xof,
        unite: 'place',
        lieu: trajet.destination,
        disponible: !passe && placesRestantes > 0,
      }),
    );
    this.seo.setJsonLd(
      'ariane',
      schemaFilAriane([
        { nom: 'Accueil', chemin: '/' },
        { nom: 'Mobilité', chemin: '/mobilite' },
        { nom, chemin: `/mobilite/${trajet.id}` },
      ]),
    );
  }

  /** Date et heure du départ en toutes lettres : « lundi 14 septembre à 07:30 ». */
  readonly departureLabel = computed(() => {
    const iso = this.trip()?.departure_at;
    if (!iso) {
      return null;
    }
    const date = new Date(iso);
    const jour = date.toLocaleDateString('fr-FR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
    const heure = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    return `${jour} à ${heure}`;
  });

  /** Le départ est-il passé ? On ne vend pas une place dans un car déjà parti. */
  readonly departed = computed(() => {
    const iso = this.trip()?.departure_at;
    return !!iso && new Date(iso).getTime() < Date.now();
  });

  readonly soldOut = computed(() => this.seatsLeft() === 0);

  // --- Réservation -----------------------------------------------------------

  readonly form = this.fb.nonNullable.group({
    guests: [1, [Validators.required, Validators.min(1)]],
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
      const saisie = this.intents.take('mobility', id);
      if (saisie) {
        this.form.patchValue({ guests: Number(saisie['guests'] ?? 1) });
      }
    });
  }

  readonly total = computed(
    () => Number(this.formValue().guests ?? 0) * (this.trip()?.price_xof ?? 0),
  );
  readonly totalLabel = computed(() => formatFcfa(this.total()));

  /** La règle est dite AVANT le clic, pas renvoyée en 422 après. */
  readonly bookingHint = computed<string | null>(() => {
    const places = Number(this.formValue().guests ?? 0);
    const restantes = this.seatsLeft();
    if (places < 1) {
      return 'Indiquez au moins une place.';
    }
    if (restantes !== null && places > restantes) {
      return `Il ne reste que ${restantes} place(s) disponible(s).`;
    }
    return null;
  });

  readonly canQuote = computed(
    () => !this.bookingHint() && !this.soldOut() && !this.departed(),
  );

  /** Réserve des places puis emmène payer. */
  submit(): void {
    const trip = this.trip();
    if (this.submitting() || !trip) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    // Visiteur non connecté (F8.13) : le nombre de places est conservé.
    if (!this.isAuthenticated()) {
      this.intents.remember('mobility', String(trip.id), {
        guests: Number(this.form.getRawValue().guests),
      });
      void this.router.navigate(['/auth/connexion'], { queryParams: this.loginQueryParams() });
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.bookings
      .createMobilityBooking(trip.id, { guests: Number(this.form.getRawValue().guests) })
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
   * Le serveur annonce lui-même le nombre de places restantes : deux clients
   * peuvent viser la dernière au même instant, et c'est LUI qui tranche. Son
   * message vaut donc mieux qu'une formule générique de notre cru.
   */
  private messageFor(err: { status?: number; error?: ValidationErrorBody }): string {
    if (err?.status === 422) {
      const premier = err.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
      return premier ?? 'Ces places ne peuvent pas être réservées.';
    }
    if (err?.status === 403) {
      return 'Confirmez votre e-mail ou votre téléphone depuis votre profil pour pouvoir réserver.';
    }
    return "La réservation n'a pas pu être enregistrée. Réessayez.";
  }
}
