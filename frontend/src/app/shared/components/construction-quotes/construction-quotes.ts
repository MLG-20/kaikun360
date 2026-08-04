import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';

import {
  ClientConstructionRequest,
  ConstructionQuote,
  ConstructionService,
  QUOTE_UNIT_LABELS,
} from '../../../core/api/construction.service';
import { formatFcfa } from '../catalog/catalog.config';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';

/** État de chargement du bloc. */
type LoadState = 'loading' | 'ready' | 'error';

/**
 * Bloc « Mes chantiers & devis » de l'espace client (F3.9).
 *
 * **Le chaînon qui manquait au cycle des devis de chantier.** Depuis F7.3.e2
 * l'équipe compose un devis ventilé par lot et l'envoie ; le client, lui,
 * n'avait aucun écran pour y répondre. Le dossier restait bloqué en « devis
 * envoyé » sans que personne ne comprenne pourquoi — la moitié cliente d'un
 * cycle entièrement construit côté back-office.
 *
 * Composant **autonome** : il charge lui-même `GET /construction-requests/mine`
 * (devis inclus, en un seul appel). C'est délibéré — il est monté dans la
 * rubrique « Projets diaspora », et devra pouvoir l'être ailleurs le jour où
 * l'espace client gagne une rubrique « Mes chantiers », sans rien réécrire ni
 * faire porter le chargement au parent.
 *
 * Deux partis pris d'interface :
 *   - **accepter demande une confirmation** (le bouton se dédouble avant
 *     d'agir). Accepter un devis est un engagement financier, pas un « j'aime » :
 *     un clic malheureux sur un téléphone ne doit pas engager des millions de
 *     francs. Refuser passe par la même étape, par symétrie et parce qu'un refus
 *     accidentel ferait repartir l'équipe sur un devis révisé pour rien ;
 *   - **le détail des lots est replié par défaut**. Le client veut d'abord le
 *     total et la date de validité ; le détail ligne à ligne est là pour qui
 *     vérifie, pas pour tout le monde.
 */
