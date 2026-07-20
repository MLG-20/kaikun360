import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { CatalogService } from '../../../core/api/catalog.service';
import { RequestService } from '../../../core/api/request.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { Property } from '../../../models/property.model';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';
import { DetailLayoutComponent } from '../../../shared/components/detail-layout/detail-layout';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Fiche détaillée d'un bien immobilier (F2.3) — route `/immobilier/:id`.
 *
 * Charge le bien via `CatalogService.property(id)` (un bien non publié renvoie
 * 404 → état « introuvable »). Présente description, localisation,
 * caractéristiques et niveau de vérification, puis un formulaire contextuel de
 * **demande de visite** (`POST /requests`, service_type = immo). Ce dépôt exige
 * une session : si l'utilisateur n'est pas connecté, on l'invite à se connecter
 * (avec retour sur la fiche).
 */
@Component({
  selector: 'app-property-detail-page',
  imports: [ReactiveFormsModule, RouterLink, WhatsAppButtonComponent, DetailLayoutComponent],
  templateUrl: './property-detail-page.html',
  styleUrl: './property-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PropertyDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly requests = inject(RequestService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  /** Identifiant du bien issu de l'URL. */
  private readonly id = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id'))),
  );

  readonly state = signal<LoadState>('loading');

  /** Bien chargé (ou null tant qu'indisponible). */
  readonly property = signal<Property | null>(null);

  /** Vrai si un utilisateur est connecté (autorise le dépôt de demande). */
  readonly isAuthenticated = this.auth.isAuthenticated;

  /** Paramètres de connexion renvoyant sur la fiche courante après login. */
  readonly loginQueryParams = computed(() => ({ redirect: `/immobilier/${this.id() ?? ''}` }));

  /**
   * Déclenche le chargement dès que l'identifiant est connu. `switchMap` annule
   * une requête précédente si l'on navigue d'une fiche à l'autre.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.property.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.catalog.property(id).pipe(
          tap((env) => {
            this.property.set(env.data);
            this.state.set('ready');
            this.prefillMessage(env.data);
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );

  // --- Formulaire de demande de visite --------------------------------------
  readonly form = this.fb.nonNullable.group({
    message: ['', [Validators.required, Validators.maxLength(2000)]],
    preferred_date: [''],
  });

  readonly submitting = signal(false);
  /** Référence de la demande créée (bandeau de succès). */
  readonly createdReference = signal<string | null>(null);
  /** Message d'erreur global du formulaire. */
  readonly formError = signal<string | null>(null);

  /** Badge « Vérifié » à afficher uniquement si le niveau est positif. */
  readonly verified = computed(() => {
    const level = this.property()?.verification_level;
    return !!level && level !== 'unverified' && level !== 'aucun';
  });

  /** Prix formaté du bien. */
  readonly priceLabel = computed(() => formatFcfa(this.property()?.price_xof));

  /**
   * URLs des photos du bien pour la galerie, **couverture en tête** (l'API les
   * renvoie déjà triées). Les entrées sans URL exploitable sont écartées pour ne
   * jamais produire d'image cassée ; une liste vide masque la galerie.
   */
  readonly photoUrls = computed(
    () =>
      this.property()
        ?.photos?.map((photo) => photo.url)
        .filter((url): url is string => !!url) ?? [],
  );

  /** Localisation lisible (commune · département · région). */
  readonly locationLabel = computed(() => {
    const loc = this.property()?.location;
    if (!loc) {
      return null;
    }
    return [loc.commune, loc.department, loc.region].filter(Boolean).join(' · ') || null;
  });

  /**
   * Lien vers une carte externe si les coordonnées sont connues (OpenStreetMap).
   * Les coordonnées sont sérialisées en chaîne par Laravel (cast decimal).
   */
  readonly mapLink = computed(() => {
    const loc = this.property()?.location;
    if (!loc?.latitude || !loc?.longitude) {
      return null;
    }
    return `https://www.openstreetmap.org/?mlat=${loc.latitude}&mlon=${loc.longitude}#map=16/${loc.latitude}/${loc.longitude}`;
  });

  /** Pré-remplit le message de visite avec le titre du bien. */
  private prefillMessage(property: Property): void {
    this.form.controls.message.setValue(
      `Bonjour, je souhaite organiser une visite du bien « ${property.title} ». Merci de me recontacter.`,
    );
  }

  /** Dépose la demande de visite (POST /requests, service_type = immo). */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const property = this.property();
    if (!property) {
      return;
    }

    // La date souhaitée est fusionnée dans le message (l'API générique ne porte
    // pas de champ dédié ; l'agent la lit dans le corps de la demande).
    const raw = this.form.getRawValue();
    const message = raw.preferred_date
      ? `${raw.message}\n\nDate souhaitée : ${raw.preferred_date}.`
      : raw.message;

    this.submitting.set(true);
    this.formError.set(null);

    this.requests
      .create({
        service_type: 'immo',
        message,
        city: property.location?.commune ?? null,
      })
      .subscribe({
        next: (env) => {
          this.submitting.set(false);
          this.createdReference.set(env.data.request.reference);
        },
        error: (err: { status?: number; error?: ValidationErrorBody }) => {
          this.submitting.set(false);
          // Le 401 est déjà détourné vers la connexion par l'errorInterceptor ;
          // ici on ne gère que les échecs de validation/serveur.
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
