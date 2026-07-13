import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';

/**
 * Page de connexion (F1.1).
 *
 * Formulaire réactif : identifiant (e-mail ou téléphone) + mot de passe. En cas
 * de succès, `AuthService` stocke le jeton en mémoire et on redirige vers l'URL
 * demandée (`?redirect=`, posée par l'`authGuard`) ou l'accueil. Le backend
 * renvoie **422** pour des identifiants invalides : le message est affiché dans
 * un bandeau, sans quitter la page.
 */
@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './login-page.html',
  styleUrl: './login-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  /** Requête en cours (désactive le bouton, évite les doubles envois). */
  protected readonly submitting = signal(false);
  /** Message d'erreur global (identifiants invalides, panne…). */
  protected readonly formError = signal<string | null>(null);
  /** Message de succès (ex. retour depuis la réinitialisation de mot de passe). */
  protected readonly info = this.route.snapshot.queryParamMap.has('reset')
    ? 'Mot de passe réinitialisé. Vous pouvez vous connecter.'
    : null;

  protected readonly form = this.fb.nonNullable.group({
    login: ['', [Validators.required]],
    password: ['', [Validators.required]],
  });

  /** Un champ est-il invalide ET déjà touché (pour n'afficher l'erreur qu'alors) ? */
  protected invalid(field: 'login' | 'password'): boolean {
    const control = this.form.controls[field];
    return control.invalid && control.touched;
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

    this.auth.login(this.form.getRawValue()).subscribe({
      next: () => {
        const redirect = this.route.snapshot.queryParamMap.get('redirect') ?? '/';
        void this.router.navigateByUrl(redirect);
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.formError.set(this.messageFor(error));
      },
    });
  }

  /** Traduit une erreur HTTP en message lisible. */
  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Identifiants invalides.';
    }
    // Les 0/5xx sont déjà routés vers /erreur par l'errorInterceptor ; ce message
    // couvre les autres cas inattendus.
    return 'Connexion impossible pour le moment. Réessayez dans un instant.';
  }
}
