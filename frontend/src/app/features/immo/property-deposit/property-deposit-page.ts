import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { Commune, Department, GeoService, Region } from '../../../core/api/geo.service';
import {
  CreatePropertyPayload,
  PropertyManagementService,
  PropertyType,
} from '../../../core/api/property-management.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Property } from '../../../models/property.model';

/** Option du sélecteur de type de bien (valeur = enum backend). */
interface TypeOption {
  value: PropertyType;
  label: string;
}

/**
 * Dépôt de bien par un propriétaire (F2.7) — route `/deposer-un-bien`.
 *
 * Formulaire intelligent qui dépose un bien via `POST /properties`. Points clés :
 *   - **auth + compte vérifié** requis (l'endpoint exige `verified.account`) :
 *     on gate le formulaire en amont (invitation à se connecter / vérifier) ;
 *   - **localisation en cascade** région → département → commune, alimentée par
 *     le référentiel géo (F2.7.0) : choisir une région charge ses départements,
 *     choisir un département charge ses communes (cohérence garantie côté
 *     serveur) ;
 *   - le bien créé part **en file de validation** (non publié) : la confirmation
 *     l'explique. Les photos/documents s'ajoutent ensuite (hors périmètre ici).
 */
@Component({
  selector: 'app-property-deposit-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './property-deposit-page.html',
  styleUrl: './property-deposit-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PropertyDepositPageComponent {
  private readonly geo = inject(GeoService);
  private readonly properties = inject(PropertyManagementService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  /** Types de biens proposés (miroir de l'enum `PropertyType`). */
  readonly types: TypeOption[] = [
    { value: 'appartement', label: 'Appartement' },
    { value: 'maison', label: 'Maison' },
    { value: 'villa', label: 'Villa' },
    { value: 'studio', label: 'Studio' },
    { value: 'terrain', label: 'Terrain' },
    { value: 'bureau', label: 'Bureau' },
    { value: 'commerce', label: 'Local commercial' },
    { value: 'autre', label: 'Autre' },
  ];

  /** Vrai si un utilisateur est connecté. */
  readonly isAuthenticated = this.auth.isAuthenticated;

  /** Vrai si le compte est vérifié (e-mail OU téléphone) — requis pour déposer. */
  readonly isVerified = computed(() => {
    const user = this.auth.user();
    return !!user && (user.email_verified_at !== null || user.phone_verified_at !== null);
  });

  // --- Options géographiques (cascade) --------------------------------------
  readonly regions = signal<Region[]>([]);
  readonly departments = signal<Department[]>([]);
  readonly communes = signal<Commune[]>([]);
  /** Vrai si le chargement du référentiel a échoué (bloque la saisie géo). */
  readonly geoError = signal(false);

  readonly form = this.fb.nonNullable.group({
    type: ['' as PropertyType | '', [Validators.required]],
    title: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
    price_xof: [null as number | null, [Validators.min(0)]],
    region_id: [null as number | null, [Validators.required]],
    department_id: [null as number | null, [Validators.required]],
    commune_id: [null as number | null],
    tourist_zone: [''],
    address: [''],
  });

  readonly submitting = signal(false);
  /** Bien créé (bandeau de succès). */
  readonly created = signal<Property | null>(null);
  /** Message d'erreur global du formulaire. */
  readonly formError = signal<string | null>(null);

  constructor() {
    // On ne charge le référentiel que pour un utilisateur éligible (le reste de
    // la page l'invite d'abord à se connecter / vérifier son compte).
    if (this.isAuthenticated() && this.isVerified()) {
      this.loadRegions();
      this.wireCascade();
    }
  }

  /** Charge la liste des régions (première étape de la cascade). */
  private loadRegions(): void {
    this.geo.regions().subscribe({
      next: (env) => this.regions.set(env.data),
      error: () => this.geoError.set(true),
    });
  }

  /**
   * Branche la cascade : changer de région recharge les départements et remet à
   * zéro département + commune ; changer de département recharge les communes et
   * remet à zéro la commune.
   */
  private wireCascade(): void {
    this.form.controls.region_id.valueChanges
      .pipe(takeUntilDestroyed())
      .subscribe((regionId) => {
        this.departments.set([]);
        this.communes.set([]);
        this.form.controls.department_id.setValue(null);
        this.form.controls.commune_id.setValue(null);
        if (regionId) {
          this.geo.departments(regionId).subscribe({
            next: (env) => this.departments.set(env.data),
            error: () => this.geoError.set(true),
          });
        }
      });

    this.form.controls.department_id.valueChanges
      .pipe(takeUntilDestroyed())
      .subscribe((departmentId) => {
        this.communes.set([]);
        this.form.controls.commune_id.setValue(null);
        if (departmentId) {
          this.geo.communes(departmentId).subscribe({
            next: (env) => this.communes.set(env.data),
            error: () => this.geoError.set(true),
          });
        }
      });
  }

  /** Dépose le bien (POST /properties). */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: CreatePropertyPayload = {
      type: raw.type as PropertyType,
      title: raw.title,
      region_id: raw.region_id as number,
      department_id: raw.department_id as number,
      commune_id: raw.commune_id,
      description: raw.description || null,
      price_xof: raw.price_xof,
      tourist_zone: raw.tourist_zone || null,
      address: raw.address || null,
    };

    this.submitting.set(true);
    this.formError.set(null);

    this.properties.create(payload).subscribe({
      next: (env) => {
        this.submitting.set(false);
        this.created.set(env.data.property);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        const firstError = err?.error?.errors
          ? Object.values(err.error.errors)[0]?.[0]
          : null;
        this.formError.set(
          firstError ?? "Votre bien n'a pas pu être déposé. Réessayez.",
        );
      },
    });
  }
}
