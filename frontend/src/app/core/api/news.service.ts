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
}

interface NewsArticleApi {
  id: number;
  title: string;
  excerpt: string | null;
  body: string | null;
  image: string;
  video_file: string | null;
  video_url: string | null;
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

  list(): Observable<NewsArticle[]> {
    return this.http.get<{ data: { articles: NewsArticleApi[] } }>(`${this.api}/news`).pipe(
      map((res) =>
        (res.data.articles ?? []).map((a) => ({
          id: a.id,
          title: a.title,
          excerpt: a.excerpt,
          body: a.body,
          image: a.image,
          videoFile: a.video_file,
          videoUrl: a.video_url,
        })),
      ),
    );
  }
}
