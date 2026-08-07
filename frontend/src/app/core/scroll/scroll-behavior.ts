import { ViewportScroller, isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, inject } from '@angular/core';
import { Router, Scroll } from '@angular/router';
import { filter } from 'rxjs/operators';

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

  /** Chemin de la page courante, paramètres d'URL exclus. */
  let cheminPrecedent = chemin(router.url);

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
        scroller.scrollToAnchor(ancre!);
        break;
      case 'haut':
        scroller.scrollToPosition([0, 0]);
        break;
      // 'rien' : filtre, tri ou pagination — le regard du visiteur ne bouge pas.
    }
  });
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

/** L'URL sans ses paramètres ni son ancre. */
function chemin(url: string): string {
  return url.split('?')[0].split('#')[0];
}
