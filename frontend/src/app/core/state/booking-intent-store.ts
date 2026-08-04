import { isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, inject } from '@angular/core';

/** Les quatre univers où l'on réserve en direct (F8.10). */
export type BookingIntentKind = 'stay' | 'vehicle' | 'experience' | 'mobility';

/** Une intention de réservation mise de côté le temps d'une connexion. */
export interface BookingIntent {
  /** Univers de la fiche (une intention ne vaut que pour le sien). */
  kind: BookingIntentKind;
  /** Identifiant de la fiche, tel qu'il figure dans l'URL. */
  id: string;
  /**
   * La saisie du visiteur, telle que le formulaire de la fiche la porte. Volontairement
   * non typée par univers : **chaque univers a SA forme** (période pour une nuitée,
   * journées pour un véhicule, date de départ seule pour un circuit, nombre de places
   * pour un trajet), et ce store n'a pas à les connaître — il transporte, la fiche
   * interprète.
   */
  values: Record<string, string | number>;
  /** Horodatage du dépôt (millisecondes), pour la péremption. */
  savedAt: number;
}

/** Clé unique en `sessionStorage` : il n'y a jamais qu'une intention en cours. */
const STORAGE_KEY = 'kaikun.booking-intent';

/**
 * Au-delà d'une heure, l'intention est périmée. Une saisie retrouvée le
 * lendemain porterait des dates devenues absurdes — et un prix qui a pu changer.
 */
const TTL_MS = 3_600_000;

/**
 * Le **panier de réservation en cours** (F8.13) : ce qu'un visiteur a saisi sur
 * une fiche avant qu'on lui demande de se connecter.
 *
 * **Le problème résolu.** Les quatre fiches réservables masquaient purement et
 * simplement leur formulaire au visiteur non connecté : à la place, une phrase
 * et un bouton « Se connecter ». On demandait donc de créer un compte pour
 * découvrir un prix — le mur arrivait avant l'envie. Le formulaire est désormais
 * ouvert à tous ; c'est le bouton « Réserver » qui conduit à la connexion, et la
 * saisie attend le retour.
 *
 * **Pourquoi `sessionStorage` et pas un signal.** La connexion Google (F8.7)
 * fait **sortir de l'application** : au retour, tout l'état en mémoire a disparu.
 * `sessionStorage` survit à ce rechargement, et — à la différence de
 * `localStorage` — meurt avec l'onglet : la saisie d'un visiteur ne traîne pas
 * derrière lui sur une machine partagée.
 *
 * **Une seule intention à la fois**, et elle se **consomme** : `take()` rend la
 * saisie puis l'efface. Sans cela, revenir sur la fiche des semaines plus tard
 * réafficherait des dates oubliées, et l'on ne saurait plus si elles viennent du
 * visiteur ou d'un fantôme.
 *
 * ⚠️ Rien de sensible ne doit passer par ici : c'est du stockage navigateur en
 * clair. Des dates et un nombre de places, rien d'autre.
 */
@Injectable({ providedIn: 'root' })
export class BookingIntentStore {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  /**
   * Met une saisie de côté avant d'envoyer le visiteur se connecter. Remplace
   * l'intention précédente s'il y en avait une (il n'en réserve qu'une à la fois).
   */
  remember(kind: BookingIntentKind, id: string, values: Record<string, string | number>): void {
    if (!this.isBrowser) {
      return;
    }
    const intent: BookingIntent = { kind, id, values, savedAt: Date.now() };
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(intent));
    } catch {
      // Navigation privée, quota, stockage refusé : le panier est un confort,
      // son échec ne doit pas empêcher d'aller se connecter.
    }
  }

  /**
   * Reprend la saisie mise de côté **pour cette fiche précise**, et l'efface.
   * `null` s'il n'y en a pas, si elle visait une autre fiche, ou si elle a plus
   * d'une heure.
   */
  take(kind: BookingIntentKind, id: string): Record<string, string | number> | null {
    if (!this.isBrowser) {
      return null;
    }

    let intent: BookingIntent | null = null;
    try {
      const brut = sessionStorage.getItem(STORAGE_KEY);
      intent = brut ? (JSON.parse(brut) as BookingIntent) : null;
    } catch {
      intent = null;
    }

    if (!intent || intent.kind !== kind || intent.id !== id) {
      return null;
    }

    // Consommée dans tous les cas : périmée, elle n'a plus rien à faire là.
    this.clear();

    if (Date.now() - intent.savedAt > TTL_MS) {
      return null;
    }
    return intent.values ?? null;
  }

  /** Oublie l'intention en cours (déconnexion, réservation aboutie…). */
  clear(): void {
    if (!this.isBrowser) {
      return;
    }
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      /* voir `remember` : l'échec du stockage n'est jamais bloquant */
    }
  }
}
