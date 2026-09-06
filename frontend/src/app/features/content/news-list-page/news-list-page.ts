import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { NewsArticle, NewsService } from '../../../core/api/news.service';

/** État de chargement de la page liste. */
type LoadState = 'loading' | 'ready' | 'failed';

/**
 * Page liste des actualités Kaikun (2026-09-06) — route `/actualites`.
 *
 * Demande client : une vraie page « Actualités », pas seulement l'aperçu de
 * l'accueil (vidéo + 4 cartes maximum, voir `HomePageComponent`), pour y
 * loger les futures communications de l'équipe, avec un tri par thème.
 *
 * ⚠️ **Ne montre que les vrais articles (texte rédigé)** : les cartes sans
 * texte (image + lien vers une autre page du site, ex. « Immobilier
 * vérifié ») sont réservées à la section « À découvrir » de l'accueil — même
 * critère que `cartesLibres` dans `home-page.ts`. Une carte de navigation
 * n'a rien à faire au milieu d'une liste d'actualités.
 *
 * Le filtre par catégorie se fait ENTIÈREMENT ici, côté client : `NewsService
 * .list()` renvoie déjà tous les articles publiés en un seul appel (volume
 * d'articles faible), inutile d'aller-retourner au serveur à chaque clic sur
 * un chip — voir la décision consignée dans le plan du 2026-09-06.
 */
@Component({
  selector: 'app-news-list-page',
  imports: [RouterLink],
  templateUrl: './news-list-page.html',
  styleUrl: './news-list-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewsListPageComponent {
  private readonly news = inject(NewsService);

  protected readonly state = signal<LoadState>('loading');
  protected readonly articles = signal<NewsArticle[]>([]);

  /** Vrais articles (texte rédigé) — exclut les cartes de navigation de l'accueil. */
  private readonly articlesRediges = computed(() => this.articles().filter((a) => !!a.body?.trim()));

  /** `null` = « Toutes » (aucun filtre actif). */
  protected readonly categorieActive = signal<string | null>(null);

  /** Catégories distinctes réellement utilisées, dans l'ordre d'apparition. */
  protected readonly categories = computed(() => {
    const vues = new Set<string>();
    for (const a of this.articlesRediges()) {
      if (a.category) vues.add(a.category);
    }
    return [...vues];
  });

  protected readonly articlesFiltres = computed(() => {
    const categorie = this.categorieActive();
    const liste = this.articlesRediges();
    return categorie ? liste.filter((a) => a.category === categorie) : liste;
  });

  /** Premier article de la liste filtrée (déjà trié par `position` côté back), mis en avant. */
  protected readonly articleVedette = computed(() => this.articlesFiltres()[0] ?? null);

  /** Reste de la liste filtrée, sans l'article vedette. */
  protected readonly autresArticles = computed(() => this.articlesFiltres().slice(1));

  constructor() {
    this.news.list().subscribe({
      next: ({ articles }) => {
        this.articles.set(articles);
        this.state.set('ready');
      },
      error: () => this.state.set('failed'),
    });
  }

  protected choisirCategorie(categorie: string | null): void {
    this.categorieActive.set(categorie);
  }

  protected lienArticle(article: NewsArticle): unknown[] {
    return ['/actualites', article.slug ?? article.id];
  }
}
