import { DatePipe } from '@angular/common';
import {
  AfterViewChecked,
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { MessageService } from '../../../core/api/message.service';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';
import { Conversation, ConversationMessage } from '../../../models/message.model';

@Component({
  selector: 'app-message-thread',
  imports: [DatePipe, FormsModule, RouterLink],
  templateUrl: './message-thread.html',
  styleUrl: './message-thread.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran d'un fil de discussion (F3.7), monté sous `<espace>/messages/{id}`.
 * **Générique** : partagé par l'espace client et l'espace entreprise (F6) ; le
 * lien de retour est dérivé de `SPACE_CONFIG` (jamais codé en dur).
 *
 * Ouvre la conversation (`GET /messages/{id}`) — ce qui la marque comme lue côté
 * serveur — affiche les messages sous forme de bulles (les miens à droite, ceux
 * du correspondant à gauche) et propose un composeur pour répondre
 * (`POST /messages/{id}/messages`). Le message envoyé est ajouté au fil sans
 * rechargement. Un fil dont on n'est pas participant renvoie 404 → écran d'erreur.
 */
export class MessageThreadComponent implements AfterViewChecked {
  private readonly messages = inject(MessageService);
  private readonly route = inject(ActivatedRoute);

  /** Lien de retour vers la liste des messages de l'espace courant (`<espace>/messages`). */
  protected readonly messagesBase = `${inject(SPACE_CONFIG).basePath}/messages`;

  /** Zone défilante des messages : on la fait défiler en bas à chaque ajout. */
  private readonly scroller = viewChild<ElementRef<HTMLElement>>('scroller');
  /** Faut-il défiler en bas au prochain cycle de rendu ? */
  private pendingScroll = false;

  /** Identifiant du fil, lu dans l'URL. */
  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly conversation = signal<Conversation | null>(null);
  protected readonly thread = signal<ConversationMessage[]>([]);

  // — Composeur —
  protected readonly draft = signal('');
  protected readonly sending = signal(false);
  protected readonly sendError = signal(false);
  /** Le brouillon contient-il un texte exploitable (non vide) ? */
  protected readonly canSend = computed(() => this.draft().trim().length > 0 && !this.sending());

  /** Titre du fil : sujet, sinon nom du/des correspondant(s). */
  protected readonly title = computed(() => {
    const conv = this.conversation();
    if (!conv) {
      return 'Conversation';
    }
    if (conv.subject) {
      return conv.subject;
    }
    const names = conv.counterparts.map((c) => c.name).filter(Boolean);
    return names.length ? names.join(', ') : 'Conversation';
  });

  constructor() {
    this.load();
  }

  ngAfterViewChecked(): void {
    if (this.pendingScroll) {
      this.pendingScroll = false;
      const el = this.scroller()?.nativeElement;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    }
  }

  /** Charge le fil (messages + marquage lu côté serveur). */
  private load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.messages.conversation(this.id).subscribe({
      next: (res) => {
        this.conversation.set(res.data);
        this.thread.set(res.data.messages ?? []);
        this.loading.set(false);
        this.pendingScroll = true;
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  /** Envoie le brouillon : ajoute le message au fil et vide le composeur. */
  protected send(): void {
    const body = this.draft().trim();
    if (!body || this.sending()) {
      return;
    }

    this.sending.set(true);
    this.sendError.set(false);
    this.messages.sendMessage(this.id, body).subscribe({
      next: (res) => {
        this.thread.update((list) => [...list, res.data.message]);
        this.draft.set('');
        this.sending.set(false);
        this.pendingScroll = true;
      },
      error: () => {
        this.sending.set(false);
        this.sendError.set(true);
      },
    });
  }
}
