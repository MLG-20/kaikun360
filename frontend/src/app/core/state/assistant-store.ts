import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, effect, inject, signal } from '@angular/core';
import { Router } from '@angular/router';

import {
  AssistantAction,
  AssistantItem,
  AssistantService,
  AssistantTurn,
} from '../api/assistant.service';
import { MessageService } from '../api/message.service';
import { AuthService } from '../auth/auth.service';
import { spaceHomeFor } from '../auth/space-home';

/**
 * Longueur maximale d'un message, **miroir de `config('assistant.limits.
 * message_length')`**. On la fait respecter ici pour que la personne le voie
 * pendant qu'elle écrit, plutôt que de lui rendre un 422 après l'envoi. Le
 * serveur reste l'autorité : ce compteur est un confort, pas une garde.
 */
export const ASSISTANT_MAX_LENGTH = 500;

/**
 * Nombre de tours d'historique renvoyés au serveur, miroir de
 * `config('assistant.limits.history_turns')`.
 *
 * ⚠️ Le serveur tronque de toute façon aux N derniers tours. Le faire aussi ici
 * n'est pas une redondance inutile : c'est ce qui évite d'envoyer une
 * conversation d'une heure à chaque message — donc, dès le driver Claude
 * (F10.4), de payer des tokens que le serveur jettera.
 */
export const ASSISTANT_HISTORY_TURNS = 10;

/** Auteur d'une bulle affichée dans le panneau. */
export type AssistantSpeaker = 'user' | 'assistant';

/**
 * Une bulle de la conversation.
 *
 * Elle porte plus qu'un texte : les **fiches** et les **boutons** qui
 * accompagnaient la réponse restent attachés à leur bulle, et non à un état
 * global « dernière réponse ». Sans cela, faire défiler la conversation vers le
 * haut afficherait des résultats sous une réponse qui ne les a pas produits.
 */
export interface AssistantMessage {
  /** Clé de rendu stable (`@for … track`). */
  id: number;
  speaker: AssistantSpeaker;
  text: string;
  items: AssistantItem[];
  actions: AssistantAction[];
  /** Bulle d'excuse produite localement (erreur réseau, 429, 503). */
  failed?: boolean;
}

/**
 * Message d'accueil, produit **localement, sans appel réseau**.
 *
 * Ouvrir le panneau ne doit rien coûter : ni un aller-retour serveur, ni — dès
 * F10.4 — un appel facturé pour dire bonjour. La salutation wolof est celle de
 * la marque (elle reprend `RuleBasedBrain::greeting`), la conversation se
 * poursuit en français.
 */
const ACCUEIL = "Dalal ak diam 👋 Je suis l'assistant Kaikun 360. "
  + 'Dites-moi ce que vous cherchez — un bien, un hébergement, un circuit, un véhicule — '
  + 'ou posez-moi une question sur le fonctionnement de la plateforme.';

/**
 * L'état PARTAGÉ de la conversation avec l'assistant (F10.1).
 *
 * **Pourquoi un store `root` et pas l'état interne du panneau.** Le panneau est
 * monté dans les layouts (site public, quatre espaces connectés) : passer d'une
 * page à l'autre à l'intérieur d'un layout le préserve, mais passer du site
 * public à son espace le détruit — et la conversation avec lui. Or c'est
 * exactement le parcours que l'assistant provoque : il propose un lien, on
 * clique, on revient. Perdre le fil à ce moment-là rendrait l'outil inutilisable.
 *
 * **Rien n'est écrit sur le disque du navigateur**, à la différence de
 * `CompareStore` ou `BookingIntentStore`. Une conversation contient ce que la
 * personne a tapé — parfois un problème personnel, un litige, un budget. Ça n'a
 * pas à survivre à la fermeture de l'onglet sur une machine partagée. Le prix à
 * payer est assumé : un rechargement complet repart d'une page blanche.
 *
 * ⚠️ **L'assistant ne s'ouvre jamais tout seul.** Un panneau qui se déploie sans
 * qu'on l'ait demandé est la première chose que les gens ferment — et la
 * deuxième, c'est l'onglet.
 */
