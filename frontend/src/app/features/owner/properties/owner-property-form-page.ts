import { ChangeDetectionStrategy, Component, computed, inject, signal, viewChild } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Observable, of } from 'rxjs';
import { map, switchMap } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { Commune, Department, GeoService, Region } from '../../../core/api/geo.service';
import {
  CreatePropertyPayload,
  PropertyManagementService,
  PropertyType,
  UpdatePropertyPayload,
  UpsertStayPayload,
} from '../../../core/api/property-management.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Property, PropertyPhoto } from '../../../models/property.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { PhotoManagerComponent } from '../../../shared/components/photo-manager/photo-manager';

/** Option du sélecteur de type de bien (valeur = enum backend). */
interface TypeOption {
  value: PropertyType;
  label: string;
}

/**
 * Mode de location d'un bien.
 *   - `mensuelle` → loué au mois (loyer `price_xof` sur le bien) ;
 *   - `nuitees`   → loué à la nuitée (config `Stay` : prix/nuit, capacité…) ;
 *   - `mixte`     → les deux à la fois.
 */
type RentalMode = 'mensuelle' | 'nuitees' | 'mixte';

/** État de chargement du bien à éditer (mode édition seulement). */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

@Component({
  selector: 'app-owner-property-form-page',
  imports: [ReactiveFormsModule, RouterLink, BackLinkComponent, PhotoManagerComponent],
  templateUrl: './owner-property-form-page.html',
  styleUrl: './owner-property-form-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Dépôt **et** édition d'un bien depuis l'espace propriétaire (F4.3), monté sous
 * `/espace-proprietaire/biens/nouveau` (création) et `/biens/:id/modifier`
 * (édition). Un même composant sert les deux : la présence d'un `:id` bascule en
 * mode édition (préremplissage via `GET /properties/mine/{id}`).
 *
 * Points clés :
 *   - **compte vérifié requis** (les endpoints exigent `verified.account`) : on
 *     gate le formulaire en amont ;
 *   - **mode de location** mensuelle / nuitées / mixte, qui affiche le loyer
 *     mensuel et/ou le bloc nuitées et pilote les appels d'enregistrement ;
 *   - **localisation en cascade** région → département → commune (référentiel) ;
 *   - à l'enregistrement : POST/PATCH du bien, puis, selon le mode, PUT (config
 *     nuitées) ou DELETE (retrait du mode nuitées) ; on redirige ensuite vers la
 *     fiche du bien.
 */
export class OwnerPropertyFormPageComponent {
  private readonly geo = inject(GeoService);
  private readonly properties = inject(PropertyManagementService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  /**
   * Bloc photos, désormais mutualisé (F8.18) : la même mécanique sert les
   * véhicules et les circuits, qui n'avaient aucun moyen d'être illustrés.
   */
  private readonly photoManager = viewChild(PhotoManagerComponent);

  /**
   * Photos du bien reçues du serveur (mode édition), passées en entrée au bloc
   * partagé. ⚠️ Le bloc n'existe pas tant que le compte n'est pas vérifié : le
   * parent ne peut donc pas les lui pousser, il les lui **expose**.
   */
  readonly existingPhotos = signal<PropertyPhoto[]>([]);

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

  /** Identifiant du bien à éditer (null en création) — lu une fois à l'init. */
  readonly propertyId = signal<number | null>(null);
  readonly isEdit = computed(() => this.propertyId() !== null);

  /** Mode de location courant (pilote l'affichage et l'enregistrement). */
  readonly mode = signal<RentalMode>('mensuelle');

  /** En édition : le bien avait-il déjà une config nuitées ? (→ DELETE au besoin). */
  private readonly hadStay = signal(false);

  /** Vrai si le compte est vérifié (e-mail OU téléphone) — requis pour publier. */
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

  // --- État de chargement (édition) -----------------------------------------
  readonly loadState = signal<LoadState>('ready');

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
    // Bloc « nuitées » — activé/désactivé selon le mode (cf. applyMode()).
    stay: this.fb.nonNullable.group({
      price_per_night_xof: [null as number | null, [Validators.required, Validators.min(0)]],
      caution_xof: [null as number | null, [Validators.min(0)]],
      capacity: [null as number | null, [Validators.min(1)]],
      min_nights: [null as number | null, [Validators.min(1)]],
      max_nights: [null as number | null, [Validators.min(1)]],
      check_in_time: [''],
      check_out_time: [''],
    }),
  });

  readonly submitting = signal(false);
  /** Message d'erreur global du formulaire. */
  readonly formError = signal<string | null>(null);

  constructor() {
    // On ne prépare le formulaire que pour un compte vérifié (sinon la page
    // l'invite d'abord à vérifier son compte).
    if (!this.isVerified()) {
      return;
    }

    this.applyMode('mensuelle');
    this.loadRegions();
    this.wireCascade();

    // Mode édition si l'URL porte un `:id` (lecture unique : un formulaire n'est
    // pas réutilisé entre deux biens). On charge alors le bien et on préremplit.
    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.loadState.set('loading');
      this.properties.get(idParam).subscribe({
        next: (env) => {
          this.prefill(env.data);
          this.loadState.set('ready');
        },
        error: (err: { status?: number }) => {
          this.loadState.set(err?.status === 404 ? 'notfound' : 'failed');
        },
      });
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
   * zéro département + commune ; changer de département recharge les communes.
   * (Le préremplissage en édition utilise `emitEvent: false` pour ne pas
   * déclencher ces remises à zéro.)
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

  /** Bascule le mode de location (radios) et (dés)active le bloc nuitées. */
  applyMode(mode: RentalMode): void {
    this.mode.set(mode);
    const stay = this.form.controls.stay;
    if (mode === 'nuitees' || mode === 'mixte') {
      stay.enable();
    } else {
      stay.disable();
    }
  }

  /** Vrai si le mode courant inclut la location au mois (loyer affiché). */
  readonly showsMonthly = computed(() => this.mode() !== 'nuitees');
  /** Vrai si le mode courant inclut la location à la nuitée (bloc affiché). */
  readonly showsNightly = computed(() => this.mode() !== 'mensuelle');

  /**
   * Prépare le formulaire à partir d'un bien existant (mode édition).
   * Reçu depuis le résolveur de route via `prefill()`.
   */
  prefill(p: Property): void {
    this.propertyId.set(p.id);

    this.form.patchValue({
      type: (p.type as PropertyType) ?? '',
      title: p.title,
      description: p.description ?? '',
      price_xof: p.price_xof,
      tourist_zone: p.location.tourist_zone ?? '',
      address: p.location.address ?? '',
    });

    // Photos déjà en ligne (l'API les renvoie principale d'abord).
    this.existingPhotos.set(p.photos ?? []);

    // Géo : on charge les listes puis on pose les valeurs sans réamorcer la
    // cascade (emitEvent: false), pour éviter la remise à zéro en chaîne.
    this.prefillGeo(p.location.region_id, p.location.department_id, p.location.commune_id);

    // Mode déduit du bien : nuitées si config présente, mixte si de plus un loyer
    // mensuel, mensuelle sinon.
    const hasStay = !!p.stay;
    this.hadStay.set(hasStay);
    const mode: RentalMode = hasStay ? (p.price_xof ? 'mixte' : 'nuitees') : 'mensuelle';
    this.applyMode(mode);

    if (p.stay) {
      this.form.controls.stay.patchValue({
        price_per_night_xof: p.stay.price_per_night_xof,
        caution_xof: p.stay.caution_xof,
        capacity: p.stay.capacity,
        min_nights: p.stay.min_nights,
        max_nights: p.stay.max_nights,
        // Les heures viennent au format HH:MM:SS ; l'input `time` attend HH:MM.
        check_in_time: (p.stay.check_in_time ?? '').slice(0, 5),
        check_out_time: (p.stay.check_out_time ?? '').slice(0, 5),
      });
    }
  }

  /** Renseigne les sélecteurs géo à partir des ids du bien, sans cascade. */
  private prefillGeo(
    regionId: number | null,
    departmentId: number | null,
    communeId: number | null,
  ): void {
    if (!regionId) {
      return;
    }
    this.form.controls.region_id.setValue(regionId, { emitEvent: false });
    this.geo.departments(regionId).subscribe({
      next: (env) => {
        this.departments.set(env.data);
        if (departmentId) {
          this.form.controls.department_id.setValue(departmentId, { emitEvent: false });
          this.geo.communes(departmentId).subscribe({
            next: (env2) => {
              this.communes.set(env2.data);
              if (communeId) {
                this.form.controls.commune_id.setValue(communeId, { emitEvent: false });
              }
            },
            error: () => this.geoError.set(true),
          });
        }
      },
      error: () => this.geoError.set(true),
    });
  }

  /** Corps « bien » à envoyer (le loyer mensuel n'est gardé que si le mode l'inclut). */
  private propertyPayload(): CreatePropertyPayload {
    const raw = this.form.getRawValue();
    return {
      type: raw.type as PropertyType,
      title: raw.title,
      region_id: raw.region_id as number,
      department_id: raw.department_id as number,
      commune_id: raw.commune_id,
      description: raw.description || null,
      price_xof: this.showsMonthly() ? raw.price_xof : null,
      tourist_zone: raw.tourist_zone || null,
      address: raw.address || null,
    };
  }

  /** Corps « nuitées » à envoyer (lu même si le groupe est désactivé). */
  private stayPayload(): UpsertStayPayload {
    const s = this.form.controls.stay.getRawValue();
    return {
      price_per_night_xof: s.price_per_night_xof as number,
      caution_xof: s.caution_xof,
      capacity: s.capacity,
      min_nights: s.min_nights,
      max_nights: s.max_nights,
      check_in_time: s.check_in_time || null,
      check_out_time: s.check_out_time || null,
    };
  }

  /**
   * Réconcilie la config nuitées après l'enregistrement du bien :
   *   - mode incluant les nuitées → upsert (PUT) ;
   *   - sinon, si le bien en avait une (édition) → retrait (DELETE) ;
   *   - sinon → rien.
   */
  private reconcileStay(propertyId: number): Observable<unknown> {
    const mode = this.mode();
    if (mode === 'nuitees' || mode === 'mixte') {
      return this.properties.upsertStay(propertyId, this.stayPayload());
    }
    if (this.hadStay()) {
      return this.properties.removeStay(propertyId);
    }
    return of(null);
  }

  /** Enregistre le bien (création ou édition) puis sa config nuitées. */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.propertyPayload();
    this.submitting.set(true);
    this.formError.set(null);

    const id = this.propertyId();
    const save$ = id
      ? this.properties.update(id, payload as UpdatePropertyPayload)
      : this.properties.create(payload);

    save$
      .pipe(
        switchMap((env) => {
          const propertyId = env.data.property.id;
          // Le bien existe désormais : on réconcilie sa config nuitées, puis on
          // envoie les photos retenues (impossible avant, en création).
          return this.reconcileStay(propertyId).pipe(
            // Le bloc photos peut être absent du DOM (compte non vérifié) :
            // l'enregistrement du bien ne doit pas en dépendre.
            switchMap(() => this.photoManager()?.uploadPending(propertyId) ?? of(null)),
            map(() => propertyId),
          );
        }),
      )
      .subscribe({
        next: (propertyId) => {
          this.submitting.set(false);
          // On mène le propriétaire à la fiche du bien (statut de validation visible).
          this.router.navigate(['/espace-proprietaire/biens', propertyId]);
        },
        error: (err: { status?: number; error?: ValidationErrorBody }) => {
          this.submitting.set(false);
          const firstError = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
          this.formError.set(
            firstError ?? "L'enregistrement a échoué. Vérifiez les champs et réessayez.",
          );
        },
      });
  }
}
