import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';

import {
  AdminService,
  SupportCandidate,
  SupportThread,
} from '../../../../core/api/admin.service';
import { pollWhileVisible } from '../../../../core/state/poll-while-visible';
import { UnreadStore } from '../../../../core/state/unread-store';
import { BackLinkComponent } from '../../../../shared/components/back-link/back-link';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'failed';

/**
 * Fiche d'un **fil de support** au back-office (F8.12) — c'est ici que l'équipe
 * répond.
 *
 * L'écran tient en trois blocs, dans l'ordre où l'agent en a besoin :
 *   1. **qui écrit et à propos de quoi** — identité joignable, dossier cité,
 *      depuis quand ça attend ;
 *   2. **l'échange**, du plus ancien au plus récent, les messages de l'équipe
 *      alignés à droite (même grammaire que le fil côté client) ;
 *   3. **répondre**, puis, en second rang, les gestes de pilotage (réassigner,
 *      clore).
 *
 * ⚠️ Deux effets de bord viennent du SERVEUR et sont assumés : répondre à un fil
 * sans responsable **le prend en charge**, et répondre à un fil clos **le
 * rouvre**. L'écran les annonce plutôt que de les cacher.
 *
 * ⚠️ **F8.12.a — l'écran se tient à jour tout seul** : une **relève** toutes les
 * 10 s (`pollWhileVisible`) va chercher les messages postérieurs au dernier
 * affiché (`?after=`). Sans elle, l'agent devait recharger la page pour voir la
 * réponse du client — et un fil qu'il faut rafraîchir à la main n'est pas une
 * conversation. Elle s'arrête quand l'onglet est caché.
 *
 * ⚠️ **F8.12.c — faire entrer un TIERS** (propriétaire, prestataire) est un
 * geste vers l'extérieur, jamais une règle : une question de disponibilité se
 * transmet volontiers, une négociation de prix se garde. L'écran propose en un
 * clic la personne du dossier, puis une recherche restreinte aux
 * professionnels, et **prévient que le tiers verra tout l'historique** — c'est
 * la seule chose que l'agent doit peser avant de cliquer.
 */