@Component({
  selector: 'app-construction-quotes',
  imports: [DatePipe, RouterLink],
  templateUrl: './construction-quotes.html',
  styleUrl: './construction-quotes.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConstructionQuotesComponent {
  private readonly construction = inject(ConstructionService);

  /**
   * Espace dans lequel ce bloc est monté (F8.14). Le règlement d'un devis
   * accepté vit dans l'espace COURANT : écrire `/mon-espace` en dur casserait
   * le jour où ce bloc serait monté ailleurs — et les espaces sont cloisonnés
   * par rôle, l'utilisateur y serait refoulé.
   */
  private readonly space = inject(SPACE_CONFIG);
  protected readonly bookingsBase = this.space.basePath + '/reservations';

  protected readonly state = signal<LoadState>('loading');
  protected readonly requests = signal<ClientConstructionRequest[]>([]);

  /** Devis dont le détail des lots est déplié (identifiants). */
  protected readonly expanded = signal<ReadonlySet<number>>(new Set());

  /**
   * Devis en attente de confirmation, et dans quel sens.
   * `null` = aucune confirmation en cours.
   */
  protected readonly pendingConfirm = signal<{ id: number; action: 'accept' | 'refuse' } | null>(
    null,
  );

  /** Devis en cours d'envoi au serveur (bouton verrouillé). */
  protected readonly submitting = signal<number | null>(null);

  /** Message d'erreur d'une action (affiché sur le devis concerné). */
  protected readonly actionError = signal<{ id: number; message: string } | null>(null);

  protected readonly fcfa = formatFcfa;
  protected readonly unitLabels = QUOTE_UNIT_LABELS;

  constructor() {
    this.load();
  }

  /** Charge les chantiers du client (devis inclus). */
  protected load(): void {
    this.state.set('loading');
    this.construction.mine().subscribe({
      next: (res) => {
        this.requests.set(res.data);
        this.state.set('ready');
      },
      error: () => this.state.set('error'),
    });
  }

  /**
   * Y a-t-il au moins un chantier à montrer ?
   *
   * Le bloc se masque entièrement quand le client n'a aucun chantier : dans la
   * rubrique diaspora, une section « Mes chantiers » vide n'apprendrait rien et
   * ne ferait qu'allonger la page.
   */
  protected hasAnything(): boolean {
    return this.requests().length > 0;
  }

  /** Devis en attente de réponse, tous chantiers confondus (compteur d'en-tête). */
  protected awaitingCount(): number {
    return this.requests().reduce(
      (total, request) => total + (request.quotes ?? []).filter((q) => this.isAnswerable(q)).length,
      0,
    );
  }

  /** Un devis appelle-t-il une réponse du client ? */
  protected isAnswerable(quote: ConstructionQuote): boolean {
    return quote.status === 'envoye';
  }

  /**
   * Le devis a-t-il dépassé sa date de validité ?
   *
   * On le signale sans jamais bloquer le bouton : la validité est une indication
   * commerciale, et c'est au serveur — pas à l'horloge du téléphone — de dire ce
   * qui est acceptable. Un client dont le fuseau ou la date système est décalé
   * ne doit pas se retrouver privé de sa décision.
   */
  protected isExpired(quote: ConstructionQuote): boolean {
    if (!quote.valid_until) return false;

    return new Date(quote.valid_until) < new Date(new Date().toDateString());
  }

  /** Déplie / replie le détail des lots d'un devis. */
  protected toggleDetail(quoteId: number): void {
    this.expanded.update((current) => {
      const next = new Set(current);
      next.has(quoteId) ? next.delete(quoteId) : next.add(quoteId);

      return next;
    });
  }

  protected isExpanded(quoteId: number): boolean {
    return this.expanded().has(quoteId);
  }

  /** Première étape : demander confirmation. */
  protected askConfirm(quoteId: number, action: 'accept' | 'refuse'): void {
    this.actionError.set(null);
    this.pendingConfirm.set({ id: quoteId, action });
  }

  protected cancelConfirm(): void {
    this.pendingConfirm.set(null);
  }

  /** La confirmation en cours porte-t-elle sur ce devis, dans ce sens ? */
  protected isConfirming(quoteId: number, action: 'accept' | 'refuse'): boolean {
    const pending = this.pendingConfirm();

    return pending?.id === quoteId && pending.action === action;
  }

  /** Seconde étape : la décision part au serveur. */
  protected confirm(quote: ConstructionQuote, action: 'accept' | 'refuse'): void {
    this.submitting.set(quote.id);
    this.actionError.set(null);

    const call =
      action === 'accept'
        ? this.construction.acceptQuote(quote.id)
        : this.construction.refuseQuote(quote.id);

    call.subscribe({
      next: (res) => {
        this.replaceQuote(res.data.quote);
        this.pendingConfirm.set(null);
        this.submitting.set(null);
      },
      error: (err: { status?: number }) => {
        this.submitting.set(null);
        this.pendingConfirm.set(null);
        this.actionError.set({ id: quote.id, message: this.messageFor(err.status) });

        // 422 = le devis n'est plus répondable (déjà tranché, ou renvoyé par
        // l'équipe entre-temps). L'écran est périmé : on le recharge pour que le
        // client voie l'état réel plutôt qu'un bouton qui ne marchera plus.
        if (err.status === 422) {
          this.load();
        }
      },
    });
  }

  /**
   * Remplace un devis dans la liste après réponse, **sans recharger la page**.
   *
   * Le serveur renvoie le devis à jour : on l'écrit là où il était plutôt que de
   * refaire un appel. Le statut du chantier lui-même n'est pas rafraîchi — il le
   * sera au prochain chargement ; l'information qui compte pour le client à cet
   * instant est que sa réponse est enregistrée.
   */
  private replaceQuote(updated: ConstructionQuote): void {
    this.requests.update((requests) =>
      requests.map((request) =>
        request.id === updated.construction_request_id
          ? {
              ...request,
              quotes: (request.quotes ?? []).map((q) => (q.id === updated.id ? updated : q)),
            }
          : request,
      ),
    );
  }

  /** Message lisible selon le code d'erreur HTTP. */
  private messageFor(status?: number): string {
    switch (status) {
      case 422:
        return 'Ce devis n’est plus en attente de réponse. La liste vient d’être actualisée.';
      case 403:
        return 'Seul le client à l’origine du projet peut répondre à ce devis.';
      case 0:
        return 'Connexion perdue. Vérifiez votre réseau, puis réessayez.';
      default:
        return 'La réponse n’a pas pu être enregistrée. Réessayez dans un instant.';
    }
  }
}
