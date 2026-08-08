import { DOCUMENT, isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, inject, signal } from '@angular/core';
import { SwUpdate } from '@angular/service-worker';

/**
 * L'événement Chromium qui précède l'installation d'une PWA.
 *
 * ⚠️ Il n'est PAS dans les typages du DOM (il n'est pas standardisé) : on le
 * décrit ici plutôt que de disperser des `any` dans le service.
 */
interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>;
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

/**
 * Vie de l'application installée (F9.0) : proposer l'installation, signaler une
 * mise à jour.
 *
 * POURQUOI CE SERVICE EXISTE
 * --------------------------
 * Un manifeste et un service worker suffisent à rendre une application
 * *installable* — ils ne suffisent pas à ce qu'elle soit *installée* ni *à
 * jour*. Deux comportements du navigateur l'expliquent, et aucun n'est
 * intuitif :
 *
 * 1. **Chrome n'installe rien tout seul.** Il émet `beforeinstallprompt` et
 *    attend qu'on lui demande. Sans cela, l'utilisateur doit trouver « Ajouter à
 *    l'écran d'accueil » dans le menu du navigateur — personne ne le fait.
 * 2. **Une nouvelle version ne s'active pas d'elle-même.** Le service worker la
 *    télécharge en arrière-plan, puis attend que **tous** les onglets du site
 *    soient fermés. Sur un téléphone où l'onglet ne se ferme jamais, un
 *    utilisateur peut rester des semaines sur une version périmée — et signaler
 *    des bugs déjà corrigés.
 *
 * ⚠️ **Tout est gardé par `isPlatformBrowser`.** La leçon de F8.7 est encore
 * fraîche : toucher `window` depuis un service instancié au rendu serveur lève
 * une `ReferenceError` **silencieuse**, visible seulement dans les journaux du
 * serveur de rendu.
 */
@Injectable({ providedIn: 'root' })
export class PwaService {
  private readonly updates = inject(SwUpdate, { optional: true });
  private readonly document = inject(DOCUMENT);
  private readonly estNavigateur = isPlatformBrowser(inject(PLATFORM_ID));

  /** L'événement mis de côté, à rejouer quand l'utilisateur clique. */
  private invitation: BeforeInstallPromptEvent | null = null;

  /**
   * Vrai quand le navigateur accepterait d'installer l'application.
   *
   * ⚠️ **Faux au rendu serveur ET au premier rendu client**, ce qui est
   * exactement ce qu'il faut : le DOM produit des deux côtés est identique, donc
   * l'hydratation ne casse pas. La bannière n'apparaît qu'ensuite, quand
   * l'événement arrive.
   */
  readonly installable = signal(false);

  /** Vrai quand une nouvelle version est prête à prendre la place. */
  readonly miseAJourPrete = signal(false);

  constructor() {
    if (!this.estNavigateur) {
      return;
    }

    this.ecouterLInvitationDInstallation();
    this.surveillerLesMisesAJour();
  }

  /**
   * Déclenche la boîte d'installation du navigateur.
   *
   * ⚠️ **L'événement ne se rejoue pas** : une fois `prompt()` appelé, il est
   * consommé, qu'on ait accepté ou refusé. On le jette donc dans les deux cas —
   * garder le bouton après un refus ne ferait qu'un bouton mort.
   */
  async installer(): Promise<void> {
    const invitation = this.invitation;

    if (!invitation) {
      return;
    }

    this.invitation = null;
    this.installable.set(false);

    await invitation.prompt();
  }

  /**
   * Active la version téléchargée et recharge la page.
   *
   * Le rechargement est **indispensable** : activer le nouveau service worker ne
   * remplace pas le JavaScript déjà en mémoire dans l'onglet.
   */
  async appliquerLaMiseAJour(): Promise<void> {
    if (!this.updates?.isEnabled) {
      return;
    }

    await this.updates.activateUpdate();
    this.document.defaultView?.location.reload();
  }

  /** Met de côté l'invitation du navigateur au lieu de la laisser s'afficher. */
  private ecouterLInvitationDInstallation(): void {
    const fenetre = this.document.defaultView;

    if (!fenetre) {
      return;
    }

    fenetre.addEventListener('beforeinstallprompt', (event: Event) => {
      // Sans ce `preventDefault`, Chrome affiche SA propre bannière, au moment
      // qu'il choisit et dans sa langue à lui.
      event.preventDefault();
      this.invitation = event as BeforeInstallPromptEvent;
      this.installable.set(true);
    });

    // Installée depuis le menu du navigateur plutôt que par notre bouton :
    // l'invitation n'a plus lieu d'être.
    fenetre.addEventListener('appinstalled', () => {
      this.invitation = null;
      this.installable.set(false);
    });
  }

  /**
   * Surveille l'arrivée d'une nouvelle version.
   *
   * ⚠️ `SwUpdate` est injecté en `optional` et testé sur `isEnabled` : en
   * développement le service worker est désactivé, et l'injecter durement
   * ferait échouer l'amorçage de toute l'application.
   */
  private surveillerLesMisesAJour(): void {
    if (!this.updates?.isEnabled) {
      return;
    }

    this.updates.versionUpdates.subscribe((evenement) => {
      if (evenement.type === 'VERSION_READY') {
        this.miseAJourPrete.set(true);
      }

      // ⚠️ Version installée introuvable (cache purgé par le navigateur, ou
      // déploiement qui a effacé les fichiers hachés d'hier) : l'application ne
      // peut plus charger ses chunks paresseux et se figerait à la première
      // navigation. Un rechargement complet la remet d'aplomb.
      if (evenement.type === 'VERSION_INSTALLATION_FAILED') {
        this.document.defaultView?.location.reload();
      }
    });
  }
}