@Component({
  selector: 'app-backoffice-message-thread-page',
  imports: [DatePipe, FormsModule, BackLinkComponent],
  templateUrl: './backoffice-message-thread-page.html',
  styleUrl: './backoffice-message-thread-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeMessageThreadPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);
  /** Compteur partagé : lire un fil éteint la pastille du rail (F8.13). */
  private readonly unread = inject(UnreadStore);

  protected readonly state = signal<LoadState>('loading');
  protected readonly thread = signal<SupportThread | null>(null);
  /** Vivier des agents habilités, pour la réassignation. */
  protected readonly agents = signal<{ id: number; name: string }[]>([]);

  // — Composeur —
  protected readonly draft = signal('');
  protected readonly sending = signal(false);
  protected readonly sendError = signal(false);

  // — Ajout d'un tiers (F8.12.c) —
  /** Le panneau d'ajout est-il ouvert ? (fermé par défaut : c'est un geste rare.) */
  protected readonly adding = signal(false);
  protected readonly candidateFromContext = signal<SupportCandidate | null>(null);
  protected readonly candidateResults = signal<SupportCandidate[]>([]);
  protected candidateSearch = '';

  /** Identifiant du fil, lu une fois : cet écran n'est jamais réutilisé pour un autre. */
  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  constructor() {
    this.load();

    // Relève silencieuse (cf. en-tête) : le premier chargement reste séparé,
    // c'est lui seul qui porte l'état « chargement » et l'écran d'erreur.
    pollWhileVisible(() => this.refresh());
  }

  private load(): void {
    this.state.set('loading');

    this.admin.supportThread(this.id).subscribe({
      next: (detail) => {
        this.thread.set(detail.conversation);
        this.agents.set(detail.agents);
        this.state.set('ready');
        // Ouvrir le fil l'a marqué comme lu côté serveur ; l'endpoint ne renvoie
        // pas le nouveau total, on le redemande pour éteindre la pastille (F8.13).
        this.unread.refresh();
      },
      error: () => this.state.set('failed'),
    });
  }

  /**
   * Relève : ne demande QUE les messages postérieurs au dernier affiché.
   * Silencieuse — une coupure passagère ne doit pas transformer un fil lisible
   * en écran d'erreur, le battement suivant rattrapera. On ne touche à rien
   * pendant un envoi en cours, pour ne pas doubler le message qui part.
   */
  private refresh(): void {
    const courant = this.thread();

    if (!courant || this.state() !== 'ready' || this.sending()) {
      return;
    }

    const dernier = (courant.messages ?? []).at(-1)?.id;

    this.admin.supportThread(this.id, dernier).subscribe({
      next: (detail) => {
        const nouveaux = detail.conversation.messages ?? [];

        // L'en-tête a pu changer (un collègue a repris ou clôturé le fil) ;
        // les messages, eux, s'ajoutent à ceux déjà affichés.
        this.thread.set({
          ...detail.conversation,
          messages: [...(courant.messages ?? []), ...nouveaux],
        });
        this.agents.set(detail.agents);
      },
      error: () => {
        /* relève silencieuse : le battement suivant réessaiera */
      },
    });
  }

  /** Envoie la réponse (et, côté serveur, prend le dossier / rouvre le fil). */
  protected send(): void {
    const body = this.draft().trim();

    if (body.length === 0 || this.sending()) return;

    this.sending.set(true);
    this.sendError.set(false);

    this.admin.replyToSupportThread(this.id, body).subscribe({
      next: (conversation) => {
        this.thread.set(conversation);
        this.draft.set('');
        this.sending.set(false);
      },
      error: () => {
        this.sending.set(false);
        this.sendError.set(true);
      },
    });
  }

  // --- Faire entrer / sortir un tiers (F8.12.c) -------------------------------

  /** Ouvre le panneau et charge d'emblée la personne du dossier. */
  protected toggleAdd(): void {
    this.adding.update((open) => !open);

    if (this.adding()) {
      this.loadCandidates();
    }
  }

  /** Cherche parmi les propriétaires et prestataires (2 caractères minimum). */
  protected loadCandidates(): void {
    const recherche = this.candidateSearch.trim();

    this.admin.supportCandidates(this.id, recherche || undefined).subscribe({
      next: (candidats) => {
        this.candidateFromContext.set(candidats.dossier);
        this.candidateResults.set(candidats.results);
      },
      error: () => this.sendError.set(true),
    });
  }

  /** Fait entrer la personne dans le fil (elle voit tout l'historique). */
  protected addParticipant(candidat: SupportCandidate): void {
    const courant = this.thread();
    if (!courant) return;

    this.admin.addSupportParticipant(this.id, candidat.id).subscribe({
      next: (conversation) => {
        this.thread.set({ ...courant, ...conversation, messages: courant.messages });
        this.adding.set(false);
        this.candidateSearch = '';
        this.candidateResults.set([]);
      },
      error: () => this.sendError.set(true),
    });
  }

  /** Sort un tiers du fil (ses messages déjà écrits restent). */
  protected removeParticipant(userId: number): void {
    const courant = this.thread();
    if (!courant) return;

    this.admin.removeSupportParticipant(this.id, userId).subscribe({
      next: (conversation) =>
        this.thread.set({ ...courant, ...conversation, messages: courant.messages }),
      error: () => this.sendError.set(true),
    });
  }

  /** Réassigne le fil (`''` = le remettre dans « Non assignées »). */
  protected reassign(value: string): void {
    this.pilot({ assigned_agent_id: value === '' ? null : Number(value) });
  }

  /** Clôt le fil (réglé) ou le rouvre. */
  protected toggleClosed(): void {
    const current = this.thread();
    if (!current) return;

    this.pilot({ closed: !current.is_closed });
  }

  /**
   * Applique un geste de pilotage et recolle l'état affiché.
   *
   * ⚠️ `PATCH /admin/conversations/{id}` ne renvoie **pas** les messages (la
   * fiche n'a pas à les recharger pour une réassignation) : on fusionne donc le
   * fil retourné avec celui affiché, sinon l'échange disparaîtrait de l'écran
   * au premier clic sur « Clôturer ».
   */
  private pilot(changes: { assigned_agent_id?: number | null; closed?: boolean }): void {
    const current = this.thread();
    if (!current) return;

    this.admin.updateSupportThread(this.id, changes).subscribe({
      next: (conversation) =>
        this.thread.set({ ...current, ...conversation, messages: current.messages }),
      error: () => this.sendError.set(true),
    });
  }
}
