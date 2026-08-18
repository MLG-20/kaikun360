import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, map, of } from 'rxjs';

import { environment } from '../../../environments/environment';

/**
 * Noms des univers affichés dans la bande défilante de l'accueil (F16.2).
 *
 * Juste les libellés — pas d'icône, pas de lien : la grille « Nos univers »
 * (section 2) porte déjà la navigation, cette bande n'est qu'un repère
 * visuel. Réutilise le catalogue `HeroCatalog` côté serveur ; l'équipe se
 * contente d'en masquer certains au back-office.
 */
@Injectable({ providedIn: 'root' })
export class UniverseStripService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  list(): Observable<string[]> {
    return this.http.get<{ data: { names: string[] } }>(`${this.api}/universe-strip`).pipe(
      map((res) => res.data.names ?? []),
      // Décoration : une panne ne doit pas empêcher le reste de l'accueil de s'afficher.
      catchError(() => of([] as string[])),
    );
  }
}
