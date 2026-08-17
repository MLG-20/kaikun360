import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';

/** Vidéo de fond du héros (au plus l'un des deux champs est renseigné). */
export interface HomeHeroVideo {
  file: string | null;
  url: string | null;
}

/** Héros de l'accueil (F15.1), tel que renvoyé par `GET /home-hero`. */
export interface HomeHero {
  images: string[];
  video: HomeHeroVideo | null;
}

interface HomeHeroApi {
  images: string[];
  video: HomeHeroVideo | null;
}

/**
 * Lecture publique du héros de l'accueil (F15.1).
 *
 * Distinct de `HeroService` (F12, une image par page) : c'est le seul endroit
 * du site où l'équipe peut charger un diaporama de photos, ou une courte
 * vidéo à la place. Une vidéo, quand elle existe, REMPLACE entièrement le
 * diaporama — c'est `home-page.ts` qui applique cette priorité.
 */
@Injectable({ providedIn: 'root' })
export class HomeHeroService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  get(): Observable<HomeHero> {
    return this.http.get<{ data: HomeHeroApi }>(`${this.api}/home-hero`).pipe(
      map((res) => ({
        images: res.data.images ?? [],
        video: res.data.video ?? null,
      })),
    );
  }
}
