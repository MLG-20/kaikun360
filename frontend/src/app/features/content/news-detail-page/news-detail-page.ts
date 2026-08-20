import { isPlatformBrowser } from '@angular/common';
import { ChangeDetectionStrategy, Component, PLATFORM_ID, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { NewsArticle, NewsService } from '../../../core/api/news.service';
import { SeoService } from '../../../core/seo/seo.service';

/** État de chargement de la page de détail. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/**
 * Page de détail d'une actualité Kaikun (F16.3) — route `/actualites/:id`.
 *
 * La carte de l'accueil (`home-page`) n'affiche que titre et résumé : le corps
 * complet de l'article n'était nulle part accessible. Cette page comble ce
 * manque, sur le même modèle que `ContentPageComponent` (page éditoriale
 * adressée par identifiant plutôt que par slug, le back-office des actualités
 * n'en portant pas).
 *
 * Le corps HTML vient de l'éditeur riche du back-office et passe par
 * `[innerHTML]` — Angular l'assainit automatiquement, aucun
 * `DomSanitizer.bypassSecurityTrustHtml` nécessaire (contrairement à l'URL
 * d'embed vidéo, qui elle DOIT être marquée sûre explicitement).
 */
@Component({
  selector: 'app-news-detail-page',
  imports: [RouterLink],
  templateUrl: './news-detail-page.html',
  styleUrl: './news-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewsDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly news = inject(NewsService);
  private readonly seo = inject(SeoService);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly estNavigateur = isPlatformBrowser(inject(PLATFORM_ID));

  readonly state = signal<LoadState>('loading');
  readonly article = signal<NewsArticle | null>(null);

  /**
   * URL d'embed vidéo assainie — délibérément laissée à `null` au RENDU
   * SERVEUR (voir `estNavigateur` ci-dessous), pas seulement mémoïsée.
   *
   * ⚠️ **Piège rencontré en la posant sans cette garde** : l'hydratation crée
   * une INSTANCE `SafeResourceUrl` différente de celle produite côté serveur,
   * même pour la même URL — deux passes (serveur, puis client) qui appellent
   * chacune `bypassSecurityTrustResourceUrl`. Angular voit alors une valeur
   * « changée » et réaffecte l'attribut `src` de l'iframe déjà présente dans
   * le HTML serveur, ce que le navigateur traite comme une navigation : la
   * requête vidéo en cours est annulée puis relancée juste après
   * l'hydratation — et si le visiteur a cliqué « lecture » dans cette
   * fenêtre (cas courant sur mobile, où l'aperçu est visible avant que le JS
   * ait fini de s'hydrater), l'iframe repart de zéro et le clic semble sans
   * effet. Même famille de piège que `NewsArticleView` dans `home-page.ts`,
   * mais côté hydratation plutôt que cycles de détection répétés.
   *
   * En ne posant l'URL qu'une fois sur le NAVIGATEUR, aucune iframe vidéo
   * n'existe dans le HTML serveur : la vignette (`@else` du gabarit) s'affiche
   * d'abord, puis l'iframe est créée une seule fois, sans jamais entrer en
   * conflit avec une version serveur.
   */
  readonly videoEmbedUrl = signal<SafeResourceUrl | null>(null);

  /**
   * Charge l'article dès que l'identifiant est connu. `switchMap` annule une
   * requête précédente si l'on navigue d'un article à un autre.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.article.set(null);
        this.videoEmbedUrl.set(null);

        const numericId = Number(id);
        if (!id || !Number.isInteger(numericId)) {
          this.state.set('notfound');
          return of(null);
        }

        return this.news.get(numericId).pipe(
          tap((article) => {
            this.article.set(article);
            if (article.videoUrl && this.estNavigateur) {
              this.videoEmbedUrl.set(this.sanitizer.bypassSecurityTrustResourceUrl(article.videoUrl));
            }
            this.state.set('ready');
            this.seo.apply({
              title: article.title,
              description: article.excerpt ?? this.resumer(article.body ?? ''),
              type: 'article',
              canonicalPath: `/actualites/${article.id}`,
              image: article.image,
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

  /** Extrait une description lisible du corps HTML — même méthode que ContentPageComponent. */
  private resumer(html: string): string {
    return html
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/g, ' ')
      .replace(/&amp;/g, '&')
      .replace(/\s+/g, ' ')
      .trim();
  }
}
