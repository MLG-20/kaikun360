import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
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
  // FormsModule : le chiffrage d'un devis (F8.11) se saisit dans cette fiche.
  imports: [FicheSignalsComponent, FormsModule],
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

  /**
   * @param refus Message de repli quand le serveur refuse sans détail. Il diffère
   *              selon le geste : refuser une transition et refuser un chiffrage
   *              n'appellent pas la même explication.
   */
  private messageFor(
    error: HttpErrorResponse,
    refus = 'Cette étape a été refusée.',
    interdit = "Vous n'avez pas le droit de faire avancer cette demande.",
  ): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | undefined;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
      return first ?? refus;
    }
    if (error.status === 403) {
      return interdit;
    }
    return 'L\'action a échoué. Réessayez.';
  }

  // --- Chiffrage d'un devis (F8.11) --------------------------------------------
  //
  // ⚠️ `POST /requests/{id}/quotes` existait depuis B11.3 sans aucun écran pour
  // l'appeler. L'agent pouvait faire avancer une demande jusqu'au stade « devis »
  // sans jamais pouvoir en produire un — et le client, lui, savait déjà accepter
  // ou refuser. Il pouvait accepter ce que personne ne pouvait émettre.

  /** Le formulaire de chiffrage est-il déployé ? */
  protected readonly composing = signal(false);
  /** Envoi en cours (verrouille le bouton). */
  protected readonly sendingQuote = signal(false);
  protected readonly quoteError = signal<string | null>(null);

  protected quoteAmount = '';
  protected quoteValidUntil = '';

  /**
   * Postes du chiffrage, saisis ligne à ligne.
   *
   * Le backend accepte un objet libre (`details`), affiché tel quel au client.
   * On ne fait donc PAS taper du JSON à l'agent : une ligne vide est toujours
   * offerte en fin de liste, les lignes incomplètes sont ignorées à l'envoi.
   */
  protected quoteLines: { label: string; value: string }[] = [{ label: '', value: '' }];

  /** Ouvre ou referme le formulaire, en repartant d'une saisie propre. */
  protected toggleCompose(): void {
    const next = !this.composing();
    this.composing.set(next);
    if (next) {
      this.quoteAmount = '';
      this.quoteValidUntil = '';
      this.quoteLines = [{ label: '', value: '' }];
      this.quoteError.set(null);
    }
  }

  /** Garantit qu'une ligne vierge reste disponible en bas de la liste. */
  protected onLineInput(): void {
    const last = this.quoteLines[this.quoteLines.length - 1];
    if (last && (last.label.trim() || last.value.trim())) {
      this.quoteLines = [...this.quoteLines, { label: '', value: '' }];
    }
  }

  protected removeLine(index: number): void {
    this.quoteLines = this.quoteLines.filter((_, i) => i !== index);
    if (!this.quoteLines.length) {
      this.quoteLines = [{ label: '', value: '' }];
    }
  }

  /**
   * Envoie le devis au client.
   *
   * Le montant est la seule donnée obligatoire — c'est la seule que le serveur
   * exige, et un chiffrage sans détail reste un chiffrage valable.
   */
  protected sendQuote(): void {
    if (this.sendingQuote()) return;

    const montant = Number(this.quoteAmount);
    if (!Number.isFinite(montant) || montant <= 0) {
      this.quoteError.set('Indiquez un montant en FCFA.');
      return;
    }

    // Seules les lignes complètes partent : une ligne à moitié saisie n'a pas
    // de sens dans un devis lu par le client.
    const details: Record<string, string> = {};
    for (const line of this.quoteLines) {
      const label = line.label.trim();
      const value = line.value.trim();
      if (label && value) {
        details[label] = value;
      }
    }

    this.sendingQuote.set(true);
    this.quoteError.set(null);

    this.admin
      .composeQuote(this.requestId, {
        amount_xof: montant,
        valid_until: this.quoteValidUntil || null,
        details: Object.keys(details).length ? details : undefined,
      })
      .subscribe({
        next: () => {
          this.sendingQuote.set(false);
          this.composing.set(false);
          // Rechargement complet : le devis s'ajoute à la liste ET l'historique
          // gagne une entrée.
          this.load();
        },
        error: (error: HttpErrorResponse) => {
          this.sendingQuote.set(false);
          this.quoteError.set(
            this.messageFor(
              error,
              "Ce devis a été refusé par le serveur.",
              "Vous n'avez pas le droit de chiffrer cette demande.",
            ),
          );
        },
      });
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
