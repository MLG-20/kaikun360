import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AccountService } from '../../../core/api/account.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { AuthService } from '../../../core/auth/auth.service';
import { User } from '../../../models/user.model';

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
 *
 * **Nom affiché et photo** (F17, 2026-08-20) : la plateforme appartient au
 * client, pas à l'équipe — chaque compte back-office (pas seulement le
 * super_admin, cf. l'absence de garde de permission sur cette route) doit
 * pouvoir corriger son propre nom et déposer sa photo, sans repasser par le
 * code. `PATCH /users/me` et `POST/DELETE /users/me/avatar` existent déjà et
 * sont utilisés par la page « Mon profil » du site public
 * (`features/account/profile/profile-page.ts`) : ce bloc en reprend le même
 * patron (upload immédiat au choix de fichier, pas de bouton « envoyer »
 * séparé) plutôt que d'en inventer un second.
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

  /**
   * La personne connectée. Chargée via `account.me()` (et non le seul
   * `auth.user()` issu de la connexion) car le profil — donc `avatar_url` —
   * n'est pas forcément inclus dans la session initiale.
   */
  protected readonly user = signal<User | null>(this.auth.user());

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

  // --- Identité (nom + photo) -------------------------------------------------

  protected readonly identityForm = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
  });

  protected readonly identityBusy = signal(false);
  protected readonly identityError = signal<string | null>(null);
  protected readonly identityDone = signal(false);

  /** `photo` (personne) ou `logo` (entreprise) — sans objet ici, l'équipe
   * back-office n'a jamais de profil « entreprise », mais on reprend le même
   * accesseur que `profile-page.ts` pour rester au même défaut prudent. */
  protected readonly avatarKind = computed(() => this.user()?.profile?.avatar_kind ?? 'photo');
  protected readonly avatarUrl = computed(() => this.user()?.profile?.avatar_url ?? null);
  protected readonly avatarInitial = computed(
    () => this.user()?.name?.trim()?.charAt(0)?.toUpperCase() ?? '?',
  );
  protected readonly avatarUploading = signal(false);
  protected readonly avatarError = signal<string | null>(null);

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
    this.identityForm.controls.name.setValue(this.user()?.name ?? '');

    // `auth.user()` (session de connexion) peut ne pas porter `profile` —
    // sans lui, `avatarUrl()` resterait toujours vide. `account.me()`
    // recharge la personne connectée avec son profil complet.
    this.account.me().subscribe({
      next: (user) => {
        this.user.set(user);
        this.identityForm.controls.name.setValue(user.name ?? '', { emitEvent: false });
        this.auth.setCurrentUser(user);
      },
      error: () => {
        // Silencieux : l'écran reste utilisable avec ce que la session
        // connaissait déjà (identité en tête, formulaires d'identifiants).
      },
    });
  }

  /** Change le nom affiché. */
  protected submitIdentity(): void {
    if (this.identityBusy() || this.identityForm.invalid) {
      this.identityForm.markAllAsTouched();

      return;
    }

    this.identityBusy.set(true);
    this.identityError.set(null);
    this.identityDone.set(false);

    this.account.updateProfile({ name: this.identityForm.getRawValue().name }).subscribe({
      next: (resultat) => {
        this.identityBusy.set(false);
        this.identityDone.set(true);
        this.applyUser(resultat.user);
      },
      error: (err: { error?: ValidationErrorBody }) => {
        this.identityBusy.set(false);
        this.identityError.set(premierMessage(err) ?? 'La modification n’a pas abouti.');
      },
    });
  }

  /**
   * Dépôt immédiat dès qu'une image est choisie — même patron que
   * `profile-page.ts` : pas de type à préciser, pas de bouton « Envoyer »
   * séparé.
   */
  protected onAvatarSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file || this.avatarUploading()) {
      return;
    }

    this.avatarUploading.set(true);
    this.avatarError.set(null);

    this.account.uploadAvatar(file).subscribe({
      next: (user) => {
        this.avatarUploading.set(false);
        this.applyUser(user);
      },
      error: (error: HttpErrorResponse) => {
        this.avatarUploading.set(false);
        const body = error.error as ValidationErrorBody | null;
        this.avatarError.set(
          body?.errors?.['avatar']?.[0] ??
            body?.message ??
            'L’image n’a pas pu être envoyée. Merci de réessayer.',
        );
      },
    });
  }

  /** Retire la photo (avec confirmation : l'action est immédiate et visible). */
  protected removeAvatar(): void {
    if (this.avatarUploading()) {
      return;
    }
    if (typeof window !== 'undefined' && !window.confirm('Retirer votre photo de profil ?')) {
      return;
    }

    this.avatarUploading.set(true);
    this.avatarError.set(null);

    this.account.deleteAvatar().subscribe({
      next: (user) => {
        this.avatarUploading.set(false);
        this.applyUser(user);
      },
      error: () => {
        this.avatarUploading.set(false);
        this.avatarError.set('Le retrait a échoué. Merci de réessayer.');
      },
    });
  }

  /**
   * Range l'utilisateur renvoyé par l'API dans les DEUX états qui l'affichent
   * — l'écran lui-même et `AuthService` (l'en-tête du back-office affiche
   * aussi le nom/l'avatar de la personne connectée).
   */
  private applyUser(user: User): void {
    this.user.set(user);
    this.auth.setCurrentUser(user);
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
          this.applyUser(resultat.user);
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
