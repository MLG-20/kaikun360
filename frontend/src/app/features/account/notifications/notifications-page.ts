import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';

import { NotificationService } from '../../../core/api/notification.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { UnreadStore } from '../../../core/state/unread-store';
import { AppNotification, NotificationCategory } from '../../../models/notification.model';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';
import { AccountIcon } from '../account-nav';
import { AccountIconComponent } from '../account-icon';

/** Préfixe des espaces client — celui pour lequel le serveur produit les `action_url`. */
const CLIENT_BASE = '/mon-espace';

@Component({
  selector: 'app-notifications-page',
  imports: [DatePipe, AccountIconComponent],
  templateUrl: './notifications-page.html',
  styleUrl: './notifications-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes notifications » (F3.6). Les notifications portant sur
 * l'utilisateur et non sur un espace, cet écran est **monté dans chaque espace**
 * (`/mon-espace/notifications`, `/espace-proprietaire/notifications`, …) : les
 * espaces sont autonomes, aucun ne renvoie vers l'écran d'un autre.
 *
 * Liste paginée des notifications « base de données » (`GET
 * /users/me/notifications`, 15/page, plus récentes d'abord), avec le nombre de
 * non-lues joint aux métadonnées.
 *
 * Chaque notification est une carte cliquable : au clic, on la marque comme lue
 * (si elle ne l'est pas) puis on navigue vers l'écran concerné — **transposé
 * dans l'espace courant** par `targetFor()`. Un bouton « Tout marquer comme lu »
 * apparaît dès qu'il reste des non-lues.
 */
export class NotificationsPageComponent {
  private readonly notifications = inject(NotificationService);
  private readonly router = inject(Router);
  /** Compteur partagé avec la cloche de l'en-tête et le rail (F8.13). */
  private readonly unread = inject(UnreadStore);
  /** Espace dans lequel cet écran est monté (client, propriétaire…). */
  private readonly space = inject(SPACE_CONFIG);
  /** Sur-titre de l'écran : le nom de l'espace courant, pas « Mon espace » en dur. */
  protected readonly spaceLabel = this.space.headerTitle;

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<AppNotification[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  /**
   * Non-lues restantes. Lu sur l'état partagé, et non tenu ici : cet écran est
   * précisément celui qui fait BAISSER le compteur, et sa pastille doit s'éteindre
   * dans le même geste que celle de la cloche (F8.13).
   */
  protected readonly unreadCount = this.unread.notifications;
  /** Notification en cours de marquage (désactive sa carte le temps de l'appel). */
  protected readonly busyId = signal<string | null>(null);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de notifications (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.notifications.myNotifications(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.unread.setNotifications(res.unread_count);
        this.loading.set(false);
        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  /** Page précédente / suivante (bornées par la pagination). */
  protected prev(): void {
    if (this.hasPrev()) {
      this.load((this.meta()?.current_page ?? 2) - 1);
    }
  }

  protected next(): void {
    if (this.hasNext()) {
      this.load((this.meta()?.current_page ?? 0) + 1);
    }
  }

  /**
   * Cible réelle d'une notification, **ramenée dans l'espace courant**.
   *
   * Les `action_url` sont produites côté serveur pour l'espace client
   * (`/mon-espace/...`). Cet écran étant monté dans plusieurs espaces, suivre le
   * lien tel quel éjecterait un propriétaire hors du sien (et, les espaces étant
   * cloisonnés par rôle, il serait aussitôt refusé). On transpose donc le lien
   * dans l'espace courant quand la rubrique visée y existe ; sinon on se
   * contente de l'accueil de l'espace, plutôt que d'envoyer vers un mur.
   */
  private targetFor(item: AppNotification): string | null {
    const url = item.action_url;
    const base = this.space.basePath;

    if (!url || !url.startsWith(CLIENT_BASE) || base === CLIENT_BASE) {
      return url ?? null;
    }

    const rest = url.slice(CLIENT_BASE.length); // ex. « /demandes », « /messages/12 »
    const section = rest.split('/')[1] ?? '';
    // Rubriques du rail réellement construites + écrans transverses de l'espace.
    const exists =
      this.space.nav.some((i) => i.ready && i.path === section) ||
      section === 'profil' ||
      section === 'notifications';

    return exists ? `${base}${rest}` : base;
  }

  /**
   * Clic sur une notification : on la marque comme lue si besoin, puis, si elle
   * porte un lien interne, on navigue vers l'écran concerné. L'ordre garantit
   * que la pastille est décrémentée même quand la navigation quitte l'écran.
   */
  protected open(item: AppNotification): void {
    const go = () => {
      const target = this.targetFor(item);
      if (target) {
        this.router.navigateByUrl(target);
      }
    };

    if (item.read) {
      go();
      return;
    }

    this.busyId.set(item.id);
    this.notifications.markAsRead(item.id).subscribe({
      next: (res) => {
        this.applyRead(item.id);
        this.unread.setNotifications(res.data.unread_count);
        this.busyId.set(null);
        go();
      },
      // Même en cas d'échec du marquage, on laisse l'utilisateur suivre le lien.
      error: () => {
        this.busyId.set(null);
        go();
      },
    });
  }

  /** Bouton « Tout marquer comme lu » (visible s'il reste des non-lues). */
  protected markAll(): void {
    this.notifications.markAllAsRead().subscribe({
      next: () => {
        this.items.update((list) => list.map((n) => ({ ...n, read: true })));
        this.unread.setNotifications(0);
      },
    });
  }

  /** Passe une notification à l'état lu dans la liste locale (immutable). */
  private applyRead(id: string): void {
    this.items.update((list) => list.map((n) => (n.id === id ? { ...n, read: true } : n)));
  }

  /** Icône (réutilise le jeu de l'espace) selon la catégorie de la notification. */
  protected icon(category: NotificationCategory): AccountIcon {
    switch (category) {
      case 'request':
      case 'quote':
        return 'inbox';
      case 'booking':
        return 'calendar';
      case 'message':
        return 'chat';
      default:
        return 'bell';
    }
  }
}
