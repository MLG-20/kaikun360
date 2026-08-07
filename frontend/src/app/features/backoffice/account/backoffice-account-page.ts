import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AccountService } from '../../../core/api/account.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { AuthService } from '../../../core/auth/auth.service';

/**
 * **Mon compte** — identifiants de la personne connectée au back-office (F8.22).
 *
 * POURQUOI CET ÉCRAN EXISTE
 * -------------------------
 * Le back-office n'avait **aucun écran de compte**. Un super administrateur ne
 * pouvait donc ni changer son mot de passe, ni corriger son adresse de
 * connexion sans sortir vers un espace client qui, pour ce profil, n'existe pas.
 * Le compte le plus puissant de la plateforme était le seul à ne pas pouvoir
 * entretenir ses propres identifiants — et un mot de passe qu'on ne peut pas
 * changer est un mot de passe qu'on ne change jamais.
 *
 * ⚠️ **Deux formulaires séparés, jamais un seul.** Ce sont deux actes distincts,
 * avec deux conséquences distinctes : changer d'adresse déplace la serrure (la
 * récupération de compte partira ailleurs), changer de mot de passe ferme les
 * autres sessions. Les fondre ferait faire les deux à qui n'en voulait qu'un.
 *
 * ⚠️ **Le mot de passe actuel est exigé des deux côtés**, et l'écran dit
 * pourquoi : ce n'est pas une formalité, c'est ce qui empêche un poste laissé
 * déverrouillé de devenir une prise de contrôle définitive.
 */
@Component({
  selector: 'app-backoffice-account-page',
  imports: [ReactiveFormsModule],
  templateUrl: './backoffice-account-page.html',
  styleUrl: './backoffice-account-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeAccountPageComponent {
  private readonly account = inject(AccountService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  /** La personne connectée, telle que la session la connaît. */
  protected readonly user = this.auth.user;

  /** Rôle affiché en clair, pour que l'écran nomme ce qu'on protège. */
  protected readonly roleLabel = computed(() => {
    const roles = this.user()?.roles ?? [];

    if (roles.includes('super_admin')) return 'Super administrateur';
    if (roles.includes('admin')) return 'Administrateur';

    return 'Membre de l’équipe';
  });

  /**
   * ⚠️ **Le mot de passe actuel est demandé à tout le monde ici, y compris aux
   * comptes Google.** Le serveur en dispense ces derniers (leur mot de passe est
   * une chaîne aléatoire qu'ils n'ont jamais vue), mais l'API n'expose pas
   * `google_id` et l'écran ne peut donc pas savoir à qui il a affaire. Deviner
   * serait pire : demander à tort bloque un utilisateur légitime. On demande
   * donc toujours — et sur un compte à privilèges, connecter le back-office par
   * Google n'est de toute façon pas le chemin nominal (2FA e-mail obligatoire).
   */

  // --- Adresse de connexion --------------------------------------------------

  protected readonly emailForm = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    current_password: ['', [Validators.required]],
  });

  protected readonly emailBusy = signal(false);
  protected readonly emailError = signal<string | null>(null);
  protected readonly emailDone = signal<string | null>(null);

  // --- Mot de passe ----------------------------------------------------------

  protected readonly passwordForm = this.fb.nonNullable.group({
    current_password: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
  });

  protected readonly passwordBusy = signal(false);
  protected readonly passwordError = signal<string | null>(null);
  protected readonly passwordDone = signal(false);

  constructor() {
    // Le formulaire part de l'adresse actuelle : on corrige une adresse, on ne
    // la ressaisit pas de mémoire.
    this.emailForm.controls.email.setValue(this.user()?.email ?? '');
  }

  /** Change l'adresse de connexion. */
  protected submitEmail(): void {
    if (this.emailBusy() || this.emailForm.invalid) {
      this.emailForm.markAllAsTouched();

      return;
    }

    const { email, current_password } = this.emailForm.getRawValue();

    if (email === this.user()?.email) {
      this.emailError.set('Cette adresse est déjà la vôtre.');

      return;
    }

    if (!current_password) {
      this.emailError.set('Votre mot de passe actuel est obligatoire pour changer d’adresse.');

      return;
    }

    this.emailBusy.set(true);
    this.emailError.set(null);
    this.emailDone.set(null);

    this.account
      .updateProfile({ email, current_password })
      .subscribe({
        next: (resultat) => {
          this.emailBusy.set(false);
          this.emailForm.controls.current_password.reset('');
          // La session locale doit refléter la nouvelle adresse, sinon l'en-tête
          // affiche encore l'ancienne jusqu'au prochain rechargement.
          this.auth.setCurrentUser(resultat.user);
          this.emailDone.set(
            resultat.emailVerificationRequired
              ? 'Adresse mise à jour. Un code de vérification vient d’être envoyé à la nouvelle adresse.'
              : 'Adresse mise à jour.',
          );
        },
        error: (err: { error?: ValidationErrorBody }) => {
          this.emailBusy.set(false);
          this.emailError.set(premierMessage(err) ?? 'La modification n’a pas abouti.');
        },
      });
  }

  /** Change le mot de passe. */
  protected submitPassword(): void {
    if (this.passwordBusy() || this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();

      return;
    }

    const valeurs = this.passwordForm.getRawValue();

    if (valeurs.password !== valeurs.password_confirmation) {
      this.passwordError.set('La confirmation ne correspond pas au nouveau mot de passe.');

      return;
    }

    this.passwordBusy.set(true);
    this.passwordError.set(null);
    this.passwordDone.set(false);

    this.account.updatePassword(valeurs).subscribe({
      next: () => {
        this.passwordBusy.set(false);
        this.passwordForm.reset();
        this.passwordDone.set(true);
      },
      error: (err: { error?: ValidationErrorBody }) => {
        this.passwordBusy.set(false);
        this.passwordError.set(premierMessage(err) ?? 'Le changement n’a pas abouti.');
      },
    });
  }
}

/**
 * Premier message d'erreur renvoyé par le serveur.
 *
 * ⚠️ On affiche le message du SERVEUR plutôt qu'un texte générique : « Le mot de
 * passe actuel est incorrect » et « Cette adresse e-mail est déjà utilisée »
 * appellent deux gestes très différents.
 */
function premierMessage(err: { error?: ValidationErrorBody }): string | null {
  const erreurs = err?.error?.errors;

  return erreurs ? (Object.values(erreurs)[0]?.[0] ?? null) : (err?.error?.message ?? null);
}
