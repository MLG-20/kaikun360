import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, effect, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { ValidationErrorBody } from '../../../core/api/api-response.model';
import {
  WaitlistCategory,
  WaitlistEntryPayload,
  WaitlistService,
} from '../../../core/api/waitlist.service';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/** Une carte de catégorie affichée dans le sélecteur segmenté. */
interface CategoryOption {
  value: WaitlistCategory;
  label: string;
}

/**
 * Page « Liste d'attente » avant ouverture officielle (2026-08-14) — route
 * `/liste-attente`.
 *
 * Détachée de la page statique que le client maintient sur `kaikun360.com`
 * (Cloudflare Worker hors de ce dépôt) : celle-ci ne couvre que 3 catégories
 * (Propriétaire, Prestataire, Client), la nôtre en couvre 5 — Team building et
 * Diaspora en plus, chacune avec ses propres champs. Route interne pour
 * l'instant, pas encore liée à la navigation ni branchée sur le domaine public.
 *
 * Formulaire public (`POST /waitlist`), même logique que la page Contact : pas
 * de compte requis, le backend limite le débit.
 */
@Component({
  selector: 'app-waitlist-page',
  imports: [PageHeroComponent, ReactiveFormsModule],
  templateUrl: './waitlist-page.html',
  styleUrl: './waitlist-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WaitlistPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly waitlist = inject(WaitlistService);

  protected readonly categories: CategoryOption[] = [
    { value: 'proprietaire', label: 'Propriétaire' },
    { value: 'prestataire', label: 'Prestataire' },
    { value: 'client', label: 'Client intéressé' },
    { value: 'team_building', label: 'Team building' },
    { value: 'diaspora', label: 'Diaspora' },
  ];

  /** Catégorie sélectionnée — pilote les champs affichés et leurs validateurs. */
  protected readonly category = signal<WaitlistCategory>('proprietaire');

  protected selectCategory(value: WaitlistCategory): void {
    this.category.set(value);
  }

  /**
   * Formulaire commun aux 5 catégories, plus un sous-groupe `details` qui
   * porte TOUS les champs spécifiques possibles. Seuls ceux de la catégorie
   * sélectionnée sont affichés et rendus obligatoires (effet ci-dessous) ;
   * les autres restent vides et ne sont jamais envoyés (cf. `submit()`).
   */
  protected readonly form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(150)]],
    phone: ['', [Validators.required, Validators.maxLength(30)]],
    email: ['', [Validators.email, Validators.maxLength(255)]],
    city: ['', [Validators.maxLength(255)]],
    precisions: ['', [Validators.maxLength(2000)]],
    details: this.fb.nonNullable.group({
      type_bien: [''],
      nb_biens: [''],
      type_service: [''],
      univers: [''],
      taille_equipe: [''],
      budget_xof: [''],
      pays_residence: [''],
      type_projet: [''],
    }),
  });

  /**
   * Le champ requis par la catégorie change de validateur avec elle — sans
   * cet effet, changer de catégorie sans jamais avoir touché un champ
   * spécifique passerait la validation malgré un champ manquant, ou
   * inversement bloquerait sur un champ devenu invisible.
   */
  private readonly syncRequiredField = effect(() => {
    const details = this.form.controls.details;
    const requiredByCategory: Record<WaitlistCategory, string | null> = {
      proprietaire: 'type_bien',
      prestataire: 'type_service',
      client: 'univers',
      team_building: 'taille_equipe',
      diaspora: 'pays_residence',
    };
    const requiredField = requiredByCategory[this.category()];

    for (const name of Object.keys(details.controls)) {
      const control = details.get(name)!;
      control.clearValidators();
      if (name === requiredField) {
        control.setValidators([Validators.required]);
      }
      control.updateValueAndValidity({ emitEvent: false });
    }
  });

  protected readonly submitting = signal(false);
  protected readonly sent = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly fieldErrors = signal<Record<string, string>>({});

  protected error(field: string): string | null {
    return this.fieldErrors()[field] ?? null;
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);
    this.fieldErrors.set({});

    const raw = this.form.getRawValue();
    const payload: WaitlistEntryPayload = {
      name: raw.name,
      phone: raw.phone,
      email: raw.email || null,
      city: raw.city || null,
      precisions: raw.precisions || null,
      category: this.category(),
      // On ne transmet que les champs non vides : les champs des AUTRES
      // catégories, jamais affichés, restent à '' et seraient sinon envoyés
      // comme des précisions fantômes.
      details: Object.fromEntries(
        Object.entries(raw.details).filter(([, value]) => value !== ''),
      ),
    };

    this.waitlist.create(payload).subscribe({
      next: () => {
        this.submitting.set(false);
        this.sent.set(true);
        this.form.reset();
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.applyError(error);
      },
    });
  }

  protected reset(): void {
    this.sent.set(false);
  }

  private applyError(error: HttpErrorResponse): void {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const flat: Record<string, string> = {};
      for (const [field, messages] of Object.entries(body?.errors ?? {})) {
        flat[field] = messages[0];
      }
      this.fieldErrors.set(flat);
      this.formError.set(body?.message ?? 'Veuillez corriger les champs indiqués.');
      return;
    }
    this.formError.set('Envoi impossible pour le moment. Réessayez dans un instant.');
  }
}
