import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { switchMap } from 'rxjs/operators';

import {
  EXPERIENCE_INCLUSIONS,
  NewExperiencePayload,
  OfferService,
} from '../../../core/api/offer.service';
import { Experience } from '../../../models/experience.model';
import { PropertyPhoto } from '../../../models/property.model';
import { extractGoogleMapsEmbedUrl } from '../../../shared/format/google-maps';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { PhotoManagerComponent } from '../../../shared/components/photo-manager/photo-manager';

/**
 * Formulaire de dépôt d'une **expérience touristique** (F5.6), monté sous
 * `/espace-prestataire/offres/experience/nouvelle`.
 *
 * Miroir de `StoreExperienceRequest` / `UpdateExperienceRequest`. Les
 * **inclusions** (restauration, guide, transport, hébergement) sont cochées et
 * envoyées en `{ cle: booléen }`.
 *
 * ⚠️ **L'édition n'existait pas** (F8.19) : le backend n'exposait aucun `PATCH`,
 * un circuit déposé était donc définitif — et, les photos n'étant déposables
 * qu'à la création (F8.18), un circuit créé sans photo ne pouvait plus jamais
 * être illustré. Le même composant sert désormais les deux modes, comme celui
 * des véhicules : la présence d'un `:id` bascule en édition.
 */
@Component({
  selector: 'app-provider-experience-form-page',
  imports: [ReactiveFormsModule, RouterLink, BackLinkComponent, PhotoManagerComponent],
  templateUrl: './provider-experience-form-page.html',
  styleUrl: './provider-experience-form-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderExperienceFormPageComponent {
  private readonly offers = inject(OfferService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  /** Inclusions proposées (miroir de `EXPERIENCE_INCLUSIONS`). */
  protected readonly inclusions = EXPERIENCE_INCLUSIONS;

  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  /** Id du circuit édité (null en création). */
  protected readonly editId = signal<number | null>(null);
  protected readonly isEdit = computed(() => this.editId() !== null);
  protected readonly state = signal<'loading' | 'form' | 'not-found' | 'error'>('form');

  /** Photos déjà en ligne du circuit (mode édition). */
  protected readonly existingPhotos = signal<PropertyPhoto[]>([]);

  /**
   * Bloc photos (F8.18). Un circuit est ce qui se vend le plus par l'image, et
   * c'était l'univers le plus démuni : ni dépôt, ni photo sur la carte, ni
   * galerie sur la fiche. ⚠️ Écran de **création seule** (le backend n'expose
   * pas d'édition de circuit) : les photos partent donc juste après le POST.
   */
  private readonly photoManager = viewChild(PhotoManagerComponent);

  protected readonly form = this.fb.nonNullable.group({
    title: ['', [Validators.required, Validators.maxLength(255)]],
    destination: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
    duration_days: [1, [Validators.required, Validators.min(1)]],
    price_xof: [0, [Validators.required, Validators.min(0)]],
    capacity: [1, [Validators.required, Validators.min(1)]],
    maps_link: [''],
    // Une case par inclusion, ajoutée dynamiquement ci-dessous.
    inclusions: this.fb.nonNullable.group(
      Object.fromEntries(EXPERIENCE_INCLUSIONS.map((i) => [i.key, this.fb.nonNullable.control(false)])),
    ),
  });

  constructor() {
    const idParam = this.route.snapshot.paramMap.get('id');
    if (!idParam) {
      return;
    }

    this.state.set('loading');
    this.editId.set(Number(idParam));

    // ⚠️ `findMyExperience` et non le détail public : on édite justement des
    // circuits « en attente », « rejetés » ou « retirés », que le catalogue
    // public ne renvoie pas.
    this.offers.findMyExperience(Number(idParam)).subscribe({
      next: (experience) => {
        if (!experience) {
          this.state.set('not-found');
          return;
        }
        this.patch(experience);
        this.state.set('form');
      },
      error: () => this.state.set('error'),
    });
  }

  /** Pré-remplit le formulaire à partir d'un circuit existant. */
  private patch(x: Experience): void {
    this.form.patchValue({
      title: x.title,
      destination: x.destination,
      description: x.description ?? '',
      duration_days: x.duration_days ?? 1,
      price_xof: x.price_xof,
      capacity: x.capacity,
      maps_link: x.maps_link ?? '',
    });

    // Les inclusions arrivent en `{ cle: booléen }` ; les cases absentes de la
    // réponse restent décochées.
    const inclusions = (x.inclusions ?? {}) as Record<string, boolean>;
    for (const option of EXPERIENCE_INCLUSIONS) {
      this.form.controls.inclusions.controls[option.key]?.setValue(!!inclusions[option.key]);
    }

    this.existingPhotos.set(x.photos ?? []);
  }

  /** Publie ou met à jour le circuit (POST / PATCH /experiences). */
  protected submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: NewExperiencePayload = {
      title: raw.title,
      destination: raw.destination,
      description: raw.description || null,
      duration_days: raw.duration_days,
      price_xof: raw.price_xof,
      capacity: raw.capacity,
      inclusions: raw.inclusions as Record<string, boolean>,
      maps_link: raw.maps_link ? extractGoogleMapsEmbedUrl(raw.maps_link) : null,
    };

    this.submitting.set(true);
    this.formError.set(null);

    const id = this.editId();
    const request$ = id
      ? this.offers.updateExperience(id, payload)
      : this.offers.createExperience(payload);

    request$
      .pipe(switchMap((env) => this.photoManager()?.uploadPending(env.data.experience.id) ?? of(null)))
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
    return firstError ?? "Votre circuit n'a pas pu être enregistré. Réessayez.";
  }
}
