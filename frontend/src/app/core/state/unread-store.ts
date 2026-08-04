import { isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, computed, effect, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter } from 'rxjs/operators';

import { MessageService } from '../api/message.service';
import { NotificationService } from '../api/notification.service';
import { AuthService } from '../auth/auth.service';
import { pollWhileVisible } from './poll-while-visible';

/**
 * Cadence de la relève des compteurs (F8.13). Volontairement plus lente que
 * celle d'un fil ouvert (10 s dans `pollWhileVisible`) : une pastille est un
 * signal d'attention, pas une conversation — la voir apparaître dans la minute
 * suffit, et ces deux appels partent depuis TOUTES les pages d'un espace.
 */
const CADENCE_MS = 60_000;

/**
 * État PARTAGÉ des compteurs « non lus » de l'utilisateur connecté (F8.13) :
 * notifications et messages.
 *
 * **Le problème résolu.** Ces deux compteurs étaient dans deux états opposés,
 * tous deux mauvais :
 *
 *   - les **notifications** étaient comptées dans l'en-tête lui-même, en local,
 *     et rafraîchies à la seule navigation : une notification arrivée pendant
 *     qu'on lit une page ne se signalait qu'au clic suivant, et l'écran qui les
 *     marque comme lues ne pouvait pas éteindre la pastille (il fallait naviguer
 *     pour que l'en-tête se reprenne) ;
 *   - les **messages** n'étaient comptés **nulle part** : `MessageService.
 *     unreadCount()` existait depuis F3.7 sans un seul appelant, et le rail des
 *     quatre espaces affichait une rubrique « Messages » muette. On n'apprenait
 *     qu'on avait reçu un message qu'en ouvrant l'écran — c'est-à-dire jamais.
 *
 * **Ce que la source unique apporte.** Trois réveils, un seul état :
 *   1. l'**ouverture de session** (et sa fermeture, qui remet à zéro : les
 *      compteurs du compte précédent ne doivent pas survivre à une déconnexion) ;
 *   2. chaque **navigation** dans l'application ;
 *   3. une **relève périodique** tant que l'onglet est visible.
 *
 * Et surtout : les écrans qui *font baisser* un compteur le poussent ici
 * (`setNotifications` / `setMessages`) au lieu d'attendre le prochain réveil.
 * Les deux endpoints de lecture renvoient déjà le nouveau total — la pastille
 * s'éteint donc dans le même souffle que le clic, sans appel supplémentaire.
 *
 * ⚠️ Le store est `root` : il vit aussi pendant la navigation publique. Tous ses
 * appels sont gardés par `isAuthenticated()` (aucun 401 en visiteur) et par
 * `isBrowser` (le SSR n'a pas le jeton, qui est gardé en mémoire).
 */
@Injectable({ providedIn: 'root' })
export class UnreadStore {
  private readonly notificationsApi = inject(NotificationService);
  private readonly messagesApi = inject(MessageService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  private readonly notificationsCount = signal(0);
  private readonly messagesCount = signal(0);

  /** Notifications non lues (pastille de la cloche). */
  readonly notifications = this.notificationsCount.asReadonly();
  /** Messages non lus, tous fils confondus (pastille de la rubrique « Messages »). */
  readonly messages = this.messagesCount.asReadonly();

  /** Total, pour une éventuelle surface qui ne distinguerait pas les deux. */
  readonly total = computed(() => this.notificationsCount() + this.messagesCount());

  constructor() {
    // Réveil 1 — l'état de connexion. La connexion charge, la déconnexion vide.
    effect(() => {
      if (this.auth.isAuthenticated()) {
        this.refresh();
      } else {
        this.notificationsCount.set(0);
        this.messagesCount.set(0);
      }
    });

    // Réveil 2 — la navigation. Couvre le cas où un écran a créé du non-lu
    // (envoyer un message ouvre un fil) ou en a consommé sans le pousser ici.
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.refresh());

    // Réveil 3 — la relève périodique, seule capable de signaler ce qui arrive
    // pendant qu'on reste sur la même page.
    pollWhileVisible(() => this.refresh(), CADENCE_MS);
  }

  /**
   * Recharge les deux compteurs. Silencieux en cas d'échec : une pastille est un
   * confort, une erreur réseau ne doit pas produire un message à l'écran ni
   * remettre le compteur à zéro (ce qui ferait *disparaître* un non-lu réel).
   */
  refresh(): void {
    if (!this.isBrowser || !this.auth.isAuthenticated()) {
      return;
    }
    this.notificationsApi.unreadCount().subscribe({
      next: (res) => this.notificationsCount.set(res.data.unread_count),
      error: () => {},
    });
    this.messagesApi.unreadCount().subscribe({
      next: (res) => this.messagesCount.set(res.data.unread_count),
      error: () => {},
    });
  }

  /** Compteur de notifications renvoyé par un écran qui vient d'en marquer. */
  setNotifications(count: number): void {
    this.notificationsCount.set(Math.max(0, count));
  }

  /** Compteur de messages renvoyé par un écran qui vient d'ouvrir un fil. */
  setMessages(count: number): void {
    this.messagesCount.set(Math.max(0, count));
  }
}
