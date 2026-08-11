import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Subject, of } from 'rxjs';
import { catchError, map, shareReplay, startWith, switchMap } from 'rxjs/operators';

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
   * Demandes de relecture. Chaque émission relance l'appel.
   *
   * ⚠️ Sans elles, la liste ne serait lue **qu'une fois par session de
   * navigation**, et c'est le défaut qui a été constaté en recette : l'équipe
   * dépose ses dix photos au back-office, revient sur le site *dans le même
   * onglet* — et n'en voit qu'une, celle qui existait déjà au chargement de
   * l'application. Les autres n'arrivaient qu'après un rechargement complet du
   * navigateur, geste que personne n'a de raison de faire.
   *
   * ⚠️ **Un `Subject`, et surtout pas un signal passé à `toObservable`.** Ce
   * dernier n'émet que depuis un **effet**, donc au premier cycle de détection
   * et non au montage : la requête cessait de partir à la construction du
   * service — les tests l'ont montré en n'en voyant aucune — ce qui retardait le
   * bandeau et fragilisait le rendu serveur, qui doit voir l'appel partir pour
   * l'attendre.
   */
  private readonly reload$ = new Subject<void>();

  /**
   * Bandeaux personnalisés, par clé.
   *
   * `shareReplay` : les douze pages publiques lisent ce signal, l'appel ne doit
   * partir qu'une fois **par tour** — sans quoi chaque page en déclencherait un.
   * `catchError` : un bandeau est de la décoration — si l'appel échoue, les
   * pages retombent sur leur apparence d'origine, elles ne s'affichent pas en
   * erreur. ⚠️ Le `catchError` est placé DANS le `switchMap` : posé au-dehors,
   * il achèverait le flux à la première panne de réseau et plus aucun
   * rechargement ne serait possible ensuite.
   */
  private readonly banners = toSignal(
    this.reload$.pipe(
      // Le premier tour part tout seul, à la construction du service.
      startWith(undefined),
      switchMap(() =>
        this.http.get<{ data: { heroes: Record<string, HeroBanner> } }>(`${this.api}/heroes`).pipe(
          map((res) => res.data.heroes ?? {}),
          catchError(() => of({} as Record<string, HeroBanner>)),
        ),
      ),
      shareReplay({ bufferSize: 1, refCount: false }),
    ),
    { initialValue: {} as Record<string, HeroBanner> },
  );

  /**
   * Redemande les bandeaux au serveur.
   *
   * Appelé par le back-office après l'enregistrement d'un bandeau : l'agent qui
   * vient de déposer une photo voit le site à jour sans recharger la page.
   */
  refresh(): void {
    this.reload$.next();
  }

  /**
   * Personnalisation d'une clé de bandeau, ou `null` si la page n'a jamais été
   * touchée au back-office.
   */
  banner(key: string): HeroBanner | null {
    return this.banners()[key] ?? null;
  }
}
