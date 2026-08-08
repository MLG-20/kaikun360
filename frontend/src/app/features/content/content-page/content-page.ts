import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { ContentService } from '../../../core/api/content.service';
import { SeoService } from '../../../core/seo/seo.service';
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
 * Une page absente ou non publiée renvoie 404 → état « introuvable ».
 *
 * ⚠️ **Référencement (F9.1)** : cette page était la SEULE de tout le frontend à
 * toucher aux balises du document — elle appelait `Title.setTitle()`. Elle passe
 * par `SeoService` comme les autres, ce qui lui apporte en plus description,
 * URL canonique et OpenGraph. Le titre reste celui saisi au back-office : ces
 * pages sont éditoriales, leur intitulé est un choix rédactionnel.
 *
 * ⚠️ La description est **extraite du corps HTML** : l'API n'expose aucun champ
 * de résumé. Les balises sont retirées avant coupe — sans quoi Google afficherait
 * « <h2>Article 1 </h2><p>Le présent… » sous le résultat.
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
  private readonly seo = inject(SeoService);

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
            this.seo.apply({
              title: page.title,
              description: this.resumer(page.body),
              type: 'article',
              canonicalPath: `/pages/${page.slug}`,
            });
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );

  /**
   * Extrait une description lisible du corps HTML d'une page éditoriale.
   *
   * ⚠️ Volontairement une expression régulière et **pas** un
   * `DOMParser`/`innerHTML` : cette méthode s'exécute aussi au rendu serveur,
   * où il n'existe ni `document` global ni `DOMParser`. On ne cherche pas à
   * analyser du HTML — seulement à retirer des balises d'un texte qu'on a
   * nous-mêmes rendu ailleurs — et le résultat ne repart jamais dans le DOM :
   * il finit dans un attribut `content`, échappé par le navigateur.
   *
   * La coupe finale à 160 caractères est faite par `SeoService`.
   */
  private resumer(html: string): string {
    return html
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/g, ' ')
      .replace(/&amp;/g, '&')
      .replace(/\s+/g, ' ')
      .trim();
  }
}
