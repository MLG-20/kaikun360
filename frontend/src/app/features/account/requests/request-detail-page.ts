import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { RequestService } from '../../../core/api/request.service';
import { ServiceRequest } from '../../../models/service-request.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { REQUEST_STEPS, RequestStep, stepState } from './request-timeline';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'forbidden' | 'failed';

@Component({
  selector: 'app-request-detail-page',
  imports: [DatePipe, BackLinkComponent],
  templateUrl: './request-detail-page.html',
  styleUrl: './request-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Détail d'une demande de l'espace client (F3.3), monté sous
 * `/mon-espace/demandes/:id`. Atteint en cliquant une carte depuis « Mes
 * demandes ».
 *
 * Charge la demande (`GET /requests/{id}`, réservée au propriétaire) et en
 * présente le récapitulatif complet — référence, univers, statut, message,
 * faits (localité, budget, date) — puis la **chronologie de statut** (machine à
 * états backend). Un bouton « ← Mes demandes » ramène toujours à la liste. Une
 * demande qui n'appartient pas à l'utilisateur renvoie 403 (état « accès refusé »).
 */
export class RequestDetailPageComponent {
  private readonly requests = inject(RequestService);
  private readonly route = inject(ActivatedRoute);

  /** Étapes de la chronologie (partagées avec la liste). */
  protected readonly steps: readonly RequestStep[] = REQUEST_STEPS;

  // — État de l'écran —
  protected readonly state = signal<LoadState>('loading');
  protected readonly request = signal<ServiceRequest | null>(null);

  /**
   * Déclenche le chargement dès que l'identifiant est connu. `switchMap` annule
   * une requête précédente si l'on change de demande.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.request.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.requests.get(id).pipe(
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

  /** Montant formaté en FCFA (ou null si non renseigné). */
  protected readonly budgetLabel = computed(() => formatFcfa(this.request()?.budget_xof));

  /** État d'une étape par rapport au statut courant de la demande. */
  protected stepState(req: ServiceRequest, i: number): 'done' | 'current' | 'todo' {
    return stepState(req, i);
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
