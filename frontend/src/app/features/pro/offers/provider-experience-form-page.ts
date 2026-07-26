import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import {
  EXPERIENCE_INCLUSIONS,
  NewExperiencePayload,
  OfferService,
} from '../../../core/api/offer.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/**
 * Formulaire de dépôt d'une **expérience touristique** (F5.6), monté sous
 * `/espace-prestataire/offres/experience/nouvelle`.
 *
 * Miroir de `StoreExperienceRequest`. Les **inclusions** (restauration, guide,
 * transport, hébergement) sont cochées et envoyées en `{ cle: booléen }`. Le
 * backend n'expose pas d'édition d'expérience : cet écran est donc en création
 * uniquement (la modification passera par le back-office).
 */
@Component({
  selector: 'app-provider-experience-form-page',
  imports: [ReactiveFormsModule, BackLinkComponent],
  templateUrl: './provider-experience-form-page.html',
  styleUrl: './provider-experience-form-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderExperienceFormPageComponent {
  private readonly offers = inject(OfferService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  /** Inclusions proposées (miroir de `EXPERIENCE_INCLUSIONS`). */
  protected readonly inclusions = EXPERIENCE_INCLUSIONS;

  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    title: ['', [Validators.required, Validators.maxLength(255)]],
    destination: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
    duration_days: [1, [Validators.required, Validators.min(1)]],
    price_xof: [0, [Validators.required, Validators.min(0)]],
    capacity: [1, [Validators.required, Validators.min(1)]],
    // Une case par inclusion, ajoutée dynamiquement ci-dessous.
    inclusions: this.fb.nonNullable.group(
      Object.fromEntries(EXPERIENCE_INCLUSIONS.map((i) => [i.key, this.fb.nonNullable.control(false)])),
    ),
  });

  /** Publie l'expérience (POST /experiences). */
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
    };

    this.submitting.set(true);
    this.formError.set(null);

    this.offers.createExperience(payload).subscribe({
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
