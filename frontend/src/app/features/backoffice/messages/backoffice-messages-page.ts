import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import {
  AdminContactMessage,
  AdminService,
  SupportInboxQuery,
  SupportThread,
} from '../../../core/api/admin.service';
import { pollWhileVisible } from '../../../core/state/poll-while-visible';

/** Portée de la file, telle que la comprend le serveur. */
type Scope = 'mine' | 'unassigned' | 'all';

/**
 * Les deux natures de courrier entrant (F8.15.c).
 *
 * ⚠️ **Un onglet, pas une quatrième portée.** Une conversation a un compte, un
 * fil et une réponse dans l'application ; un message de contact vient d'un
 * prospect **sans compte**, se traite au téléphone ou par e-mail, et se referme.
 * Les mélanger dans la même liste ferait chercher un bouton « Répondre » qui ne
 * peut pas exister.
 */
type Tab = 'threads' | 'contact';

/**
 * Écran **Messages** du back-office — la boîte de réception du support (F8.12).
 *
 * ⚠️ **Sans cet écran, la messagerie n'existe pas.** Le socle des conversations
 * date de F3.7 : un client pouvait lire un fil et y répondre, mais aucun geste
 * ne permettait d'en OUVRIR un, et personne côté équipe n'avait de vue sur ces
 * fils. Un agent aurait dû ouvrir son espace client personnel pour découvrir,
 * au hasard d'une notification, qu'on lui écrivait. Le cahier des charges liste
 * pourtant « Messages — conversation avec le support Kaikun ou le prestataire
 * affecté » comme module contractuel, **pour tous les profils**.
 *
 * Trois partis pris :
 *  - **la file s'ouvre sur MES fils ouverts**. Une boîte partagée qui déverse
 *    tout l'historique de l'équipe est une boîte que personne ne traite : chacun
 *    suppose que l'autre a répondu. Les deux autres portées (« Non assignés »,
 *    « Toute l'équipe ») restent à un clic ;
 *  - **« en attente » n'est pas « non lu »**. Un fil dont le dernier message
 *    vient du client est *dû* — c'est le seul chiffre qui gouverne le travail,
 *    et il est calculé côté serveur (`awaiting_reply`) ;
 *  - **le contact du client est cliquable dès la liste**, comme dans la file des
 *    demandes (F8.9) : certaines questions se règlent plus vite au téléphone.
 */
