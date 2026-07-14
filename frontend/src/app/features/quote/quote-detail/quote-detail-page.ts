import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { QuoteDecision, QuoteService } from '../../../core/api/quote.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { Quote } from '../../../models/quote.model';

/** État de chargement de la page. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'forbidden' | 'failed';

/** Une ligne du détail chiffré du devis (poste → valeur lisible). */
interface DetailRow {
  label: string;
  value: string;
}

/**
 * Consultation & réponse à un devis (F2.7) — route `/devis/:id`.
 *
 * Le client arrive ici par un lien reçu en notification quand un conseiller lui
 * propose un devis sur l'une de ses demandes. La page présente le montant, la
 * validité et le détail chiffré, puis — **uniquement si le devis est encore au
 * statut « envoyé »** — propose de l'accepter ou de le refuser
 * (`PATCH /quotes/{id}`). Les endpoints exigent une session ; un devis qui
 * n'appartient pas à l'utilisateur renvoie 403 (état « accès refusé »).
 */
@Component({
  selector: 'app-quote-detail-page',
  imports: [RouterLink],
  templateUrl: './quote-detail-page.html',
  styleUrl: './quote-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class QuoteDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly quotes = inject(QuoteService);
  private readonly auth = inject(AuthService);

  readonly state = signal<LoadState>('loading');

  /** Devis chargé (ou null tant qu'indisponible). */
  readonly quote = signal<Quote | null>(null);

  /** Vrai si un utilisateur est connecté (les endpoints l'exigent). */
  readonly isAuthenticated = this.auth.isAuthenticated;

  /** Identifiant du devis issu de l'URL. */
  private readonly id = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id'))),
  );

  /** Paramètres de connexion renvoyant sur le devis courant après login. */
  readonly loginQueryParams = computed(() => ({ redirect: `/devis/${this.id() ?? ''}` }));

  /** Traitement en cours (accept/refuse) — désactive les boutons. */
  readonly responding = signal(false);
  /** Message d'erreur global (échec de la réponse). */
  readonly responseError = signal<string | null>(null);

  /**
   * Déclenche le chargement dès que l'identifiant est connu. `switchMap` annule
   * une requête précédente si l'on change de devis.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.quote.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.quotes.get(id).pipe(
          tap((env) => {
            this.quote.set(env.data.quote);
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

  /** Montant formaté en FCFA. */
  readonly amountLabel = computed(() => formatFcfa(this.quote()?.amount_xof));

  /** Vrai si le devis est encore répondable (statut « envoyé »). */
  readonly canRespond = computed(() => this.quote()?.status === 'envoye');

  /** Vrai si le client a déjà tranché (accepté ou refusé). */
  readonly isSettled = computed(() => {
    const status = this.quote()?.status;
    return status === 'accepte' || status === 'refuse';
  });

  /**
   * Détail chiffré du devis présenté en lignes lisibles. Le backend renvoie un
   * objet libre (`details`) ; on l'aplatit en paires clé → valeur, en ignorant
   * les valeurs vides. Un tableau (rare) n'est pas déplié ici.
   */
  readonly detailRows = computed<DetailRow[]>(() => {
    const details = this.quote()?.details;
    if (!details || Array.isArray(details)) {
      return [];
    }
    return Object.entries(details)
      .filter(([, value]) => value !== null && value !== undefined && value !== '')
      .map(([key, value]) => ({ label: this.humanize(key), value: String(value) }));
  });

  /** Accepte le devis. */
  accept(): void {
    this.respond('accepte');
  }

  /** Refuse le devis. */
  refuse(): void {
    this.respond('refuse');
  }

  /** Envoie la décision (PATCH /quotes/{id}) et rafraîchit l'affichage. */
  private respond(decision: QuoteDecision): void {
    const quote = this.quote();
    if (this.responding() || !quote || !this.canRespond()) {
      return;
    }

    this.responding.set(true);
    this.responseError.set(null);

    this.quotes.respond(quote.id, decision).subscribe({
      next: (env) => {
        this.responding.set(false);
        this.quote.set(env.data.quote);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.responding.set(false);
        const firstError = err?.error?.errors
          ? Object.values(err.error.errors)[0]?.[0]
          : null;
        this.responseError.set(
          firstError ?? "Votre réponse n'a pas pu être enregistrée. Réessayez.",
        );
      },
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

  /** Rend une clé technique (`main_oeuvre`) lisible (« Main oeuvre »). */
  private humanize(key: string): string {
    const spaced = key.replace(/[_-]+/g, ' ').trim();
    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
  }
}
