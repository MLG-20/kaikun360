import { HttpErrorResponse } from '@angular/common/http';
import {
  AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  NgZone,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';
import { RegisterPayload } from '../../../../core/auth/auth.types';
import { GoogleIdentityService } from '../../../../core/auth/google-identity.service';
import { PasswordRevealDirective } from '../../../../shared/directives/password-reveal.directive';

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
 * Un écran : choix du profil (4 casquettes métier, une par espace) puis
 * coordonnées et mot de passe. En cas de succès, `AuthService.register` ouvre la session (jeton en
 * mémoire). Le backend renvoie **422** avec des erreurs par champ (e-mail ou
 * téléphone déjà utilisés…) : elles sont affichées sous les champs concernés.
 *
 * **F8.7 — Google était absent de cet écran.** Le bouton n'existait que sur la
 * page de connexion. Or c'est à l'inscription qu'il sert le plus : « continuer
 * avec Google » évite de choisir un mot de passe de plus. Côté serveur, la même
 * route `POST /auth/google` crée le compte s'il n'existe pas encore — il n'y a
 * donc rien de neuf à brancher, seulement un bouton à poser.
 *
 * ⚠️ Un compte créé par Google n'a **pas de profil choisi** : le sélecteur de
 * casquette ci-dessus ne s'applique qu'à l'inscription par formulaire. C'est
 * assumé — le serveur retient « client » par défaut, et l'utilisateur change de
 * profil depuis son espace.
 */
@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink, PasswordRevealDirective],
  templateUrl: './register-page.html',
  styleUrl: './register-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RegisterPageComponent implements AfterViewInit {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly google = inject(GoogleIdentityService);
  private readonly zone = inject(NgZone);

  /** L'inscription Google est-elle proposée (identifiant client configuré) ? */
  protected readonly googleEnabled = this.google.isEnabled;
  /** Emplacement où Google dessine son bouton officiel. */
  private readonly googleBtn = viewChild<ElementRef<HTMLElement>>('googleBtn');

  /**
   * Les 4 profils correspondant aux espaces (valeurs = enum ProfileType backend).
   *
   * NB : « Diaspora » n'est PAS proposé ici — ce n'est pas un espace mais une
   * fonctionnalité de l'espace client (un membre de la diaspora s'inscrit comme
   * « Client » et retrouve ses projets pilotés à distance dans son espace).
   */
  protected readonly profiles: ProfileOption[] = [
    { value: 'client', icon: '🔎', label: 'Client', description: 'Rechercher, réserver, demander un service' },
    { value: 'proprietaire', icon: '🏠', label: 'Propriétaire', description: 'Déposer et gérer vos biens' },
    { value: 'prestataire', icon: '🧰', label: 'Prestataire', description: 'Proposer véhicule, circuit, BTP, guide…' },
    { value: 'entreprise', icon: '🏢', label: 'Entreprise', description: 'Demandes groupées, team building, devis' },
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

  /** Après l'affichage : si Google est activé, on y dessine son bouton officiel. */
  ngAfterViewInit(): void {
    const host = this.googleBtn()?.nativeElement;
    if (host) {
      void this.google.renderButton(host, (idToken) => this.onGoogleToken(idToken));
    }
  }

  /**
   * Reçoit le jeton d'identité Google et ouvre la session via le backend (la
   * route crée le compte s'il n'existe pas).
   *
   * Le callback vient d'un script externe, donc HORS de la zone Angular : sans
   * `zone.run`, ni la navigation ni l'affichage ne se mettraient à jour.
   */
  private onGoogleToken(idToken: string): void {
    this.zone.run(() => {
      this.submitting.set(true);
      this.formError.set(null);

      this.auth.loginWithGoogle(idToken).subscribe({
        next: () => {
          void this.router.navigateByUrl('/mon-espace');
        },
        error: (error: HttpErrorResponse) => {
          this.submitting.set(false);
          this.formError.set(
            error.status === 401
              ? 'La connexion Google a échoué. Réessayez.'
              : 'Inscription Google impossible pour le moment. Réessayez dans un instant.',
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
    this.fieldErrors.set({});

    this.auth.register(this.form.getRawValue() as RegisterPayload).subscribe({
      next: () => {
        // Le compte est « en attente de vérification » : on enchaîne directement
        // sur l'étape de vérification (F1.3).
        void this.router.navigateByUrl('/auth/verification');
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
