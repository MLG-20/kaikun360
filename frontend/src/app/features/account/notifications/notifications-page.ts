import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';

import { NotificationService } from '../../../core/api/notification.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { AppNotification, NotificationCategory } from '../../../models/notification.model';
import { AccountIcon } from '../account-nav';
import { AccountIconComponent } from '../account-icon';

@Component({
  selector: 'app-notifications-page',
  imports: [DatePipe, AccountIconComponent],
  templateUrl: './notifications-page.html',
  styleUrl: './notifications-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes notifications » de l'espace client (F3.6), monté sous
 * `/mon-espace/notifications`. Liste paginée des notifications « base de
 * données » du client (`GET /users/me/notifications`, 15/page, plus récentes
 * d'abord), avec le nombre de non-lues joint aux métadonnées.
 *
 * Chaque notification est une carte cliquable : au clic, on la marque comme lue
 * (si elle ne l'est pas) puis, si elle porte un `action_url`, on navigue vers
 * l'écran concerné (demande, réservation…). Un bouton « Tout marquer comme lu »
 * apparaît dès qu'il reste des non-lues.
 */
export class NotificationsPageComponent {
  private readonly notifications = inject(NotificationService);
  private readonly router = inject(Router);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<AppNotification[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly unreadCount = signal(0);
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
        this.unreadCount.set(res.unread_count);
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
   * Clic sur une notification : on la marque comme lue si besoin, puis, si elle
   * porte un lien interne, on navigue vers l'écran concerné. L'ordre garantit
   * que la pastille est décrémentée même quand la navigation quitte l'écran.
   */
  protected open(item: AppNotification): void {
    const go = () => {
      if (item.action_url) {
        this.router.navigateByUrl(item.action_url);
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
        this.unreadCount.set(res.data.unread_count);
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
        this.unreadCount.set(0);
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
