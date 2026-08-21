import { ViewportScroller, isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, inject } from '@angular/core';
import { NavigationCancel, NavigationError, NavigationStart, Router, Scroll } from '@angular/router';
import { filter } from 'rxjs/operators';

import { RouteTransitionService } from './route-transition.service';

/**
 * Ce qu'il faut faire du défilement à l'arrivée sur une URL.
 *
 *   - `position` : restaurer une position mémorisée (retour/avant navigateur) ;
 *   - `ancre`    : aller à l'ancre demandée (`#section`) ;
 *   - `haut`     : remonter en haut (vrai changement de page) ;
 *   - `rien`     : **ne pas toucher au défilement**.
 */
export type DecisionDeDefilement = 'position' | 'ancre' | 'haut' | 'rien';

/**
 * Politique de défilement de l'application (F8.20).
 *
 * POURQUOI ELLE REMPLACE `withInMemoryScrolling`
 * ----------------------------------------------
 * La politique intégrée d'Angular remonte en haut de page à **chaque**
 * navigation. Or **filtrer un catalogue est une navigation** : les filtres
 * vivent dans les paramètres d'URL (choix assumé — un filtre qu'on ne peut pas
 * envoyer par lien n'est pas partagé), donc chaque saisie renvoyait le visiteur
 * en haut de page.
 *
 * L'effet était l'inverse de celui recherché : on règle un prix maximum, on
 * valide, et on se retrouve devant la bannière, à redéfiler jusqu'aux résultats
 * — à chaque essai, alors qu'on affine une recherche cinq ou six fois de suite.
 */
export function activerPolitiqueDeDefilement(): void {
  const platformId = inject(PLATFORM_ID);

  // Rien à faire au rendu serveur : il n'y a pas de fenêtre à faire défiler.
  if (!isPlatformBrowser(platformId)) {
    return;
  }

  const router = inject(Router);
  const scroller = inject(ViewportScroller);
  const transition = inject(RouteTransitionService);

  /** Chemin de la page courante, paramètres d'URL exclus. */
  let cheminPrecedent = chemin(router.url);

  /**
   * ⚠️ **Le tout premier `NavigationStart` ne doit JAMAIS voiler la page**
   * (bug trouvé le 2026-08-21, à l'origine d'un écran bloqué entièrement
   * vide). `router.url` vaut encore `'/'` au moment de ce premier événement,
   * quelle que soit l'URL réellement chargée (chargement direct d'une page,
   * ou lien classique `<a href>` plutôt qu'un `routerLink`) — la comparaison
   * plus bas les trouve donc TOUJOURS différentes et voile la page dès le
   * démarrage. Le voile ne se lève ensuite que sur l'événement `Scroll`
   * suivant, qui peut manquer ou arriver hors délai pendant l'hydratation :
   * la page reste alors couverte indéfiniment par un rectangle uni, sans
   * la moindre erreur visible. Seule une VRAIE navigation ultérieure — un
   * clic interne, une fois l'application démarrée — doit déclencher le
   * voile ; jamais le chargement initial.
   */
  let premiereNavigation = true;

  // ==========================================================================
  // Remonter AU DÉPART d'un vrai changement de page
  //
  // ⚠️ Ne pas confondre avec le `Scroll` plus bas : celui-ci arrive trop tard.
  // Symptôme observé, chronomètre à l'appui, en quittant une page longue :
  //
  //   +150 ms  scrollY=514  page=1416px  0 carte   ← on regarde le FOOTER
  //   +400 ms  scrollY= 19  page=2451px  20 cartes
  //
  // La page d'arrivée s'affiche d'abord VIDE — ses données ne sont pas là — donc
  // beaucoup plus courte. Le navigateur écrête alors la position de défilement au
  // nouveau maximum : parti de 4188, on se retrouve à 514, c'est-à-dire tout en
  // bas de cette page provisoire. Le footer, en somme. Ce n'est qu'ensuite que le
  // `Scroll` remonte — un quart de seconde ici, bien davantage sur un téléphone
  // ou un réseau lent, assez pour qu'on croie avoir été envoyé dans le footer.
  //
  // Remonter dès `NavigationStart` supprime la fenêtre de tir : on est déjà en
  // haut quand la page d'arrivée se peint. Le décalage se voit alors sur la page
  // que l'on QUITTE, qui disparaît dans la foulée.
  //
  // ⚠️ Et **seulement quand le chemin change** : c'est la même règle qu'en bas,
  // pour la même raison — un filtre de catalogue est une navigation, et personne
  // ne veut être renvoyé en haut à chaque saisie.
  //
  // ⚠️ **Voile de transition** (retour utilisateur du 2026-08-18) : cette
  // remontée instantanée reste VISIBLE le temps que la page d'arrivée charge —
  // on clique sur une carte au fond de l'accueil et on voit l'écran sauter
  // jusqu'au héros avant de partir vers la page demandée. `transition.voile`
  // masque exactement cette fenêtre ; il retombe une fois le `Scroll` final
  // appliqué ci-dessous, sur la page d'arrivée cette fois.
  router.events
    .pipe(filter((e): e is NavigationStart => e instanceof NavigationStart))
    .subscribe((evenement) => {
      if (premiereNavigation) {
        premiereNavigation = false;
        return;
      }

      if (chemin(evenement.url) !== chemin(router.url)) {
        transition.voile.set(true);
        remonterSansAnimation();
      }
    });

  router.events.pipe(filter((e): e is Scroll => e instanceof Scroll)).subscribe((evenement) => {
    // ⚠️ `routerEvent` peut être un `NavigationSkipped` (navigation vers l'URL
    // déjà affichée) : seul `url` est commun aux deux types possibles.
    const url = evenement.routerEvent.url;
    const cheminCourant = chemin(url);
    const memePage = cheminCourant === cheminPrecedent;
    cheminPrecedent = cheminCourant;

    // ⚠️ L'ancre est relue dans l'URL plutôt que prise sur l'événement : le
    // routeur ne la renseigne que si son `anchorScrolling` est actif, or on l'a
    // désactivé pour reprendre la décision à notre compte.
    const ancre = evenement.anchor ?? url.split('#')[1] ?? null;

    switch (deciderDefilement({ position: evenement.position, ancre, memePage })) {
      case 'position':
        scroller.scrollToPosition(evenement.position!);
        break;
      case 'ancre':
        // Seul cas qui GARDE l'animation : on a demandé une section, la glissade
        // montre le chemin parcouru. C'est un geste, pas une remise à zéro.
        scroller.scrollToAnchor(ancre!);
        break;
      case 'haut':
        remonterSansAnimation();
        break;
      // 'rien' : filtre, tri ou pagination — le regard du visiteur ne bouge pas.
    }

    // Le voile ne couvre que le cas qu'il a lui-même ouvert (`haut`, à
    // NavigationStart) : le lever inconditionnellement ici serait inoffensif
    // pour 'position'/'ancre'/'rien' (jamais levé pour eux) mais autant rester
    // explicite. Un frame d'attente laisse le navigateur peindre la page
    // d'arrivée à la bonne position AVANT de découvrir le voile — sinon on
    // verrait un sursaut résiduel d'un frame au moment même du dévoilement.
    if (transition.voile()) {
      requestAnimationFrame(() => transition.voile.set(false));
    }
  });

  // Filet de sécurité : une navigation annulée (garde qui refuse) ou en échec
  // n'émet jamais de `Scroll` — sans ce filet, le voile resterait affiché
  // indéfiniment, l'application entière paraissant figée.
  router.events
    .pipe(filter((e) => e instanceof NavigationCancel || e instanceof NavigationError))
    .subscribe(() => transition.voile.set(false));
}