@Component({
  selector: 'app-backoffice-messages-page',
  imports: [FormsModule],
  templateUrl: './backoffice-messages-page.html',
  styleUrl: './backoffice-messages-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeMessagesPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<SupportThread[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Portée courante — « mes fils » par défaut (cf. en-tête). */
  protected readonly scope = signal<Scope>('mine');
  /** Archive des fils clos. */
  protected readonly closed = signal(false);
  protected search = '';

  // --- Messages de contact (F8.15.c) ----------------------------------------
  /** Onglet courant : les fils du support, ou le courrier des prospects. */
  protected readonly tab = signal<Tab>('threads');
  protected readonly contactRows = signal<AdminContactMessage[]>([]);
  /** Nombre de messages de contact NON TRAITÉS, quel que soit le filtre. */
  protected readonly contactPending = signal(0);
  protected readonly contactPage = signal(1);
  protected readonly contactLastPage = signal(1);
  protected readonly contactLoading = signal(false);
  protected readonly contactError = signal(false);
  /** Filtre : `null` = tout, sinon `nouveau` / `traite`. */
  protected readonly contactStatus = signal<string | null>('nouveau');
  /** Message dont le changement de statut est en vol (bouton neutralisé). */
  protected readonly contactBusyId = signal<number | null>(null);

  constructor() {
    this.load();
    // Le compteur de l'onglet doit être juste dès l'arrivée, même si l'agent
    // reste sur les fils : sinon rien ne l'appelle vers le courrier en attente.
    this.loadContact();

    // F8.12.a — la file se tient à jour seule (30 s) : c'est l'écran sur lequel
    // un agent ATTEND, un nouveau fil qui n'apparaît qu'au rechargement manuel
    // n'est pas vu. Relève silencieuse : pas d'état de chargement, pas d'erreur
    // affichée — le battement suivant rattrapera une coupure passagère.
    pollWhileVisible(() => this.load(true), 30_000);
  }

  protected setScope(scope: Scope): void {
    if (this.scope() === scope) return;
    this.scope.set(scope);
    this.page.set(1);
    this.load();
  }

  protected toggleClosed(): void {
    this.closed.update((closed) => !closed);
    this.page.set(1);
    this.load();
  }

  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(silencieux = false): void {
    if (!silencieux) {
      this.loading.set(true);
      this.error.set(false);
    }

    const query: SupportInboxQuery = {
      scope: this.scope(),
      closed: this.closed() || undefined,
      search: this.search.trim() || undefined,
      page: this.page(),
    };

    this.admin.supportInbox(query).subscribe({
      next: (paginated) => {
        this.rows.set(paginated.data);
        this.total.set(paginated.meta.total);
        this.lastPage.set(paginated.meta.last_page);
        this.loading.set(false);
      },
      error: () => {
        if (!silencieux) {
          this.error.set(true);
          this.loading.set(false);
        }
      },
    });
  }

  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /** Ouvre le fil. */
  protected open(thread: SupportThread): void {
    void this.router.navigate(['/back-office', 'messages', thread.id]);
  }

  // --- Présentation -----------------------------------------------------------

  /** Depuis combien de temps le fil attend (le chiffre qui fait agir). */
  protected age(iso: string | null): string {
    if (!iso) return '—';
    const jours = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);
    if (jours <= 0) return "aujourd'hui";
    if (jours === 1) return 'hier';
    return `il y a ${jours} jours`;
  }

  /** Extrait court du dernier message (la liste doit se lire d'un coup d'œil). */
  protected extrait(texte: string | null | undefined): string {
    if (!texte) return '—';
    return texte.length > 90 ? `${texte.slice(0, 90)}…` : texte;
  }

  // --- Messages de contact (F8.15.c) ------------------------------------------

  /** Bascule d'onglet ; ne recharge que ce qui est réellement affiché. */
  protected setTab(tab: Tab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);
    if (tab === 'contact') {
      this.loadContact();
    }
  }

  /**
   * Filtre du courrier. Le défaut est **« à traiter »** : une boîte qui s'ouvre
   * sur tout l'historique est une boîte où l'on ne voit pas ce qui reste à faire.
   */
  protected setContactStatus(status: string | null): void {
    if (this.contactStatus() === status) return;
    this.contactStatus.set(status);
    this.contactPage.set(1);
    this.loadContact();
  }

  protected goToContact(page: number): void {
    if (page < 1 || page > this.contactLastPage() || page === this.contactPage()) return;
    this.contactPage.set(page);
    this.loadContact();
  }

  protected loadContact(): void {
    this.contactLoading.set(true);
    this.contactError.set(false);

    this.admin
      .contactMessages({
        status: this.contactStatus() ?? undefined,
        page: this.contactPage(),
      })
      .subscribe({
        next: (paginated) => {
          this.contactRows.set(paginated.data);
          this.contactLastPage.set(paginated.meta.last_page);
          // `pending` est servi hors filtre : il dit la charge réelle même quand
          // l'agent regarde les messages déjà traités.
          this.contactPending.set(paginated.meta.pending ?? 0);
          this.contactLoading.set(false);
        },
        error: () => {
          this.contactError.set(true);
          this.contactLoading.set(false);
        },
      });
  }

  /**
   * Marque traité / rouvre. Le serveur enregistre l'agent et l'horodatage — d'où
   * le remplacement de la ligne par sa version fraîche plutôt qu'une bascule
   * locale, qui afficherait « traité par personne ».
   */
  protected toggleContactHandled(message: AdminContactMessage): void {
    const cible = message.status === 'traite' ? 'nouveau' : 'traite';
    this.contactBusyId.set(message.id);

    this.admin.setContactMessageStatus(message.id, cible).subscribe({
      next: (fresh) => {
        this.contactBusyId.set(null);
        this.contactPending.update((n) => (cible === 'traite' ? Math.max(0, n - 1) : n + 1));
        // Sous filtre, la ligne quitte la vue : la garder afficherait un message
        // « traité » dans une liste intitulée « à traiter ».
        if (this.contactStatus() !== null && this.contactStatus() !== cible) {
          this.contactRows.update((rows) => rows.filter((r) => r.id !== message.id));
        } else {
          this.contactRows.update((rows) => rows.map((r) => (r.id === fresh.id ? fresh : r)));
        }
      },
      error: () => {
        this.contactBusyId.set(null);
        this.contactError.set(true);
      },
    });
  }
}
