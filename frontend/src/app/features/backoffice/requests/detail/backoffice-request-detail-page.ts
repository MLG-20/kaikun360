import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';

import {
  AccountActivity,
  AdminService,
  RequestQueueEntry,
  RequestQuote,
} from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { FicheFlag, FicheSignalsComponent } from '../../shared/fiche-signals/fiche-signals';

/**
 * **Fiche d'une demande client** (F8.9) — `/back-office/demandes/:id`.
 *
 * Alimentée par `GET /admin/requests/{id}`, elle restitue ce qu'il faut pour
 * décider : le demandeur **joignable**, sa demande en toutes lettres, les devis
 * déjà proposés, et l'historique des décisions prises.
 *
 * **Le pilotage passe par la route historique** `PATCH /requests/{id}/status`,
 * gardée par la même permission `traiter:demandes` : la machine à états vit
 * dans `RequestStatus` côté serveur et n'est pas redite ici. L'écran ne propose
 * que les étapes listées dans `allowed_transitions` — offrir un bouton que le
 * serveur refuserait en 422 serait un faux espoir, et rejouer la machine à
 * états côté client la ferait diverger au premier statut ajouté.
 */
@Component({
  selector: 'app-backoffice-request-detail-page',
  imports: [FicheSignalsComponent],
  templateUrl: './backoffice-request-detail-page.html',
  // Briques communes des fiches hiérarchisées en F8.3 (volets repliables).
  styleUrls: ['./backoffice-request-detail-page.scss', '../../shared/fiche-blocks.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeRequestDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  private readonly requestId = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<RequestQueueEntry | null>(null);
  protected readonly quotes = signal<RequestQuote[]>([]);
  protected readonly activity = signal<AccountActivity[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /** Transition en cours (verrouille les boutons pour éviter le double clic). */
  protected readonly busy = signal(false);
  protected readonly actionError = signal<string | null>(null);

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.requestDossier(this.requestId).subscribe({
      next: (detail) => {
        this.dossier.set(detail.request);
        this.quotes.set(detail.quotes);
        this.activity.set(detail.activity);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  // --- Ce qui appelle une décision --------------------------------------------

  /** Jours écoulés depuis le dépôt de la demande. */
  protected readonly ageInDays = computed(() => {
    const date = this.dossier()?.created_at;
    if (!date) return 0;
    return Math.floor((Date.now() - new Date(date).getTime()) / 86_400_000);
  });

  /**
   * Les signaux d'une file de traitement tiennent en une idée : **le temps**.
   * Une demande n'a pas de « problème » au sens d'un mandat impayé — elle a un
   * délai de première réponse qui court depuis son dépôt, et c'est le silence
   * qui abîme la relation, pas le refus.
   */
  protected readonly flags = computed<FicheFlag[]>(() => {
    const demande = this.dossier();
    if (!demande || demande.status === 'cloture') {
      return [];
    }

    const flags: FicheFlag[] = [];
    const jours = this.ageInDays();

    // Jamais prise en charge : le statut n'a pas bougé depuis l'arrivée.
    if (demande.status === 'recu' && jours >= 2) {
      flags.push({
        level: 'alerte',
        text: `Reçue il y a ${jours} jours et jamais prise en charge.`,
        anchor: 'rq-pilotage',
        cta: 'Faire avancer',
      });
    } else if (jours >= 14) {
      flags.push({
        level: 'vigilance',
        text: `Ouverte depuis ${jours} jours, toujours pas clôturée.`,
        anchor: 'rq-pilotage',
        cta: 'Faire avancer',
      });
    }

    // Une demande « au stade devis » sans aucun devis attaché est une
    // incohérence : le client attend un chiffrage qui n'existe pas.
    if ((demande.status === 'devis' || demande.status === 'negociation') && !this.quotes().length) {
      flags.push({
        level: 'alerte',
        text: 'Au stade devis, mais aucun devis n\'a été proposé.',
        anchor: 'rq-devis',
        cta: 'Voir les devis',
      });
    }

    if (demande.priority === 'urgente') {
      flags.push({
        level: 'vigilance',
        text: 'Demande marquée urgente par l\'équipe.',
        anchor: 'rq-pilotage',
        cta: 'Traiter',
      });
    }

    // Un compte supprimé (anonymisé) rend la demande intraitable : on ne peut
    // plus joindre personne. Le dire vaut mieux que laisser chercher.
    if (!demande.requester) {
      flags.push({
        level: 'alerte',
        text: 'Le compte du demandeur n\'existe plus : personne à rappeler.',
        anchor: 'rq-demandeur',
        cta: 'Voir',
      });
    }

    return flags;
  });

  // --- Pilotage ----------------------------------------------------------------

  /**
   * Fait avancer la demande d'une étape.
   *
   * Le serveur reste juge : une transition refusée revient en 422 avec son
   * message, qu'on affiche tel quel plutôt que de le traduire — il dit
   * exactement quelle transition a été refusée.
   */
  protected advance(status: string): void {
    if (this.busy()) return;

    this.busy.set(true);
    this.actionError.set(null);

    this.admin.advanceRequest(this.requestId, status).subscribe({
      next: () => {
        this.busy.set(false);
        // On recharge la fiche entière : le statut change les transitions
        // permises ET ajoute une entrée à l'historique.
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.busy.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | undefined;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
      return first ?? 'Cette étape a été refusée.';
    }
    if (error.status === 403) {
      return "Vous n'avez pas le droit de faire avancer cette demande.";
    }
    return 'L\'action a échoué. Réessayez.';
  }

  protected back(): void {
    void this.router.navigate(['/back-office', 'demandes']);
  }

  // --- Présentation -------------------------------------------------------------

  protected statusClass(status: string | null): string {
    switch (status) {
      case 'cloture':
        return 'is-off';
      case 'recu':
        return 'is-pending';
      default:
        return 'is-info';
    }
  }

  protected priorityClass(priority: string | null): string {
    switch (priority) {
      case 'urgente':
        return 'is-off';
      case 'haute':
        return 'is-warn';
      default:
        return 'is-pending';
    }
  }

  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  protected shortDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    });
  }

  protected dateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  /**
   * Rend lisible le `properties` du journal d'audit — `{from, to}` pour un
   * changement de statut. Une paire clé/valeur brute ne dit rien à l'agent.
   */
  protected transitionOf(entry: AccountActivity): string | null {
    const props = entry.properties as { from?: string; to?: string } | null;
    if (!props?.from || !props?.to) return null;
    return `${props.from} → ${props.to}`;
  }
}
