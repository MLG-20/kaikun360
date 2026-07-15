import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { ContentService } from '../../../core/api/content.service';
import { ContentPage } from '../../../models/content.model';

/** État de chargement de la page de contenu. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Page de contenu éditorial générique (F2.8) — route `/pages/:slug`.
 *
 * Sert TOUTES les pages adressées par slug (À propos, mentions légales, CGU,
 * politique de confidentialité…) : le contenu vient du backend
 * (`GET /pages/{slug}`), éditable depuis le back-office, jamais codé en dur.
 *
 * Le `body` est un fragment HTML rendu via `[innerHTML]` : Angular assainit
 * automatiquement le balisage (scripts et attributs dangereux retirés), ce qui
 * autorise titres, listes et liens dans les pages légales sans dépendance.
 *
 * Une page absente ou non publiée renvoie 404 → état « introuvable ». On met à
 * jour le titre de l'onglet avec le titre de la page une fois chargée.
 */
@Component({
  selector: 'app-content-page',
  imports: [RouterLink],
  templateUrl: './content-page.html',
  styleUrl: './content-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContentPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly content = inject(ContentService);
  private readonly title = inject(Title);

  readonly state = signal<LoadState>('loading');

  /** Page chargée (ou null tant qu'indisponible). */
  readonly page = signal<ContentPage | null>(null);

  /**
   * Charge la page dès que le slug est connu. `switchMap` annule une requête
   * précédente si l'on navigue d'une page de contenu à une autre.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('slug')),
      switchMap((slug) => {
        this.state.set('loading');
        this.page.set(null);
        if (!slug) {
          this.state.set('notfound');
          return of(null);
        }
        return this.content.page(slug).pipe(
          tap((page) => {
            this.page.set(page);
            this.state.set('ready');
            this.title.setTitle(`${page.title} — Kaikun 360`);
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );
}
