import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';
import { RegisterPayload } from '../../../../core/auth/auth.types';

/** Un choix de profil proposé à l'inscription (onboarding, CDC §5.2). */
interface ProfileOption {
  value: string;
  icon: string;
  label: string;
  description: string;
}

/**
 * Page d'inscription + onboarding (F1.2).
 *
 * Un écran : choix du profil (5 casquettes métier) puis coordonnées et mot de
 * passe. En cas de succès, `AuthService.register` ouvre la session (jeton en
 * mémoire). Le backend renvoie **422** avec des erreurs par champ (e-mail ou
 * téléphone déjà utilisés…) : elles sont affichées sous les champs concernés.
 */
@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './register-page.html',
  styleUrl: './register-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RegisterPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Les 5 profils du cahier des charges (valeurs = enum ProfileType backend). */
  protected readonly profiles: ProfileOption[] = [
    { value: 'client', icon: '🔎', label: 'Client', description: 'Rechercher, réserver, demander un service' },
    { value: 'proprietaire', icon: '🏠', label: 'Propriétaire', description: 'Déposer et gérer vos biens' },
    { value: 'prestataire', icon: '🧰', label: 'Prestataire', description: 'Proposer véhicule, circuit, BTP, guide…' },
    { value: 'entreprise', icon: '🏢', label: 'Entreprise', description: 'Demandes groupées, team building, devis' },
    { value: 'diaspora', icon: '✈️', label: 'Diaspora', description: 'Piloter vos projets depuis l’étranger' },
  ];

  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);
  /** Erreurs par champ renvoyées par le backend (422). */
  protected readonly fieldErrors = signal<Record<string, string>>({});

  protected readonly form = this.fb.nonNullable.group(
    {
      profile_type: ['', [Validators.required]],
      name: ['', [Validators.required]],
      email: ['', [Validators.required, Validators.email]],
      phone: [''],
      city: [''],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: [passwordsMatch] },
  );

  /** Profil sélectionné (pour l'état visuel des cartes). */
  protected selectProfile(value: string): void {
    this.form.controls.profile_type.setValue(value);
    this.form.controls.profile_type.markAsTouched();
  }

  /** Le champ est-il invalide et touché (validation locale) ? */
  protected invalid(
    field: 'profile_type' | 'name' | 'email' | 'phone' | 'city' | 'password' | 'password_confirmation',
  ): boolean {
    const control = this.form.controls[field];
    return control.invalid && control.touched;
  }

  /** Message d'erreur serveur pour un champ donné (ou null). */
  protected serverError(field: string): string | null {
    return this.fieldErrors()[field] ?? null;
  }

  protected submit(): void {
    if (this.submitting()) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);
    this.fieldErrors.set({});

    this.auth.register(this.form.getRawValue() as RegisterPayload).subscribe({
      next: () => {
        // Le compte est « en attente de vérification » ; l'étape de vérification
        // sera insérée en F1.3. Pour l'instant, retour à l'accueil connecté.
        void this.router.navigateByUrl('/');
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.applyError(error);
      },
    });
  }

  /** Répartit une erreur HTTP entre erreurs de champ et bandeau global. */
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
    this.formError.set('Inscription impossible pour le moment. Réessayez dans un instant.');
  }
}

/** Validateur de groupe : le mot de passe et sa confirmation doivent coïncider. */
function passwordsMatch(group: AbstractControl): ValidationErrors | null {
  const password = group.get('password')?.value;
  const confirmation = group.get('password_confirmation')?.value;
  return password === confirmation ? null : { passwordsMismatch: true };
}
