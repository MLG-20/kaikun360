import { Injectable, signal } from '@angular/core';

/**
 * Voile plein écran pendant un VRAI changement de page (voir
 * `scroll-behavior.ts`). Sert à masquer la remontée instantanée en haut de la
 * page de départ pendant que la page d'arrivée finit de charger — sans lui,
 * cette remontée se voyait comme « ça saute dans le héros avant d'aller sur
 * la page demandée » (retour utilisateur du 2026-08-18).
 */
@Injectable({ providedIn: 'root' })
export class RouteTransitionService {
  readonly voile = signal(false);
}
