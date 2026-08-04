import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { MessageService, SupportContextType } from '../../../core/api/message.service';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';

/** État d'envoi du bloc. */
type SendState = 'idle' | 'sending' | 'failed';

@Component({
  selector: 'app-contact-support',
  imports: [FormsModule],
  templateUrl: './contact-support.html',
  styleUrl: './contact-support.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Bloc « Écrire au support Kaikun » (F8.12) — **le geste qui manquait à la
 * messagerie**.
 *
 * Depuis F3.7, le socle savait tout faire d'un fil de discussion sauf le
 * commencer : lire, répondre, compter les non-lus, notifier… mais aucun écran
 * n'offrait d'en ouvrir un. L'état vide de « Mes messages » promettait pourtant
 * qu'« une conversation s'ouvre lorsque vous contactez le support depuis une
 * annonce ou une demande » — un geste qui n'existait nulle part.
 *
 * Composant **autonome et réutilisable** : on le pose sur n'importe quel écran
 * en lui donnant le dossier concerné.
 *
 *   <app-contact-support contextType="reservation" [contextId]="booking.id" />
 *
 * Deux partis pris :
 *   - **le dossier voyage avec le message** (`contextType`/`contextId`). C'est
 *     ce qui évite à l'agent d'ouvrir un fil en se demandant de quoi on lui
 *     parle. Sans dossier (bouton de l'écran « Messages »), le fil est une
 *     question générale, ce qui reste légitime ;
 *   - **on ne demande pas de destinataire**. Le client n'écrit jamais
 *     directement au propriétaire ou au prestataire : le serveur assigne un
 *     agent, qui décidera au cas par cas d'ajouter le tiers au fil. C'est toute
 *     l'architecture « support pivot » — la contourner ferait sortir l'échange
 *     de la plateforme.
 *
 * À l'envoi, on redirige vers le fil créé (ou repris) : le client voit son
 * message posté et l'interlocuteur qui lui a été assigné, plutôt qu'un simple
 * « message envoyé » sans suite.
 */
export class ContactSupportComponent {
  private readonly messages = inject(MessageService);
  private readonly router = inject(Router);

  /**
   * Préfixe de l'espace courant, pour rediriger vers le BON fil : les quatre
   * espaces connectés ont chacun leur `/messages` (même piège qu'en F8.8 avec
   * `SpaceLink` côté e-mails). `optional` car le bloc peut être monté hors d'un
   * espace ; on retombe alors sur l'espace client.
   */
  private readonly space = inject(SPACE_CONFIG, { optional: true });

  /** Dossier concerné (facultatif — sans lui, c'est une question générale). */
  readonly contextType = input<SupportContextType | undefined>(undefined);
  readonly contextId = input<number | undefined>(undefined);

  /** Sujet préparé pour l'agent (sinon le serveur en compose un depuis le dossier). */
  readonly subject = input<string | undefined>(undefined);

  /** Libellé du bouton d'ouverture, ajustable selon l'écran. */
  readonly label = input('Écrire au support');

  /** Phrase d'accompagnement affichée sous le titre du bloc déplié. */
  readonly hint = input('Un agent Kaikun vous répond depuis votre espace. Réponse par notification.');

  // — État du bloc —
  /** Le formulaire est-il déplié ? (fermé par défaut : c'est une action, pas un champ). */
  protected readonly open = signal(false);
  protected readonly state = signal<SendState>('idle');
  protected readonly body = signal('');

  /** Ouvre / referme le formulaire (le brouillon est conservé en cas de repli). */
  protected toggle(): void {
    this.open.update((open) => !open);
    this.state.set('idle');
  }

  /** Envoie le message et emmène l'utilisateur dans le fil correspondant. */
  protected send(): void {
    const body = this.body().trim();

    if (body.length === 0 || this.state() === 'sending') {
      return;
    }

    this.state.set('sending');

    this.messages
      .startWithSupport({
        body,
        subject: this.subject(),
        contextType: this.contextType(),
        contextId: this.contextId(),
      })
      .subscribe({
        next: (res) => {
          this.body.set('');
          this.open.set(false);
          this.state.set('idle');
          const base = this.space?.basePath ?? '/mon-espace';
          void this.router.navigate([base, 'messages', res.data.conversation.id]);
        },
        error: () => this.state.set('failed'),
      });
  }
}
