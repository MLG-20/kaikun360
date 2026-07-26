import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { TeamBuildingService } from '../../../core/api/team-building.service';
import { TeamBuildingQuote, TeamBuildingRequest } from '../../../models/team-building.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { ENTERPRISE_SPACE } from '../enterprise-space';
import { NEEDS_OPTIONS, quoteStatus, requestStatus } from './team-building-status';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'forbidden' | 'failed';

/**
 * Détail d'une demande de team building de l'espace entreprise (F6) — route
 * `/espace-entreprise/demandes/:id`.
 *
 * Charge la demande **avec ses devis** (`GET /team-building-requests/{id}`,
 * réservée à l'entreprise propriétaire) et en présente le récapitulatif : statut
 * (avec explication), informations (ville, participants, dates, budget),
 * besoins, descriptif, puis le ou les **devis composés** par Kaikun (lignes
 * détaillées, sous-total, marge, total). Quand un devis est au statut « envoyé »,
 * l'entreprise peut l'**accepter** (`PATCH /team-building-quotes/{id}/accept`),
 * ce qui déclenche le suivi opérationnel côté Kaikun.
 *
 * Les devis en brouillon (encore en préparation côté back-office) ne sont pas
 * montrés à l'entreprise.
 */
@Component({
  selector: 'app-enterprise-request-detail-page',
  imports: [DatePipe, RouterLink],
  templateUrl: './enterprise-request-detail-page.html',
  styleUrl: './enterprise-request-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EnterpriseRequestDetailPageComponent {
  private readonly teamBuilding = inject(TeamBuildingService);
  private readonly route = inject(ActivatedRoute);

  /** Préfixe d'URL de l'espace (lien de retour). */
  protected readonly base = ENTERPRISE_SPACE.basePath;

  // — État de l'écran —
  protected readonly state = signal<LoadState>('loading');
  protected readonly request = signal<TeamBuildingRequest | null>(null);
  private readonly currentId = signal<string | null>(null);

  // — Acceptation d'un devis —
  protected readonly accepting = signal<number | null>(null);
  protected readonly acceptError = signal<string | null>(null);

  /**
   * Charge la demande dès que l'identifiant est connu. `switchMap` annule une
   * requête précédente si l'on change de demande.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.request.set(null);
        this.currentId.set(id);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.teamBuilding.get(id).pipe(
          tap((env) => {
            this.request.set(env.data.request);
            this.state.set('ready');
          }),
          catchError((err: { status?: number }) => {
            this.state.set(this.stateForError(err?.status));
            return of(null);
          }),
        );
      }),
    ),
  );

  /** Présentation du statut de la demande (libellé + tonalité + explication). */
  protected readonly status = computed(() => requestStatus(this.request()?.status ?? null));

  /** Budget indicatif formaté en FCFA. */
  protected readonly budgetLabel = computed(() => formatFcfa(this.request()?.budget_xof));

  /** Libellés des besoins cochés sur la demande. */
  protected readonly needsLabels = computed(() => {
    const needs = this.request()?.needs ?? {};
    return NEEDS_OPTIONS.filter((o) => needs[o.key as keyof typeof needs]).map((o) => o.label);
  });

  /**
   * Devis visibles par l'entreprise : on masque les brouillons (encore en
   * préparation côté back-office), les plus récents d'abord.
   */
  protected readonly quotes = computed(() =>
    (this.request()?.quotes ?? [])
      .filter((q) => q.status !== 'brouillon')
      .slice()
      .sort((a, b) => b.id - a.id),
  );

  /** Présentation du statut d'un devis. */
  protected quoteStatusOf(quote: TeamBuildingQuote) {
    return quoteStatus(quote.status);
  }

  /** Un devis est-il acceptable ? (statut « envoyé » et aucune action en cours). */
  protected canAccept(quote: TeamBuildingQuote): boolean {
    return quote.status === 'envoye' && this.accepting() === null;
  }

  /** Montant formaté en FCFA. */
  protected fcfa(amount: number | null | undefined): string {
    return formatFcfa(amount) ?? '—';
  }

  /** Taux de marge en pourcentage lisible (ex. « 15 % »). */
  protected marginLabel(quote: TeamBuildingQuote): string {
    const rate = Number(quote.margin_rate);
    return Number.isFinite(rate) ? `${rate} %` : '';
  }

  /** Accepte un devis envoyé (PATCH .../accept), puis recharge la demande. */
  protected accept(quote: TeamBuildingQuote): void {
    if (!this.canAccept(quote)) {
      return;
    }
    this.accepting.set(quote.id);
    this.acceptError.set(null);

    this.teamBuilding.acceptQuote(quote.id).subscribe({
      next: () => this.reload(),
      error: (err: { status?: number }) => {
        this.accepting.set(null);
        this.acceptError.set(
          err?.status === 422
            ? "Ce devis n'est plus acceptable (il a peut-être changé de statut). Actualisez la page."
            : "L'acceptation n'a pas pu être enregistrée. Réessayez.",
        );
      },
    });
  }

  /** Recharge la demande courante (reflète les nouveaux statuts après acceptation). */
  private reload(): void {
    const id = this.currentId();
    if (!id) {
      return;
    }
    this.teamBuilding.get(id).subscribe({
      next: (env) => {
        this.request.set(env.data.request);
        this.accepting.set(null);
      },
      error: () => this.accepting.set(null),
    });
  }

  /** Traduit un code HTTP d'erreur en état d'affichage. */
  private stateForError(status?: number): LoadState {
    if (status === 404) {
      return 'notfound';
    }
    if (status === 403) {
      return 'forbidden';
    }
    return 'failed';
  }
}
