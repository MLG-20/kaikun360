import { isPlatformBrowser } from '@angular/common';
import { DestroyRef, PLATFORM_ID, inject } from '@angular/core';

/**
 * Relève périodique d'un écran ouvert (F8.12.a).
 *
 * **Le problème résolu :** une conversation qui n'affiche un nouveau message
 * qu'après un rechargement manuel n'est pas une conversation. C'était le cas des
 * deux fils (client et back-office) : chacun chargeait le fil une fois, à
 * l'ouverture, et n'en entendait plus jamais parler.
 *
 * **Pourquoi pas de WebSocket.** Le temps réel « vrai » (Reverb, Pusher) exige un
 * **démon permanent** à surveiller, redémarrer et exposer sur un port — de
 * l'infrastructure d'exploitation, pour un canal où l'on écrit une phrase toutes
 * les deux minutes. La relève ne demande rien de plus que ce qui tourne déjà, et
 * les écrans qui l'utilisent n'auront pas à changer le jour où un vrai canal
 * poussé la remplacera : ils rechargent, peu importe qui les réveille.
 *
 * **Trois règles qui la rendent peu coûteuse :**
 *   1. **jamais côté serveur** — le SSR rend une page une fois et s'arrête ; y
 *      lancer un intervalle empêcherait la réponse de se terminer ;
 *   2. **rien quand l'onglet est caché** : `visibilityState` est vérifié à chaque
 *      battement, un onglet oublié pendant deux heures n'appelle pas 720 fois ;
 *   3. **un battement immédiat au retour sur l'onglet** — sans lui, revenir sur
 *      la page laisserait jusqu'à un intervalle entier de silence, exactement le
 *      moment où l'on veut voir ce qui est arrivé.
 *
 * L'appelant est responsable de ne redemander que le NOUVEAU (paramètre `after`
 * des deux endpoints de fil) : la relève ne doit pas retélécharger l'historique.
 *
 * ⚠️ À appeler dans un **contexte d'injection** (constructeur ou initialiseur de
 * champ d'un composant) : le nettoyage est branché sur le `DestroyRef` local, il
 * n'y a donc rien à défaire à la main.
 *
 * @param tick       ce qu'il faut refaire à chaque battement
 * @param intervalMs période, par défaut 10 s (assez vif pour une discussion,
 *                   assez lent pour rester invisible côté serveur)
 */
export function pollWhileVisible(tick: () => void, intervalMs = 10_000): void {
  const platformId = inject(PLATFORM_ID);
  const destroyRef = inject(DestroyRef);

  if (!isPlatformBrowser(platformId)) {
    return;
  }

  const battement = () => {
    if (document.visibilityState === 'visible') {
      tick();
    }
  };

  const timer = setInterval(battement, intervalMs);
  document.addEventListener('visibilitychange', battement);

  destroyRef.onDestroy(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', battement);
  });
}
