import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';

/**
 * Article de la section « Actualités Kaikun » de l'accueil (F15), tel que
 * renvoyé par `GET /news`.
 *
 * `videoFile` (fichier déposé) l'emporte sur `videoUrl` (embed) : au plus l'un
 * des deux est renseigné, jamais les deux — c'est déjà tranché côté serveur.
 */
export interface NewsArticle {
  id: number;
  title: string;
  excerpt: string | null;
  body: string | null;
  image: string;
  videoFile: string | null;
  videoUrl: string | null;
  /**
   * Destination du bouton (F17), quand cette ligne est une CARTE plutôt qu'un
   * article rédigé — `body` reste alors `null`. `linkLabel` est le texte du
   * bouton ; `null` retombe sur un libellé par défaut côté template.
   */
  linkUrl: string | null;
  linkLabel: string | null;
}

interface NewsArticleApi {
  id: number;
  title: string;
  excerpt: string | null;
  body: string | null;
  image: string;
  video_file: string | null;
  video_url: string | null;
  link_url: string | null;
  link_label: string | null;
}

/**
 * Lecture publique des actualités Kaikun (F15).
 *
 * Sert la page d'accueil, qui bascule sur cette section quand elle reçoit au
 * moins un article — et retombe sur la grille des univers si la liste est
 * vide (aucun article publié pour l'instant, cas normal en tout début de vie
 * de la section).
 */
@Injectable({ providedIn: 'root' })
export class NewsService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * `discoverCardsCount` : nombre de « petites cartes » à afficher dans la
   * section « À découvrir » de l'accueil (F17, demande client 2026-08-28),
   * pilotable au back-office (réglage `home.discover_cards_count`) — n'est
   * plus figé à 4 dans le composant.
   */
  list(): Observable<{ articles: NewsArticle[]; discoverCardsCount: number }> {
    return this.http
      .get<{ data: { articles: NewsArticleApi[]; discoverCardsCount: number } }>(`${this.api}/news`)
      .pipe(
        map((res) => ({
          articles: (res.data.articles ?? []).map((a) => this.depuisApi(a)),
          discoverCardsCount: res.data.discoverCardsCount ?? 4,
        })),
      );
  }

  /**
   * GET /news/{id} — détail d'un article publié, pour la page dédiée. Renvoie
   * 404 si l'article n'existe pas ou n'est pas publié (à traiter côté
   * appelant comme un état « introuvable »).
   */
  get(id: number): Observable<NewsArticle> {
    return this.http
      .get<{ data: { article: NewsArticleApi } }>(`${this.api}/news/${id}`)
      .pipe(map((res) => this.depuisApi(res.data.article)));
  }

  private depuisApi(a: NewsArticleApi): NewsArticle {
    return {
      id: a.id,
      title: a.title,
      excerpt: a.excerpt,
      body: a.body,
      image: a.image,
      videoFile: a.video_file,
      videoUrl: a.video_url,
      linkUrl: a.link_url,
      linkLabel: a.link_label,
    };
  }
}
