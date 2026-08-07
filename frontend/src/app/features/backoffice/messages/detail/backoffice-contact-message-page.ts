import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminContactMessage, AdminService } from '../../../../core/api/admin.service';
import { BackLinkComponent } from '../../../../shared/components/back-link/back-link';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'failed';

/**
 * Fiche d'un **message de contact** au back-office (F8.21).
 *
 * POURQUOI CET ÉCRAN EXISTE
 * -------------------------
 * En F8.15.c, le message était affiché **entier dans la liste**, au motif qu'il
 * n'y avait « aucune fiche à ouvrir derrière ». À l'usage, ce choix se retourne :
 * un tableau à cinq colonnes dont une contient un paragraphe déborde de l'écran
 * — il fallait défiler horizontalement pour atteindre le bouton d'action — et
 * les messages longs y étaient de toute façon coupés.
 *
 * La liste redevient donc une **liste** (qui écrit, quand, où ça en est) et
 * l'intégralité du courrier vit ici.
 *
 * ⚠️ **Ce n'est toujours pas une conversation**, et l'écran doit le dire : le
 * visiteur n'a le plus souvent pas de compte, il n'y a pas de fil, et aucune
 * réponse ne part depuis l'application. On rappelle ou on écrit par e-mail —
 * d'où le contact rendu cliquable au premier plan, et un bouton « Répondre par
 * e-mail » qui **préremplit l'objet** avec le sujet du message.
 *
 * ⚠️ **Marquer traité n'envoie rien** : c'est le geste qui évite que deux agents
 * rappellent le même prospect. Le serveur enregistre l'agent et l'horodatage.
 */
@Component({
  selector: 'app-backoffice-contact-message-page',
  imports: [DatePipe, RouterLink, BackLinkComponent],
  templateUrl: './backoffice-contact-message-page.html',
  styleUrl: './backoffice-contact-message-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeContactMessagePageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  protected readonly state = signal<LoadState>('loading');
  protected readonly message = signal<AdminContactMessage | null>(null);
  protected readonly busy = signal(false);
  protected readonly actionError = signal<string | null>(null);

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.load(id);
  }

  private load(id: number): void {
    this.state.set('loading');

    this.admin.contactMessage(id).subscribe({
      next: (message) => {
        this.message.set(message);
        this.state.set('ready');
      },
      error: () => this.state.set('failed'),
    });
  }

  /** Marque le message traité, ou le rouvre. */
  protected toggleHandled(): void {
    const message = this.message();
    if (!message || this.busy()) {
      return;
    }

    this.busy.set(true);
    this.actionError.set(null);

    const cible = message.status === 'nouveau' ? 'traite' : 'nouveau';

    this.admin.setContactMessageStatus(message.id, cible).subscribe({
      next: (maj) => {
        this.busy.set(false);
        // ⚠️ On reprend la réponse du serveur plutôt que de patcher le statut à
        // la main : elle porte l'agent et l'horodatage, que l'écran affiche.
        this.message.set(maj);
      },
      error: () => {
        this.busy.set(false);
        this.actionError.set("L'enregistrement n'a pas abouti. Réessayez.");
      },
    });
  }

  /**
   * Lien `mailto:` avec l'objet prérempli.
   *
   * Recopier « Re: » et le sujet à la main est le genre de petite friction qui
   * fait qu'on répond plus tard, donc parfois jamais.
   */
  protected mailtoFor(message: AdminContactMessage): string {
    const objet = message.subject ? `Re: ${message.subject}` : 'Votre message à Kaikun 360';

    return `mailto:${message.email}?subject=${encodeURIComponent(objet)}`;
  }
}
