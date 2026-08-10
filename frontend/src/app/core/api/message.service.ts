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
 * Les dossiers qu'un fil peut citer (F8.12) — miroir exact de la liste blanche
 * `App\Support\Messaging\ConversationContext` côté serveur. Tout autre slug
 * est refusé en 422 : les deux listes doivent rester alignées.
 */
export type SupportContextType =
  | 'demande'
  | 'devis'
  | 'reservation'
  | 'bien'
  | 'nuitee'
  | 'vehicule'
  | 'circuit'
  | 'trajet';

/**
 * Accès à la messagerie du client connecté (F3.7).
 *
 * Socle générique côté backend (conversations à participants + messages) :
 *   - `myConversations` liste mes fils (paginé, plus actifs d'abord) + compteur ;
 *   - `conversation` ouvre un fil (le marque lu au passage, côté serveur) ;
 *   - `sendMessage` poste un message dans un fil existant ;
 *   - `startWithSupport` ouvre (ou reprend) le fil de support, seul geste
 *     d'ouverture côté client depuis F8.12 ;
 *   - `unreadCount` ne renvoie que le compteur, alimentant `UnreadStore` (F8.13).
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

  /**
   * GET /messages/{id} — détail d'un fil (messages + marquage lu côté serveur).
   *
   * `afterId` sert à la **relève périodique** (F8.12.a) : le fil déjà affiché ne
   * redemande que les messages postérieurs à celui qu'il a en dernier. Sans lui,
   * un fil ouvert dix minutes retéléchargerait tout l'historique à chaque
   * battement. En relève, le serveur ne remet pas non plus le compteur de lecture
   * à jour s'il n'y a rien de neuf.
   */
  conversation(id: number, afterId?: number): Observable<ApiEnvelope<Conversation>> {
    return this.http.get<ApiEnvelope<Conversation>>(`${this.api}/messages/${id}`, {
      params: afterId ? { after: String(afterId) } : {},
    });
  }

  /** POST /messages/{id}/messages — envoie un message dans un fil existant. */
  sendMessage(id: number, body: string): Observable<ApiEnvelope<{ message: ConversationMessage }>> {
    return this.http.post<ApiEnvelope<{ message: ConversationMessage }>>(
      `${this.api}/messages/${id}/messages`,
      { body },
    );
  }

  /**
   * POST /messages/support — ouvre (ou reprend) un fil avec le SUPPORT (F8.12).
   *
   * ⚠️ C'est le point d'entrée de la messagerie pour tous les profils
   * connectés : **on ne désigne pas son interlocuteur**, le serveur assigne un
   * agent de permanence. Le dossier concerné (`contextType`/`contextId`) est
   * facultatif mais fortement conseillé — c'est ce qui évite à l'agent de
   * demander « de quoi parlez-vous ? » avant de pouvoir aider.
   *
   * Réécrire à propos du même dossier reprend le fil existant : le serveur ne
   * crée pas de doublon, l'appelant obtient l'identifiant du fil à ouvrir.
   */
  startWithSupport(input: {
    body: string;
    subject?: string;
    contextType?: SupportContextType;
    contextId?: number;
  }): Observable<ApiEnvelope<{ conversation: Conversation }>> {
    return this.http.post<ApiEnvelope<{ conversation: Conversation }>>(
      `${this.api}/messages/support`,
      {
        body: input.body,
        ...(input.subject ? { subject: input.subject } : {}),
        ...(input.contextType && input.contextId
          ? { context_type: input.contextType, context_id: input.contextId }
          : {}),
      },
    );
  }

  // ⚠️ `startConversation` (POST /messages, ouverture d'un fil avec un
  // destinataire désigné) a été RETIRÉE en F8.13 : plus aucun écran ne
  // l'appelait. Depuis F8.12 la route est réservée à l'équipe, et l'équipe
  // n'ouvre pas de fil — elle répond aux fils de support et y fait entrer un
  // tiers (`addSupportParticipant`). Côté client, c'est `startWithSupport`.
  // La route existe toujours côté serveur : la rebrancher ici serait le seul
  // travail à refaire si l'on veut un jour qu'un agent écrive le premier.

  /**
   * POST /messages/{id}/hide — range un fil dans MA corbeille (F11.5).
   *
   * ⚠️ **Le fil n'est ni supprimé ni clos** : le masque est posé sur ma seule
   * ligne de participation. L'agent qui supervise le fil continue de le voir
   * en entier.
   *
   * ⚠️ **Ranger n'est pas se taire** : si quelqu'un écrit, le fil revient tout
   * seul dans la liste. Le serveur refuse (422) un fil qui contient encore des
   * messages non lus.
   */
  hide(id: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.post<ApiEnvelope<{ message: string }>>(
      `${this.api}/messages/${id}/hide`,
      {},
    );
  }

  /** GET /messages/unread-count — total de messages non lus (pastille de menu). */
  unreadCount(): Observable<ApiEnvelope<{ unread_count: number }>> {
    return this.http.get<ApiEnvelope<{ unread_count: number }>>(
      `${this.api}/messages/unread-count`,
    );
  }
}
