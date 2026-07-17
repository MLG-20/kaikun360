import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Conversation, ConversationMessage } from '../../models/message.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Liste paginée des conversations, **enrichie** du nombre total de messages non
 * lus (`unread_count`, tous fils confondus), joint aux métadonnées par le
 * contrôleur — le frontend affiche ainsi une pastille sans second appel réseau.
 */
export interface PaginatedConversations extends Paginated<Conversation> {
  unread_count: number;
}

/**
 * Accès à la messagerie du client connecté (F3.7).
 *
 * Socle générique côté backend (conversations à participants + messages) :
 *   - `myConversations` liste mes fils (paginé, plus actifs d'abord) + compteur ;
 *   - `conversation` ouvre un fil (le marque lu au passage, côté serveur) ;
 *   - `sendMessage` poste un message dans un fil existant ;
 *   - `startConversation` ouvre un nouveau fil avec un destinataire ;
 *   - `unreadCount` ne renvoie que le compteur (pastille de menu).
 *
 * Auth requise (Bearer posé par l'intercepteur). L'isolation est garantie côté
 * serveur : on n'accède jamais qu'aux fils dont on est participant (404 sinon).
 */
@Injectable({ providedIn: 'root' })
export class MessageService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /messages — mes conversations (paginé) + total non-lus. */
  myConversations(page = 1): Observable<PaginatedConversations> {
    return this.http.get<PaginatedConversations>(`${this.api}/messages`, {
      params: { page: String(page) },
    });
  }

  /** GET /messages/{id} — détail d'un fil (messages + marquage lu côté serveur). */
  conversation(id: number): Observable<ApiEnvelope<Conversation>> {
    return this.http.get<ApiEnvelope<Conversation>>(`${this.api}/messages/${id}`);
  }

  /** POST /messages/{id}/messages — envoie un message dans un fil existant. */
  sendMessage(id: number, body: string): Observable<ApiEnvelope<{ message: ConversationMessage }>> {
    return this.http.post<ApiEnvelope<{ message: ConversationMessage }>>(
      `${this.api}/messages/${id}/messages`,
      { body },
    );
  }

  /** POST /messages — ouvre une nouvelle conversation avec un destinataire. */
  startConversation(
    recipientId: number,
    body: string,
    subject?: string,
  ): Observable<ApiEnvelope<{ conversation: Conversation }>> {
    return this.http.post<ApiEnvelope<{ conversation: Conversation }>>(`${this.api}/messages`, {
      recipient_id: recipientId,
      body,
      ...(subject ? { subject } : {}),
    });
  }

  /** GET /messages/unread-count — total de messages non lus (pastille de menu). */
  unreadCount(): Observable<ApiEnvelope<{ unread_count: number }>> {
    return this.http.get<ApiEnvelope<{ unread_count: number }>>(
      `${this.api}/messages/unread-count`,
    );
  }
}
