import { isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';

/** Clé de persistance du choix de l'utilisateur. */
const STORAGE_KEY = 'kaikun.cookies.consentement';

/** Les trois états possibles : jamais demandé, accepté, refusé. */
export type Consentement = 'inconnu' | 'accepte' | 'refuse';

/**
 * Le **consentement à la mesure d'audience** (F16, 2026-08-20).
 *
 * Kaikun 360 ne dépose que des cookies strictement nécessaires (session,
 * authentification, préférences de visite) sans demander d'accord — voir la
 * page `/pages/politique-cookies`, qui promet explicitement qu'un outil de
 * mesure d'audience « ne serait activé qu'après consentement, recueilli par
 * un bandeau dédié ». Ce service EST ce bandeau : `AnalyticsService` (même
 * dossier) n'appelle `gtag.js` que si `estAccepte()` vaut vrai.
 *
 * ⚠️ **Trois états, pas deux** : `inconnu` (jamais répondu → bandeau visible,
 * rien ne se charge), `accepte`, `refuse`. Un booléen aurait confondu
 * « n'a pas encore choisi » avec « a refusé », ce qui masquerait le bandeau à
 * la première visite sans jamais avoir posé la question.
 *
 * Sur le modèle de `CompareStore` (`core/state/compare-store.ts`) :
 * `localStorage`, lecture/écriture défensives (navigation privée, quota), et
 * rien au rendu serveur — le choix d'un visiteur ne doit jamais fuiter vers
 * celui du suivant.
 */
@Injectable({ providedIn: 'root' })
export class CookieConsentService {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  private readonly etat = signal<Consentement>(this.lire());

  /** Le visiteur a-t-il déjà répondu ? Détermine si le bandeau s'affiche. */
  readonly estDecide = computed(() => this.etat() !== 'inconnu');

  /** La mesure d'audience peut-elle se charger ? */
  readonly estAccepte = computed(() => this.etat() === 'accepte');

  accepter(): void {
    this.ecrire('accepte');
  }

  refuser(): void {
    this.ecrire('refuse');
  }

  private ecrire(valeur: Consentement): void {
    this.etat.set(valeur);

    if (!this.isBrowser) {
      return;
    }
    try {
      localStorage.setItem(STORAGE_KEY, valeur);
    } catch {
      // Navigation privée, quota, stockage refusé : le bandeau redemandera au
      // prochain chargement, ce qui est agaçant mais jamais bloquant.
    }
  }

  private lire(): Consentement {
    if (!this.isBrowser) {
      return 'inconnu';
    }
    try {
      const brut = localStorage.getItem(STORAGE_KEY);
      return brut === 'accepte' || brut === 'refuse' ? brut : 'inconnu';
    } catch {
      return 'inconnu';
    }
  }
}
