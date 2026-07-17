import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { MessageService } from '../../../core/api/message.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { Conversation } from '../../../models/message.model';
import { AccountIconComponent } from '../account-icon';

@Component({
  selector: 'app-messages-page',
  imports: [DatePipe, RouterLink, AccountIconComponent],
  templateUrl: './messages-page.html',
  styleUrl: './messages-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Messages » de l'espace client (F3.7), monté sous `/mon-espace/messages`.
 *
 * Liste paginée des conversations du client (`GET /messages`, 15/page, les plus
 * actives d'abord), avec le total de messages non lus joint aux métadonnées.
 * Chaque ligne mène au fil correspondant (`/mon-espace/messages/{id}`), où l'on
 * lit et répond. On ne propose pas ici de « nouveau message » libre : les
 * conversations naissent d'un contexte (contact d'un pro/du support depuis une
 * annonce ou une demande) — le fil apparaît alors dans cette liste.
 */
export class MessagesPageComponent {
  private readonly messages = inject(MessageService);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<Conversation[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly unreadCount = signal(0);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de conversations (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.messages.myConversations(page).subscribe({
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

  /** Libellé du/des correspondant(s) d'un fil (« Support Kaikun », un nom, ou plusieurs). */
  protected counterpartLabel(conversation: Conversation): string {
    const names = conversation.counterparts.map((c) => c.name).filter(Boolean);
    if (names.length === 0) {
      return 'Conversation';
    }
    return names.join(', ');
  }
}
