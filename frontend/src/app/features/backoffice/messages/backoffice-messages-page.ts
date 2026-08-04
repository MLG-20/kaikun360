import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { AdminService, SupportInboxQuery, SupportThread } from '../../../core/api/admin.service';
import { pollWhileVisible } from '../../../core/state/poll-while-visible';

/** Portée de la file, telle que la comprend le serveur. */
type Scope = 'mine' | 'unassigned' | 'all';

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

  constructor() {
    this.load();

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
}