/**
 * La règle, isolée de tout contexte Angular pour être vérifiable telle quelle.
 *
 * ⚠️ **L'ordre des cas est la règle elle-même** : une position mémorisée prime
 * sur tout (le visiteur revient en arrière, il veut retrouver son écran exact),
 * puis l'ancre explicitement demandée, puis le cas du vrai changement de page.
 * Le silence est réservé au dernier cas, celui des filtres.
 *
 * ⚠️ **La pagination tombe volontairement dans « rien »** : la liste se remplace
 * à la même hauteur d'écran, et remonter ferait perdre le fil de lecture
 * exactement comme pour un filtre.
 */
export function deciderDefilement(contexte: {
  position: [number, number] | null;
  ancre: string | null;
  memePage: boolean;
}): DecisionDeDefilement {
  if (contexte.position) {
    return 'position';
  }

  if (contexte.ancre) {
    return 'ancre';
  }

  return contexte.memePage ? 'rien' : 'haut';
}

/**
 * Remonte en haut **d'un coup**, sans glissade.
 *
 * ⚠️ POURQUOI ON N'UTILISE PAS `ViewportScroller` ICI. La feuille de base pose
 * `scroll-behavior: smooth` sur `html` (`styles/_base.scss`) — excellent quand
 * quelqu'un demande une section, désastreux pour une remise à zéro : le
 * navigateur ANIME alors la remontée. Partis de 4 000 px sur une page longue,
 * on descend lentement vers 0 pendant que la page d'arrivée, encore vide, se
 * révèle bien plus courte : on passe une demi-seconde garé dans son footer.
 *
 * `behavior: 'instant'` court-circuite la règle CSS pour ce seul appel — il ne
 * la désactive pas, le bouton « retour en haut » garde sa glissade, et une ancre
 * aussi.
 */
function remonterSansAnimation(): void {
  window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
}

/** L'URL sans ses paramètres ni son ancre. */
function chemin(url: string): string {
  return url.split('?')[0].split('#')[0];
}
