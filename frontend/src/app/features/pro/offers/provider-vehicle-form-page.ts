import { ChangeDetectionStrategy, Component, computed, inject, signal, viewChild } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { of } from 'rxjs';
import { switchMap } from 'rxjs/operators';

import {
  NewVehiclePayload,
  OfferService,
  VEHICLE_TYPES,
  VehicleTypeValue,
  vehicleFamily,
} from '../../../core/api/offer.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { PropertyPhoto } from '../../../models/property.model';
import { Vehicle } from '../../../models/vehicle.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { PhotoManagerComponent } from '../../../shared/components/photo-manager/photo-manager';

/** État d'affichage de l'écran (création prête d'emblée ; édition attend le chargement). */
type FormState = 'loading' | 'form' | 'not-found' | 'error';

/**
 * Formulaire de dépôt / édition d'un **véhicule** (F5.6), monté sous
 * `/espace-prestataire/offres/vehicule/nouveau` et `.../vehicule/:id/modifier`.
 *
 * Miroir de `StoreVehicleRequest` / `UpdateVehicleRequest`. Les champs de
 * conformité affichés dépendent de la **famille** du type choisi : assurance +
 * identité chauffeur pour un motorisé, gilets + météo pour une pirogue (§12 du
 * CDC). Ils sont facultatifs au dépôt mais conditionnent la validation.
 *
 * En édition, le véhicule est rechargé via `OfferService.findMyVehicle` (le
 * détail public ne renvoie que les véhicules publiés).
 */
@Component({
  selector: 'app-provider-vehicle-form-page',
  imports: [ReactiveFormsModule, BackLinkComponent, PhotoManagerComponent],
  templateUrl: './provider-vehicle-form-page.html',
  styleUrl: './offer-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderVehicleFormPageComponent {
  private readonly offers = inject(OfferService);
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  /** Catalogue des types proposés (miroir de l'enum `VehicleType`). */
  protected readonly types = VEHICLE_TYPES;

  /** Id du véhicule édité (null en création). */
  protected readonly editId = signal<number | null>(null);

  /**
   * Bloc photos (F8.18). Ce formulaire n'en avait AUCUN : un loueur déposait son
   * véhicule et sa carte au catalogue restait un aplat de couleur, sur l'univers
   * où l'image décide presque seule (on ne loue pas une voiture qu'on n'a pas
   * vue). Le serveur acceptait pourtant la clé `vehicle` depuis B12.1.
   */
  private readonly photoManager = viewChild(PhotoManagerComponent);

  /** Photos du véhicule reçues du serveur (mode édition). */
  protected readonly existingPhotos = signal<PropertyPhoto[]>([]);
  protected readonly state = signal<FormState>('form');
  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    type: ['' as VehicleTypeValue | '', [Validators.required]],
    brand: [''],
    model: [''],
    capacity: [1, [Validators.required, Validators.min(1)]],
    price_per_day_xof: [0, [Validators.required, Validators.min(0)]],
    has_driver: [false],
    caution_xof: [null as number | null, [Validators.min(0)]],
    description: [''],
    // Conformité motorisé.
    insurance_ref: [''],
    driver_identity: [''],
    // Conformité pirogue.
    life_jackets_count: [null as number | null, [Validators.min(0)]],
    weather_compliant: [false],
    provider_compliant: [false],
  });

  /** Type sélectionné, suivi en signal pour piloter l'affichage conditionnel. */
  private readonly typeValue = signal<VehicleTypeValue | ''>('');

  /** Famille du type courant (motorisé / pirogue) — pilote les champs de conformité. */
  protected readonly family = computed(() => vehicleFamily(this.typeValue()));

  /** Vrai en mode édition (pour les libellés). */
  protected readonly isEdit = computed(() => this.editId() !== null);

  constructor() {
    this.form.controls.type.valueChanges.subscribe((v) => this.typeValue.set(v));

    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.state.set('loading');
      this.editId.set(Number(idParam));
      this.offers.findMyVehicle(Number(idParam)).subscribe({
        next: (vehicle) => {
          if (!vehicle) {
            this.state.set('not-found');
            return;
          }
          this.patch(vehicle);
          this.state.set('form');
        },
        error: () => this.state.set('error'),
      });
    }
  }

  /** Pré-remplit le formulaire à partir d'un véhicule existant. */
  private patch(v: Vehicle): void {
    this.form.patchValue({
      type: (v.type as VehicleTypeValue) ?? '',
      brand: v.brand ?? '',
      model: v.model ?? '',
      capacity: v.capacity,
      price_per_day_xof: v.price_per_day_xof,
      has_driver: v.has_driver,
      caution_xof: v.caution_xof,
      description: v.description ?? '',
    });
    this.typeValue.set((v.type as VehicleTypeValue) ?? '');
    this.existingPhotos.set(v.photos ?? []);
  }

  /** Dépose ou met à jour le véhicule. */
  protected submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: NewVehiclePayload = {
      type: raw.type as VehicleTypeValue,
      brand: raw.brand || null,
      model: raw.model || null,
      capacity: raw.capacity,
      price_per_day_xof: raw.price_per_day_xof,
      has_driver: raw.has_driver,
      caution_xof: raw.caution_xof,
      description: raw.description || null,
      insurance_ref: raw.insurance_ref || null,
      driver_identity: raw.driver_identity || null,
      life_jackets_count: raw.life_jackets_count,
      weather_compliant: raw.weather_compliant,
      provider_compliant: raw.provider_compliant,
    };

    this.submitting.set(true);
    this.formError.set(null);

    const id = this.editId();
    const request$ = id
      ? this.offers.updateVehicle(id, payload)
      : this.offers.createVehicle(payload);

    // ⚠️ Les photos partent APRÈS l'enregistrement : en création le véhicule
    // n'a pas encore d'id, et un média ne peut être rattaché à rien.
    request$
      .pipe(switchMap((env) => this.photoManager()?.uploadPending(env.data.vehicle.id) ?? of(null)))
      .subscribe({
      next: () => {
        this.submitting.set(false);
        this.router.navigate(['/espace-prestataire/offres']);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        this.formError.set(this.messageFor(err));
      },
    });
  }

  /** Traduit une erreur serveur en message affichable. */
  private messageFor(err: { status?: number; error?: ValidationErrorBody }): string {
    if (err?.status === 403) {
      return 'Le dépôt est réservé aux prestataires dont le dossier est validé.';
    }
    const firstError = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
    return firstError ?? "Votre véhicule n'a pas pu être enregistré. Réessayez.";
  }
}
