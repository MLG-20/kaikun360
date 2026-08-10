import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { MessageService } from '../../../core/api/message.service';
import { pollWhileVisible } from '../../../core/state/poll-while-visible';
import { UnreadStore } from '../../../core/state/unread-store';
import { PageMeta } from '../../../core/api/pagination.model';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';
import { Conversation } from '../../../models/message.model';
import { ContactSupportComponent } from '../../../shared/components/contact-support/contact-support';
import { AccountIconComponent } from '../account-icon';
import { HideButtonComponent } from '../../../shared/components/hide-button/hide-button';

@Component({
  selector: 'app-messages-page',
  imports: [
    DatePipe,
    RouterLink,
    AccountIconComponent,
    ContactSupportComponent,
    HideButtonComponent,
  ],
  templateUrl: './messages-page.html',
  styleUrl: './messages-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Messages » (F3.7), monté sous `<espace>/messages`. **Générique** : le
 * même composant sert l'espace client (`/mon-espace`) et l'espace entreprise
 * (`/espace-entreprise`, F6, cahier §5 « Messages = Tous »). Le préfixe des
 * liens vers les fils est dérivé de `SPACE_CONFIG` (jamais codé en dur), de
 * sorte qu'aucun lien ne renvoie l'utilisateur hors de son espace.
 *
 * Liste paginée des conversations (`GET /messages`, 15/page, les plus actives
 * d'abord), avec le total de messages non lus joint aux métadonnées. Chaque
 * ligne mène au fil correspondant, où l'on lit et répond.
 *
 * ⚠️ F8.12 : l'écran porte désormais le bloc « Écrire au support »
 * (`app-contact-support`). Jusque-là, la messagerie n'avait **aucun geste
 * d'ouverture** — ni ici, ni ailleurs : tous les fils visibles venaient du
 * seeder, et l'état vide décrivait un bouton qui n'existait pas.
 */
export class MessagesPageComponent {
  private readonly messages = inject(MessageService);
  /** Compteur partagé avec la pastille du rail (F8.13). */
  private readonly unread = inject(UnreadStore);

  /** Préfixe des liens vers un fil (`<espace>/messages`), propre à l'espace courant. */
  protected readonly messagesBase = `${inject(SPACE_CONFIG).basePath}/messages`;

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<Conversation[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  /**
   * Total de non-lus, tous fils confondus. Tenu par l'état partagé (F8.13) : la
   * pastille du rail et ce compteur ne peuvent pas diverger, et la relève
   * ci-dessous éteint la pastille dès qu'un fil a été lu ailleurs.
   */
  protected readonly unreadCount = this.unread.messages;

  // — Rangement dans la corbeille (F11.5) —
  /** Fil dont le rangement est en vol (endort son seul bouton, pas la liste). */
  protected readonly hidingId = signal<number | null>(null);
  /** Issue du dernier rangement, affichée au-dessus de la liste. */
  protected readonly hidden = signal<string | null>(null);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);

    // F8.12.a — la liste se tient à jour seule (30 s), sans état de chargement
    // ni défilement : on attend une réponse sur cet écran, elle doit arriver
    // sans rechargement manuel.
    pollWhileVisible(() => this.load(this.meta()?.current_page ?? 1, true), 30_000);
  }

  /** Charge une page de conversations (remplace la liste affichée). */
  protected load(page: number, silencieux = false): void {
    if (!silencieux) {
      this.loading.set(true);
      this.loadError.set(false);
    }
    this.messages.myConversations(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.unread.setMessages(res.unread_count);
        this.loading.set(false);
        // Le défilement en haut appartient à la navigation voulue par
        // l'utilisateur : une relève ne doit pas déplacer sa page sous ses yeux.
        if (!silencieux && typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        if (!silencieux) {
          this.loading.set(false);
          this.loadError.set(true);
        }
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
  /**
   * Range un fil ENTIÈREMENT LU dans la corbeille (F11.5).
   *
   * ⚠️ **Le fil n'est ni supprimé ni clos** : il quitte ma seule liste. L'agent
   * qui le supervise continue de le voir en entier.
   *
   * ⚠️ **Ranger n'est pas se taire** : si quelqu'un écrit, le fil revient tout
   * seul — c'est le serveur qui s'en charge. La relève périodique de cet écran
   * (30 s) le fera donc réapparaître sans rien faire de plus ici.
   */
  protected hide(conversation: Conversation): void {
    this.hidingId.set(conversation.id);
    this.hidden.set(null);

    this.messages.hide(conversation.id).subscribe({
      next: () => {
        this.items.update((list) => list.filter((c) => c.id !== conversation.id));
        this.hidingId.set(null);
        this.hidden.set(
          'Discussion rangée dans votre corbeille. Elle revient si quelqu’un vous écrit.',
        );
      },
      error: () => {
        this.hidingId.set(null);
        this.hidden.set(
          'Cette discussion ne peut pas être rangée — ouvrez-la d’abord, elle contient des messages non lus.',
        );
      },
    });
  }

  protected counterpartLabel(conversation: Conversation): string {
    const names = conversation.counterparts.map((c) => c.name).filter(Boolean);
    if (names.length === 0) {
      return 'Conversation';
    }
    return names.join(', ');
  }
}
