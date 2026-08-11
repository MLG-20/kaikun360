import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { of } from 'rxjs';
import { catchError, map, shareReplay } from 'rxjs/operators';

import { environment } from '../../../environments/environment';

/**
 * Personnalisation d'un bandeau, telle que renvoyée par `GET /heroes`.
 *
 * Chaque champ vaut `null` quand l'équipe n'a rien saisi : la page garde alors
 * le texte écrit dans son gabarit. `image` est en revanche déjà **résolue** par
 * le serveur — si elle est renseignée, c'est soit l'image propre de la page,
 * soit celle héritée de sa page parente ; le frontend n'a aucune règle
 * d'héritage à connaître.
 */
export interface HeroBanner {
  image: string | null;
  eyebrow: string | null;
  title: string | null;
  lead: string | null;
}

/**
 * Bandeaux d'en-tête des pages publiques (F12).
 *
 * L'équipe donne à chaque grande page son image de fond (et, si elle le
 * souhaite, d'autres mots) depuis le back-office — sans redéploiement. Ce
 * service va chercher **l'ensemble des bandeaux en un seul appel**, au premier
 * besoin, et le partage ensuite avec toutes les pages : un bandeau pèse quatre
 * champs, un aller-retour par page coûterait bien plus cher que la totalité.
 *
 * ⚠️ **Aucun texte n'est obligatoire côté serveur.** Une plateforme sur
 * laquelle personne n'a rien saisi renvoie une map vide, et chaque page affiche
 * exactement ce qu'elle affichait avant l'existence de cet écran. C'est la
 * garantie que le back-office ne peut pas « vider » la vitrine par omission.
 */
@Injectable({ providedIn: 'root' })
export class HeroService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * Bandeaux personnalisés, par clé.
   *
   * `shareReplay` : les douze pages publiques lisent ce signal, l'appel ne doit
   * partir qu'une fois. `catchError` : un bandeau est de la décoration — si
   * l'appel échoue, les pages retombent sur leur apparence d'origine, elles ne
   * s'affichent pas en erreur.
   */
  private readonly banners = toSignal(
    this.http.get<{ data: { heroes: Record<string, HeroBanner> } }>(`${this.api}/heroes`).pipe(
      map((res) => res.data.heroes ?? {}),
      catchError(() => of({} as Record<string, HeroBanner>)),
      shareReplay({ bufferSize: 1, refCount: false }),
    ),
    { initialValue: {} as Record<string, HeroBanner> },
  );

  /**
   * Personnalisation d'une clé de bandeau, ou `null` si la page n'a jamais été
   * touchée au back-office.
   */
  banner(key: string): HeroBanner | null {
    return this.banners()[key] ?? null;
  }
}
