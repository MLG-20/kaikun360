import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { environment } from '../../../environments/environment';
import { SKIP_ERROR_REDIRECT } from '../interceptors/error.interceptor';
import { ApiEnvelope } from './api-response.model';

/**
 * Un tour de conversation renvoyé au serveur pour lui donner le contexte.
 *
 * ⚠️ L'assistant est **sans état côté serveur** (F10.0) : l'historique voyage
 * avec la requête. C'est donc le frontend qui le porte, et c'est aussi lui qui
 * doit le borner — le serveur ne garde de toute façon que les derniers tours
 * (`AssistantMessageRequest::cleanHistory`), mais lui envoyer une conversation
 * entière ferait grossir chaque appel pour rien.
 */
export interface AssistantTurn {
  role: 'user' | 'assistant';
  text: string;
}

/**
 * Nature d'un geste proposé par l'assistant — miroir de `AssistantAction::$kind`
 * côté PHP. Le serveur PROPOSE, le panneau exécute :
 *
 *   - `link`    : naviguer vers une page de la plateforme (chemin interne) ;
 *   - `support` : ouvrir (ou reprendre) un fil avec un conseiller ;
 *   - `contact` : renvoyer au formulaire de contact public (visiteur sans compte).
 */
export type AssistantActionKind = 'link' | 'support' | 'contact';

/**
 * Bouton proposé sous une réponse.
 *
 * ⚠️ `payload` est volontairement lâche : sa forme dépend de `kind`
 * (`{ url }` pour un lien, `{ subject, body }` pour une escalade, rien pour un
 * contact). Le panneau lit ce dont il a besoin après avoir testé `kind`, plutôt
 * que de faire confiance à une forme supposée.
 */
export interface AssistantAction {
  kind: AssistantActionKind;
  label: string;
  payload: Record<string, unknown>;
}

/**
 * Une fiche de résultat.
 *
 * ⚠️ **Aucune interface stricte ici, et c'est délibéré.** Les outils renvoient
 * des fiches de formes différentes — une annonce (`titre`/`lieu`/`prix_xof`/
 * `unite`/`url`), une entrée de FAQ (`question`/`reponse`) — et la liste
 * s'allongera en F10.2 et F10.3. Un type fermé obligerait à modifier le
 * frontend à chaque outil neuf ; le panneau reconnaît donc la forme reçue.
 */
export type AssistantItem = Record<string, unknown>;

/** Réponse de l'assistant — miroir exact de `AssistantReply::toArray()`. */
export interface AssistantReply {
  text: string;
  items: AssistantItem[];
  actions: AssistantAction[];
  /** Outil ayant produit la réponse, `null` si aucun (accueil, incompréhension). */
  tool: string | null;
}

/**
 * Accès HTTP à l'assistant Kaikun (F10.1) — `POST /assistant/messages`.
 *
 * **Un seul endpoint pour toute la plateforme et tous les rôles.** Ce n'est pas
 * le frontend qui décide de ce que l'assistant sait faire : c'est le jeton
 * envoyé avec la requête qui, côté serveur, détermine la trousse à outils
 * (`ToolRegistry`). Il n'y a donc rien à paramétrer ici selon l'espace où le
 * panneau est monté — et rien à oublier de paramétrer.
 *
 * L'authentification est **facultative** : la route est publique, l'intercepteur
 * de jeton pose le `Bearer` s'il y en a un, et le serveur reconnaît l'appelant
 * via le garde `sanctum`. Un visiteur reçoit la trousse la plus pauvre, pas une
 * erreur.
 */
@Injectable({ providedIn: 'root' })
export class AssistantService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * POST /assistant/messages — envoie un message et rend la réponse.
   *
   * Les erreurs ne sont **pas** avalées ici : le store les traduit en langage
   * humain, car leur sens dépend du statut (503 = assistant coupé, 429 = trop
   * de messages, autre = incident). Les noyer dans un `catchError` maison
   * ferait perdre cette distinction.
   *
   * ⚠️ `SKIP_ERROR_REDIRECT` est INDISPENSABLE ici. Le traitement global des
   * erreurs route vers la page d'erreur dès qu'un appel répond 0 ou 5xx — or
   * l'interrupteur d'urgence de l'assistant répond **503**. Sans ce marqueur,
   * couper l'assistant éjecterait de sa page quiconque lui adresse un message :
   * un panneau facultatif ferait tomber la navigation de tout le site.
   */
  send(message: string, history: AssistantTurn[] = []): Observable<AssistantReply> {
    return this.http
      .post<ApiEnvelope<{ reply: AssistantReply }>>(
        `${this.api}/assistant/messages`,
        {
          message,
          // On n'envoie la clé que si l'on a quelque chose à dire : `history: []`
          // à chaque premier message est du bruit dans les journaux du serveur.
          ...(history.length > 0 ? { history } : {}),
        },
        { context: new HttpContext().set(SKIP_ERROR_REDIRECT, true) },
      )
      .pipe(map((res) => res.data.reply));
  }
}
