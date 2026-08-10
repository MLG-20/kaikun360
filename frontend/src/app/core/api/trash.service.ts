import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';

/**
 * Les cinq types d'ANNONCES qui peuvent aller à la corbeille (F11.4).
 * Supprimées pour de bon au bout de 30 jours.
 */
export type TrashListingType = 'property' | 'stay' | 'vehicle' | 'experience' | 'mobility';

/**
 * Les quatre types de DOSSIERS que le client range (F11.5).
 * ⚠️ **Jamais supprimés** : ils sont partagés avec Kaikun et un partenaire, le
 * client ne fait que les retirer de sa propre vue.
 */
export type TrashRecordType = 'request' | 'booking' | 'conversation' | 'notification';

export type TrashType = TrashListingType | TrashRecordType;

/** Une ligne de corbeille — volontairement pauvre : de quoi reconnaître et décider. */
export interface TrashItem {
  type: TrashType;
  /**
   * ⚠️ **Une CHAÎNE, pas un nombre** : l'identifiant d'une notification est un
   * UUID. Le typer `number` le ramènerait à `NaN` et rendrait toutes les
   * notifications indiscernables les unes des autres.
   */
  id: string;
  /**
   * Ce que la ligne engage. C'est le seul champ qui distingue les deux
   * familles à l'écran, et il commande tout le vocabulaire :
   *   - `listing` → une annonce, avec un compte à rebours et une purge au bout ;
   *   - `record`  → un dossier masqué, que rien ne supprimera jamais.
   */
  kind: 'listing' | 'record';
  /** Intitulé lisible, calculé côté serveur (les modèles ne le portent pas pareil). */
  label: string;
  /** Quand la ligne a été rangée (suppression différée ou masquage). */
  removed_at: string;
  /**
   * Jours restants avant l'effacement définitif. `0` = aujourd'hui, pas « déjà
   * parti ». ⚠️ **`null` n'est pas une valeur manquante** : c'est l'information
   * elle-même — rien ne sera supprimé, la ligne attend d'être rappelée.
   */
  days_left: number | null;
}

export interface TrashContents {
  items: TrashItem[];
  /** Durée de conservation des annonces, dictée par le serveur — jamais en dur ici. */
  retention_days: number;
  /**
   * Le serveur a-t-il dû couper la liste ?
   *
   * ⚠️ Les dossiers masqués ne sont **jamais** purgés : sans plafond, la
   * réponse n'aurait aucune borne. L'écran doit dire qu'il en reste de plus
   * anciens plutôt que de laisser croire que la corbeille s'arrête là.
   */
  truncated: boolean;
  /** Nombre réel d'éléments rangés, plafond compris. */
  total: number;
}

/**
 * Corbeille des espaces utilisateurs (F11.4, étendue à l'espace client en F11.5).
 *
 * ⚠️ **Aucune méthode de rangement ici, et c'est voulu** : on ne met rien à la
 * corbeille depuis cet écran. Ranger reste le geste de l'écran d'origine
 * (« Mes biens », « Mes demandes », « Messages »…), qui connaît ses règles et
 * ses refus — chaque service métier porte donc son propre `hide()`. Ce service
 * ne sait que **regarder** et **restaurer**.
 */
@Injectable({ providedIn: 'root' })
export class TrashService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /me/trash — tout ce que j'ai rangé, les deux familles confondues. */
  contents(): Observable<ApiEnvelope<TrashContents>> {
    return this.http.get<ApiEnvelope<TrashContents>>(`${this.api}/me/trash`);
  }

  /**
   * POST /me/trash/{type}/{id}/restore — sort un élément de la corbeille.
   *
   * ⚠️ Les deux familles ne reviennent PAS dans le même état :
   *   - une **annonce** revient **hors ligne** (le serveur l'éteint : entre son
   *     rangement et sa restauration, le bien a pu être vendu ou le prix
   *     devenir faux) ;
   *   - un **dossier** revient **tel quel**, statut compris — il n'a jamais
   *     cessé d'exister pour Kaikun, y toucher réécrirait un contrat.
   */
  restore(type: TrashType, id: string): Observable<ApiEnvelope<{ item: TrashItem }>> {
    return this.http.post<ApiEnvelope<{ item: TrashItem }>>(
      `${this.api}/me/trash/${type}/${id}/restore`,
      {},
    );
  }
}
