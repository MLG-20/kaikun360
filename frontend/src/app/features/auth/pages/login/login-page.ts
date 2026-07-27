import { HttpErrorResponse } from '@angular/common/http';
import {
  AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  inject,
  NgZone,
  signal,
  viewChild,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';
import { GoogleIdentityService } from '../../../../core/auth/google-identity.service';
import { spaceHomeFor } from '../../../../core/auth/space-home';
import { PasswordRevealDirective } from '../../../../shared/directives/password-reveal.directive';

/**
 * Page de connexion (F1.1).
 *
 * Formulaire réactif : identifiant (e-mail ou téléphone) + mot de passe. En cas
 * de succès, `AuthService` ouvre la session et on redirige vers l'URL demandée
 * (`?redirect=`, posée par les guards) ou, à défaut, **l'espace de l'utilisateur**
 * (`spaceHomeFor`) plutôt que l'accueil public. Le backend
 * renvoie **422** pour des identifiants invalides : le message est affiché dans
 * un bandeau, sans quitter la page.
 */
@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, PasswordRevealDirective],
  templateUrl: './login-page.html',
  styleUrl: './login-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginPageComponent implements AfterViewInit {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly google = inject(GoogleIdentityService);
  private readonly zone = inject(NgZone);

  /** La connexion Google est-elle proposée (identifiant client configuré) ? */
  protected readonly googleEnabled = this.google.isEnabled;
  /** Emplacement où Google dessine son bouton officiel. */
  private readonly googleBtn = viewChild<ElementRef<HTMLElement>>('googleBtn');

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

  /**
   * Défi de double authentification en cours (comptes admin/super_admin, F7.1.d).
   * `null` = étape identifiant/mot de passe ; sinon on affiche la saisie du code.
   */
  protected readonly twoFactor = signal<{ login: string; channel: string } | null>(null);

  /** Formulaire du second facteur : le code reçu par e-mail. */
  protected readonly codeForm = this.fb.nonNullable.group({
    code: ['', [Validators.required, Validators.minLength(6)]],
  });

  /** Après l'affichage : si Google est activé, on y dessine son bouton officiel. */
  ngAfterViewInit(): void {
    const host = this.googleBtn()?.nativeElement;
    if (host) {
      void this.google.renderButton(host, (idToken) => this.onGoogleToken(idToken));
    }
  }

  /** Un champ est-il invalide ET déjà touché (pour n'afficher l'erreur qu'alors) ? */
  protected invalid(field: 'login' | 'password'): boolean {
    const control = this.form.controls[field];
    return control.invalid && control.touched;
  }

  /**
   * Reçoit le jeton d'identité Google et ouvre la session via le backend.
   * Le callback vient d'un script externe (hors zone Angular) : on repasse dans
   * la zone pour que la navigation et l'affichage se mettent bien à jour.
   */
  private onGoogleToken(idToken: string): void {
    this.zone.run(() => {
      this.submitting.set(true);
      this.formError.set(null);

      this.auth.loginWithGoogle(idToken).subscribe({
        next: () => {
          void this.router.navigateByUrl(this.landingUrl());
        },
        error: (error: HttpErrorResponse) => {
          this.submitting.set(false);
          this.formError.set(
            error.status === 401
              ? 'La connexion Google a échoué. Réessayez.'
              : this.messageFor(error),
          );
        },
      });
    });
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
      next: (outcome) => {
        this.submitting.set(false);
        // Compte à fort privilège : bascule sur la saisie du code 2FA.
        if (outcome.kind === 'two_factor') {
          this.twoFactor.set({ login: outcome.login, channel: outcome.channel });
          return;
        }
        void this.router.navigateByUrl(this.landingUrl());
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.formError.set(this.messageFor(error));
      },
    });
  }

  /** Un champ du second facteur est-il invalide ET déjà touché ? */
  protected codeInvalid(): boolean {
    const control = this.codeForm.controls.code;
    return control.invalid && control.touched;
  }

  /** Valide le second facteur (2FA back-office) et ouvre la session. */
  protected submitCode(): void {
    const challenge = this.twoFactor();
    if (!challenge || this.submitting()) {
      return;
    }
    if (this.codeForm.invalid) {
      this.codeForm.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.auth
      .twoFactor({ login: challenge.login, code: this.codeForm.getRawValue().code })
      .subscribe({
        next: () => {
          void this.router.navigateByUrl(this.landingUrl());
        },
        error: (error: HttpErrorResponse) => {
          this.submitting.set(false);
          const body = error.error as ValidationErrorBody | null;
          this.formError.set(
            error.status === 422
              ? (body?.message ?? 'Code de vérification invalide ou expiré.')
              : 'Vérification impossible pour le moment. Réessayez.',
          );
        },
      });
  }

  /** Revient à l'étape identifiant/mot de passe (abandon du défi 2FA). */
  protected cancelTwoFactor(): void {
    this.twoFactor.set(null);
    this.codeForm.reset();
    this.formError.set(null);
  }

  /**
   * Destination après connexion : l'URL demandée avant redirection (`?redirect=`,
   * posée par les guards) si elle existe, sinon **l'espace propre à l'utilisateur**
   * (`spaceHomeFor`) plutôt que l'accueil — se connecter doit faire entrer dans
   * son espace, pas retomber sur la page publique.
   */
  private landingUrl(): string {
    return this.route.snapshot.queryParamMap.get('redirect') ?? spaceHomeFor(this.auth.user());
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
