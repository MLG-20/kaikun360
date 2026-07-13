import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';

/**
 * Récupération de mot de passe (F1.3). Page publique en deux étapes sur un écran :
 *   1. `request` — saisir l'identifiant (e-mail ou téléphone) → un code est envoyé
 *      (réponse identique que le compte existe ou non, anti-énumération) ;
 *   2. `reset` — saisir le code reçu + le nouveau mot de passe → réinitialisation,
 *      puis retour à la connexion.
 */
@Component({
  selector: 'app-forgot-password-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './forgot-password-page.html',
  styleUrl: './forgot-password-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForgotPasswordPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Étape courante. */
  protected readonly step = signal<'request' | 'reset'>('request');
  protected readonly submitting = signal(false);
  protected readonly info = signal<string | null>(null);
  protected readonly formError = signal<string | null>(null);

  /** Étape 1 : identifiant. */
  protected readonly requestForm = this.fb.nonNullable.group({
    login: ['', [Validators.required]],
  });

  /** Étape 2 : code + nouveau mot de passe. */
  protected readonly resetForm = this.fb.nonNullable.group(
    {
      code: ['', [Validators.required]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: [passwordsMatch] },
  );

  /** Envoie la demande de code puis passe à l'étape 2. */
  protected requestCode(): void {
    if (this.submitting()) {
      return;
    }
    if (this.requestForm.invalid) {
      this.requestForm.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.auth.forgotPassword(this.requestForm.getRawValue().login).subscribe({
      next: () => {
        this.submitting.set(false);
        this.step.set('reset');
        this.info.set('Si un compte correspond, un code de réinitialisation vient d’être envoyé.');
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.formError.set(this.messageFor(error));
      },
    });
  }

  /** Réinitialise le mot de passe avec le code reçu. */
  protected resetPassword(): void {
    if (this.submitting()) {
      return;
    }
    if (this.resetForm.invalid) {
      this.resetForm.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.auth
      .resetPassword({
        login: this.requestForm.getRawValue().login,
        ...this.resetForm.getRawValue(),
      })
      .subscribe({
        next: () => {
          void this.router.navigate(['/auth/connexion'], { queryParams: { reset: 1 } });
        },
        error: (error: HttpErrorResponse) => {
          this.submitting.set(false);
          this.formError.set(this.messageFor(error));
        },
      });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | { message?: string } | null;
      return (body as { message?: string })?.message ?? 'Code invalide ou expiré.';
    }
    return 'Action impossible pour le moment. Réessayez dans un instant.';
  }
}

/** Validateur de groupe : mot de passe et confirmation doivent coïncider. */
function passwordsMatch(group: AbstractControl): ValidationErrors | null {
  const password = group.get('password')?.value;
  const confirmation = group.get('password_confirmation')?.value;
  return password === confirmation ? null : { passwordsMismatch: true };
}
