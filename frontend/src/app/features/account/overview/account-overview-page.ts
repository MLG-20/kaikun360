import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { AccountIconComponent } from '../account-icon';
import { ACCOUNT_NAV } from '../account-nav';

/** Une étape d'accueil (« Pour bien démarrer »), dérivée de l'état du compte. */
interface OnboardingStep {
  /** Intitulé court de l'étape. */
  title: string;
  /** Explication d'une ligne. */
  hint: string;
  /** L'étape est-elle déjà accomplie ? (coché automatiquement). */
  done: boolean;
  /** Libellé du bouton d'action (affiché tant que l'étape n'est pas faite). */
  ctaLabel: string;
  /** Route interne vers l'écran qui permet d'accomplir l'étape. */
  ctaLink: string;
}

/** Clé de mémorisation du masquage du mot de bienvenue (préférence d'affichage). */
const WELCOME_KEY = 'kaikun.account.welcomeDismissed';

/**
 * Accueil de l'espace client (F3.1, enrichi F3.7+) — « Tableau de bord ».
 *
 * Première page de l'espace personnel, pensée pour **aider la personne à
 * comprendre son espace** :
 *   - un **mot de bienvenue** explicatif (ce qu'on peut faire ici + comment
 *     naviguer), **masquable** et mémorisé (localStorage) — un lien « Besoin
 *     d'aide ? » permet de le rouvrir ;
 *   - une checklist **« Pour bien démarrer »** dont les étapes se **cochent
 *     toutes seules** à partir de l'état du compte (vérification, profil) et qui
 *     **disparaît** une fois tout accompli (pas de bruit pour les habitués) ;
 *   - les **tuiles** vers les sections de l'espace (source : `ACCOUNT_NAV`).
 *
 * Aucun appel réseau : tout est dérivé de `AuthService.user()` déjà en mémoire.
 */
@Component({
  selector: 'app-account-overview-page',
  imports: [RouterLink, AccountIconComponent],
  templateUrl: './account-overview-page.html',
  styleUrl: './account-overview-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountOverviewPageComponent {
  private readonly auth = inject(AuthService);

  /** Utilisateur connecté. */
  protected readonly user = this.auth.user;

  /** Prénom (premier mot du nom) pour une salutation courte. */
  protected readonly firstName = computed(() => (this.user()?.name ?? '').split(' ')[0] || '');

  /** Le mot de bienvenue est-il masqué ? (mémorisé entre les visites). */
  protected readonly welcomeDismissed = signal<boolean>(this.readWelcomeDismissed());

  /**
   * Compte vérifié dès qu'un canal (e-mail OU téléphone) est confirmé — même
   * règle que les formulaires auth-gated (F2.7).
   */
  protected readonly isVerified = computed(() => {
    const u = this.user();
    return !!(u?.email_verified_at || u?.phone_verified_at);
  });

  /**
   * Profil jugé « complété » quand la personne a renseigné un **téléphone** et au
   * moins un élément de **localisation** (ville, adresse ou région) — assez pour
   * des échanges et une mise en relation fluides, sans exiger tout le formulaire.
   */
  protected readonly profileComplete = computed(() => {
    const u = this.user();
    return !!u?.phone && !!(u?.city || u?.address || u?.region_id);
  });

  /** Les étapes d'accueil, cochées automatiquement selon l'état du compte. */
  protected readonly steps = computed<OnboardingStep[]>(() => [
    {
      title: 'Vérifiez votre compte',
      hint: 'Confirmez votre e-mail ou votre téléphone pour réserver et déposer en toute sécurité.',
      done: this.isVerified(),
      ctaLabel: 'Vérifier',
      ctaLink: '/auth/verification',
    },
    {
      title: 'Complétez votre profil',
      hint: 'Ajoutez votre téléphone et votre localisation pour des échanges plus fluides.',
      done: this.profileComplete(),
      ctaLabel: 'Compléter',
      ctaLink: '/mon-espace/profil',
    },
  ]);

  /** Nombre d'étapes accomplies (pour l'indicateur « x / n »). */
  protected readonly doneCount = computed(() => this.steps().filter((s) => s.done).length);

  /** Toutes les étapes sont-elles faites ? (la checklist se retire alors). */
  protected readonly allStepsDone = computed(() => this.doneCount() === this.steps().length);

  /** Tuiles = toutes les sections sauf l'accueil (path vide). */
  protected readonly sections = ACCOUNT_NAV.filter((item) => item.path !== '');

  /** Masque le mot de bienvenue et mémorise le choix. */
  protected dismissWelcome(): void {
    this.welcomeDismissed.set(true);
    this.writeWelcomeDismissed(true);
  }

  /** Rouvre le mot de bienvenue (« Besoin d'aide ? ») et oublie le masquage. */
  protected reopenWelcome(): void {
    this.welcomeDismissed.set(false);
    this.writeWelcomeDismissed(false);
  }

  /** Lit la préférence de masquage (SSR-safe : rien à lire côté serveur). */
  private readWelcomeDismissed(): boolean {
    if (typeof window === 'undefined') {
      return false;
    }
    try {
      return window.localStorage.getItem(WELCOME_KEY) === '1';
    } catch {
      return false;
    }
  }

  /** Écrit (ou efface) la préférence de masquage, sans casser si le stockage est indisponible. */
  private writeWelcomeDismissed(dismissed: boolean): void {
    if (typeof window === 'undefined') {
      return;
    }
    try {
      if (dismissed) {
        window.localStorage.setItem(WELCOME_KEY, '1');
      } else {
        window.localStorage.removeItem(WELCOME_KEY);
      }
    } catch {
      // Stockage indisponible (navigation privée, quota…) : on ignore silencieusement.
    }
  }
}
