import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { AppNotification } from '../../models/notification.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Page de notifications : pagination standard Laravel **enrichie** du nombre de
 * non-lues (`unread_count`), joint aux métadonnées par le contrôleur afin que le
 * frontend affiche la pastille sans second appel réseau.
 */
export interface PaginatedNotifications extends Paginated<AppNotification> {
  unread_count: number;
}

/**
 * Accès au centre de notifications du client connecté (F3.6).
 *
 * Les notifications proviennent du canal `database` de Laravel : chaque flux
 * métier (avancement d'une demande, devis reçu, réservation confirmée…) y écrit
 * une ligne. `myNotifications` liste (paginé 15/page, plus récentes d'abord) ;
 * `markAsRead`/`markAllAsRead` mettent à jour l'état de lecture ; `unreadCount`
 * ne renvoie que le compteur (pour la pastille de l'en-tête).
 *
 * Auth requise (Bearer posé par l'intercepteur). L'isolation est garantie côté
 * serveur : on n'accède jamais qu'à ses propres notifications.
 */
@Injectable({ providedIn: 'root' })
export class NotificationService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /users/me/notifications — mes notifications (paginé) + compteur non-lues. */
  myNotifications(page = 1): Observable<PaginatedNotifications> {
    return this.http.get<PaginatedNotifications>(`${this.api}/users/me/notifications`, {
      params: { page: String(page) },
    });
  }

  /** GET /users/me/notifications/unread-count — nombre de non-lues (pastille). */
  unreadCount(): Observable<ApiEnvelope<{ unread_count: number }>> {
    return this.http.get<ApiEnvelope<{ unread_count: number }>>(
      `${this.api}/users/me/notifications/unread-count`,
    );
  }

  /** PATCH /users/me/notifications/{id}/read — marque UNE notification comme lue. */
  markAsRead(
    id: string,
  ): Observable<ApiEnvelope<{ notification: AppNotification; unread_count: number }>> {
    return this.http.patch<ApiEnvelope<{ notification: AppNotification; unread_count: number }>>(
      `${this.api}/users/me/notifications/${id}/read`,
      {},
    );
  }

  /**
   * POST /users/me/notifications/{id}/hide — range une notification (F11.5).
   *
   * ⚠️ **Ne supprime rien** : la trace reste, et c'est ce qui permet de
   * répondre un jour à « quand ai-je été prévenu ? ». Le serveur refuse (422)
   * une notification NON LUE — la masquer avant de l'avoir ouverte effacerait
   * l'information sans l'avoir reçue.
   */
  hide(id: string): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.post<ApiEnvelope<{ message: string }>>(
      `${this.api}/users/me/notifications/${id}/hide`,
      {},
    );
  }

  /** PATCH /users/me/notifications/read-all — marque TOUTES mes non-lues comme lues. */
  markAllAsRead(): Observable<ApiEnvelope<{ message: string; unread_count: number }>> {
    return this.http.patch<ApiEnvelope<{ message: string; unread_count: number }>>(
      `${this.api}/users/me/notifications/read-all`,
      {},
    );
  }
}