@Injectable({ providedIn: 'root' })
export class AssistantStore {
  private readonly api = inject(AssistantService);
  private readonly messages = inject(MessageService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  private readonly conversation = signal<AssistantMessage[]>([]);
  private readonly ouvert = signal(false);
  private readonly enCours = signal(false);
  private readonly coupe = signal(false);

  /** Compteur de clés de rendu — jamais un index de tableau (il se décale). */
  private prochaineCle = 0;

  /** La conversation affichée, accueil compris. */
  readonly bulles = this.conversation.asReadonly();

  /** Le panneau est-il déployé ? */
  readonly estOuvert = this.ouvert.asReadonly();

  /** Une réponse est-elle attendue ? (indicateur de saisie, envoi verrouillé) */
  readonly attenteReponse = this.enCours.asReadonly();

  /**
   * L'assistant s'est-il déclaré indisponible (HTTP 503) ?
   *
   * ⚠️ **La bulle disparaît alors pour toute la session.** `ASSISTANT_ENABLED`
   * est l'interrupteur d'urgence du module : si le client coupe l'assistant, ou
   * si l'on doit l'éteindre en incident, laisser un bouton qui répond « je suis
   * indisponible » à chaque clic est pire que pas de bouton du tout.
   */
  readonly indisponible = this.coupe.asReadonly();

  /** Y a-t-il autre chose que l'accueil ? (utile aux tests et au libellé) */
  readonly aConverse = computed(() => this.conversation().some((b) => b.speaker === 'user'));

  constructor() {
    // Une déconnexion efface la conversation : sur un poste partagé, le compte
    // suivant ne doit pas retrouver les questions du précédent. Une CONNEXION
    // l'efface aussi — la trousse à outils change côté serveur, et les boutons
    // déjà affichés (« Nous écrire » plutôt que « Écrire à un conseiller »)
    // ne correspondent plus à qui l'on est.
    //
    // ⚠️ L'état de référence est lu ICI, à la construction, et non au premier
    // passage de l'effet : les effets ne s'exécutent qu'au prochain cycle de
    // détection, et faire de leur première exécution un cas particulier rendait
    // le comportement dépendant de l'instant où ce cycle survient.
    let precedent = this.auth.isAuthenticated();
    effect(() => {
      const connecte = this.auth.isAuthenticated();
      if (precedent !== connecte) {
        this.reinitialiser();
      }
      precedent = connecte;
    });
  }

  /** Déploie le panneau (et pose l'accueil au premier passage). */
  ouvrir(): void {
    if (this.conversation().length === 0) {
      this.pousser('assistant', ACCUEIL);
    }
    this.ouvert.set(true);
  }

  fermer(): void {
    this.ouvert.set(false);
  }

  basculer(): void {
    this.ouvert() ? this.fermer() : this.ouvrir();
  }

  /** Vide la conversation (déconnexion, ou geste explicite « recommencer »). */
  reinitialiser(): void {
    this.conversation.set([]);
    this.enCours.set(false);
  }

  /**
   * Envoie un message et attend la réponse.
   *
   * Deux verrous avant tout appel : on n'envoie ni du vide, ni pendant qu'une
   * réponse est en route. Le second n'est pas cosmétique — sans lui, trois
   * clics rapides consomment le quota de 12 messages/minute et l'utilisateur
   * se retrouve bloqué par sa propre impatience.
   */
  envoyer(texte: string): void {
    const message = texte.trim().slice(0, ASSISTANT_MAX_LENGTH);

    if (message === '' || this.enCours() || this.coupe()) {
      return;
    }

    // L'historique se lit AVANT d'ajouter le message courant : celui-ci part
    // dans le champ `message`, l'y répéter le compterait deux fois.
    const historique = this.historique();

    this.pousser('user', message);
    this.enCours.set(true);

    this.api.send(message, historique).subscribe({
      next: (reponse) => {
        this.pousser('assistant', reponse.text, reponse.items, reponse.actions);
        this.enCours.set(false);
      },
      error: (erreur: unknown) => {
        this.enCours.set(false);
        this.echouer(erreur);
      },
    });
  }

  /**
   * Exécute le geste proposé par un bouton de réponse.
   *
   * ⚠️ **C'est ici que le principe « l'assistant propose, il n'écrit pas »
   * devient réel.** Aucun de ces gestes n'invente d'appel : `support` passe par
   * `POST /messages/support`, l'endpoint métier avec ses règles (agent de
   * permanence, reprise d'un fil existant, réouverture) ; `contact` et `link`
   * ne font que naviguer.
   */
  executer(action: AssistantAction): void {
    switch (action.kind) {
      case 'link':
        this.naviguer(action.payload['url']);
        break;

      case 'contact':
        void this.router.navigateByUrl('/contact');
        this.fermer();
        break;

      case 'support':
        this.ouvrirUnFil(action);
        break;
    }
  }

  // ==========================================================================
  // Interne
  // ==========================================================================

  /**
   * Navigation vers un chemin **interne**, et rien d'autre.
   *
   * Le serveur ne construit que des chemins internes (`AssistantAction::link`
   * le documente), mais on ne se contente pas de le croire : à partir de F10.4,
   * c'est un modèle de langage qui alimentera ces réponses, et une URL sortante
   * affichée sous la marque Kaikun est un vecteur d'hameçonnage tout trouvé.
   * D'où le double contrôle — un seul slash initial, ce qui écarte aussi bien
   * `https://…` que la forme protocole-relative `//evil.test`.
   */
  private naviguer(url: unknown): void {
    if (typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//')) {
      return;
    }

    void this.router.navigateByUrl(url);
    this.fermer();
  }

  /**
   * Ouvre (ou reprend) un fil de support, puis y conduit.
   *
   * ⚠️ La destination est résolue par `spaceHomeFor` : le site a **cinq**
   * espaces connectés (quatre profils + le back-office) et chacun a SA
   * messagerie. C'est le pendant frontend de `SpaceLink` côté serveur.
   */
  private ouvrirUnFil(action: AssistantAction): void {
    const body = typeof action.payload['body'] === 'string' ? action.payload['body'] : '';
    const subject =
      typeof action.payload['subject'] === 'string' ? action.payload['subject'] : undefined;

    // Sans compte, il n'y a pas de messagerie : on n'appelle pas pour récolter
    // un 401, on renvoie au formulaire de contact public.
    if (!this.auth.isAuthenticated() || body === '') {
      void this.router.navigateByUrl('/contact');
      this.fermer();
      return;
    }

    this.enCours.set(true);

    this.messages.startWithSupport({ body, subject }).subscribe({
      next: (enveloppe) => {
        this.enCours.set(false);
        const espace = spaceHomeFor(this.auth.user());
        void this.router.navigateByUrl(`${espace}/messages/${enveloppe.data.conversation.id}`);
        this.fermer();
      },
      error: () => {
        this.enCours.set(false);
        this.pousser(
          'assistant',
          "Je n'ai pas réussi à ouvrir la discussion. Vous pouvez nous écrire depuis la page Contact.",
          [],
          [{ kind: 'contact', label: 'Nous écrire', payload: {} }],
          true,
        );
      },
    });
  }

  /**
   * Traduit un échec HTTP en une phrase compréhensible.
   *
   * Chaque statut a un sens différent pour la personne en face, et les
   * confondre serait mentir : « réessayez » n'a aucun sens si l'assistant est
   * coupé, et « indisponible » est faux quand on a simplement écrit trop vite.
   */
  private echouer(erreur: unknown): void {
    const statut = erreur instanceof HttpErrorResponse ? erreur.status : 0;

    if (statut === 503) {
      // L'assistant est éteint : on le dit une fois, puis la bulle disparaît.
      this.coupe.set(true);
      this.pousser(
        'assistant',
        "L'assistant est momentanément indisponible. L'équipe Kaikun reste joignable depuis la page Contact.",
        [],
        [{ kind: 'contact', label: 'Nous écrire', payload: {} }],
        true,
      );
      return;
    }

    if (statut === 429) {
      this.pousser(
        'assistant',
        'Vous écrivez plus vite que je ne réponds 🙂 Patientez une minute avant de renvoyer un message.',
        [],
        [],
        true,
      );
      return;
    }

    this.pousser(
      'assistant',
      "Je n'ai pas pu répondre : la connexion a échoué. Réessayez dans un instant.",
      [],
      [],
      true,
    );
  }

  /** Ajoute une bulle à la conversation. */
  private pousser(
    speaker: AssistantSpeaker,
    text: string,
    items: AssistantItem[] = [],
    actions: AssistantAction[] = [],
    failed = false,
  ): void {
    this.conversation.update((bulles) => [
      ...bulles,
      { id: this.prochaineCle++, speaker, text, items, actions, failed },
    ]);
  }

  /**
   * Historique envoyé au serveur : les derniers tours, texte seul.
   *
   * L'accueil et les bulles d'excuse en sont **exclus** : ni l'un ni les autres
   * ne viennent de l'assistant réel, les renvoyer comme s'il les avait dites
   * apprendrait au modèle (F10.4) à s'excuser sans raison.
   */
  private historique(): AssistantTurn[] {
    return this.conversation()
      .filter((bulle) => !bulle.failed && bulle.text !== ACCUEIL)
      .slice(-ASSISTANT_HISTORY_TURNS)
      .map((bulle) => ({ role: bulle.speaker, text: bulle.text.slice(0, ASSISTANT_MAX_LENGTH) }));
  }
}
