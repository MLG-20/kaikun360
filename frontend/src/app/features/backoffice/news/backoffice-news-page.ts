import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AdminService, NewsArticleAdmin } from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { RichTextEditorComponent } from '../../../shared/components/rich-text-editor/rich-text-editor';
import { sanitizeRichText } from '../../../shared/components/rich-text-editor/rich-text.sanitizer';

/** Sous-onglet actif de l'écran Actualités. */
type NewsSubTab = 'decouvrir' | 'actualites';

/**
 * Saisie en cours pour un article d'actualité (F15), avant enregistrement.
 *
 * Les fichiers ne peuvent pas passer par `[(ngModel)]` : ils vivent donc à
 * part, posés par les gestionnaires `(change)` des champs `<input type=file>`.
 */
interface NewsDraft {
  title: string;
  excerpt: string;
  category: string;
  body: string;
  video_url: string;
  /**
   * Destination du bouton — sur une carte « À découvrir » (sans texte
   * rédigé), c'est le lien qui remplace la page `/actualites/:id` comme
   * cible du bouton public ; sur un article de la page Actualités, c'est un
   * lien externe facultatif en plus de sa propre page.
   */
  link_url: string;
  link_label: string;
  is_published: boolean;
  position: number;
  /** Nouvelle image choisie (`null` = on garde l'image déjà enregistrée). */
  image: File | null;
  /** Nouvelle vidéo choisie (`null` = on garde l'état déjà enregistré). */
  video: File | null;
  /** Retire la vidéo déposée sans en choisir une autre. */
  removeVideo: boolean;
}

const EMPTY_NEWS_DRAFT: NewsDraft = {
  title: '',
  excerpt: '',
  category: '',
  body: '',
  video_url: '',
  link_url: '',
  link_label: '',
  is_published: false,
  position: 0,
  image: null,
  video: null,
  removeVideo: false,
};

/**
 * Écran **Actualités** du back-office (2026-09-06) — CDC hors §6, contenu de
 * vitrine.
 *
 * Extrait de l'onglet « Actualités » de `BackofficeSettingsPageComponent`
 * (où il était noyé parmi six autres onglets) pour lui donner sa propre
 * entrée de menu — demande client : une page « digne de ce nom », que
 * l'équipe Kaikun manipule facilement.
 *
 * **Deux sous-onglets**, parce que la même table sert deux destinations
 * bien distinctes, déjà séparées côté public avant cette tranche :
 *  - **À découvrir** : les cartes (image + lien, sans texte rédigé) et les
 *    vidéos de la section du même nom sur l'accueil — voir `cartesLibres` /
 *    `videosActualites` dans `home-page.ts`, qui filtrent déjà par absence
 *    de `body`.
 *  - **Actualités** : les vrais articles (texte rédigé) affichés sur la
 *    page publique `/actualites` — celle-ci exclut à son tour les cartes
 *    sans texte (voir `news-list-page.ts`).
 *
 * Le critère qui sépare les deux est donc simplement **la présence d'un
 * corps rédigé** (`body`) — pas un champ dédié : c'est déjà comme ça que
 * l'accueil distingue carte et article depuis F17, pas la peine d'ajouter
 * une donnée qui dirait deux fois la même chose.
 */
@Component({
  selector: 'app-backoffice-news-page',
  imports: [FormsModule, RichTextEditorComponent],
  templateUrl: './backoffice-news-page.html',
  styleUrl: './backoffice-news-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeNewsPageComponent {
  private readonly admin = inject(AdminService);

  protected readonly tab = signal<NewsSubTab>('decouvrir');

  protected readonly newsLoading = signal(true);
  protected readonly newsLoaded = signal(false);
  protected readonly newsError = signal(false);
  protected readonly newsActionError = signal<string | null>(null);

  protected readonly newsArticles = signal<NewsArticleAdmin[]>([]);

  /** Cartes & vidéos de la section « À découvrir » de l'accueil (sans texte rédigé). */
  protected readonly articlesDecouvrir = computed(() =>
    this.newsArticles().filter((a) => !a.body?.trim()),
  );

  /** Vrais articles de la page publique « Actualités » (texte rédigé). */
  protected readonly articlesActualites = computed(() =>
    this.newsArticles().filter((a) => !!a.body?.trim()),
  );

  /** Lignes affichées dans le tableau du sous-onglet actif. */
  protected readonly articlesDuSousOnglet = computed(() =>
    this.tab() === 'decouvrir' ? this.articlesDecouvrir() : this.articlesActualites(),
  );

  /** Article en cours d'édition (`null` = aucun, `'new'` = création). */
  protected readonly editingNews = signal<NewsArticleAdmin | 'new' | null>(null);
  protected newsForm: NewsDraft = { ...EMPTY_NEWS_DRAFT };
  protected readonly newsSaving = signal(false);

  /**
   * Catégories déjà utilisées, pour la `<datalist>` du formulaire d'article
   * — aide l'équipe à rester cohérente (une seule graphie par thème) sans
   * imposer de liste fermée (décision : texte libre, pas de table à part).
   */
  protected readonly categoriesConnues = computed(() => {
    const vues = new Set<string>();
    for (const article of this.articlesActualites()) {
      if (article.category) vues.add(article.category);
    }
    return [...vues];
  });

  // --- Réglage « Nombre de cartes affichées sur l'accueil » (À découvrir) ----
  // Plafonne combien de cartes ci-dessous apparaissent dans la section « À
  // découvrir » de l'accueil — même réglage `home.discover_cards_count` que
  // l'ancien onglet Réglages > Accueil.

  protected readonly discoverCardsCountLoading = signal(true);
  protected readonly discoverCardsCountSaving = signal(false);
  protected readonly discoverCardsCountMessage = signal<string | null>(null);
  protected readonly discoverCardsCountError = signal<string | null>(null);
  protected discoverCardsCountDraft = '4';

  constructor() {
    this.loadNews();
    this.loadDiscoverCardsCount();
  }

  protected switchTab(tab: NewsSubTab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);
    this.editingNews.set(null);
    this.newsActionError.set(null);
  }

  protected loadNews(): void {
    this.newsLoading.set(true);
    this.newsError.set(false);
    this.admin.news().subscribe({
      next: (articles) => {
        this.newsArticles.set(articles);
        this.newsLoaded.set(true);
        this.newsLoading.set(false);
      },
      error: () => {
        this.newsError.set(true);
        this.newsLoading.set(false);
      },
    });
  }

  private loadDiscoverCardsCount(): void {
    this.discoverCardsCountLoading.set(true);
    this.admin.settings().subscribe({
      next: (snapshot) => {
        const setting = snapshot.settings.find((s) => s.key === 'home.discover_cards_count');
        this.discoverCardsCountDraft = String(setting?.value ?? 4);
        this.discoverCardsCountLoading.set(false);
      },
      error: () => this.discoverCardsCountLoading.set(false),
    });
  }

  protected saveDiscoverCardsCount(): void {
    this.discoverCardsCountSaving.set(true);
    this.discoverCardsCountError.set(null);
    this.discoverCardsCountMessage.set(null);

    this.admin
      .updateSettings({ 'home.discover_cards_count': Number(this.discoverCardsCountDraft) })
      .subscribe({
        next: () => {
          this.discoverCardsCountSaving.set(false);
          this.discoverCardsCountMessage.set('Réglage enregistré.');
        },
        error: (error: HttpErrorResponse) => {
          this.discoverCardsCountSaving.set(false);
          this.discoverCardsCountError.set(this.messageFor(error));
        },
      });
  }

  protected newNews(): void {
    this.newsActionError.set(null);
    this.newsForm = { ...EMPTY_NEWS_DRAFT };
    this.editingNews.set('new');
  }

  protected editNews(article: NewsArticleAdmin): void {
    this.newsActionError.set(null);
    this.newsForm = {
      title: article.title,
      excerpt: article.excerpt ?? '',
      category: article.category ?? '',
      body: article.body ?? '',
      video_url: article.video_url ?? '',
      link_url: article.link_url ?? '',
      link_label: article.link_label ?? '',
      is_published: article.is_published,
      position: article.position,
      image: null,
      video: null,
      removeVideo: false,
    };
    this.editingNews.set(article);
  }

  protected cancelNews(): void {
    this.editingNews.set(null);
    this.newsActionError.set(null);
  }

  protected onNewsImage(event: Event): void {
    this.newsForm.image = (event.target as HTMLInputElement).files?.[0] ?? null;
  }

  protected onNewsVideo(event: Event): void {
    this.newsForm.video = (event.target as HTMLInputElement).files?.[0] ?? null;
    // Choisir un fichier annule un retrait demandé juste avant.
    if (this.newsForm.video) this.newsForm.removeVideo = false;
  }

  /** Nom du fichier vidéo choisi mais pas encore enregistré, ou `null`. */
  protected newsVideoPending(): string | null {
    return this.newsForm.video?.name ?? null;
  }

  /** L'article en cours d'édition porte déjà une vidéo DÉPOSÉE (pas un embed). */
  protected readonly editingNewsHasVideoFile = computed(() => {
    const editing = this.editingNews();
    return editing !== null && editing !== 'new' && !!editing.video_file;
  });

  /**
   * Article réellement en cours de MODIFICATION (`null` en création ou sans
   * édition en cours) — pour afficher son lien public déjà attribué
   * (`slug`), qu'une création n'a pas encore.
   */
  protected readonly editingExistingArticle = computed(() => {
    const editing = this.editingNews();
    return editing !== null && editing !== 'new' ? editing : null;
  });

  protected saveNews(): void {
    const editing = this.editingNews();
    if (!editing) return;

    if (editing === 'new' && !this.newsForm.image) {
      this.newsActionError.set('Une image de couverture est obligatoire.');
      return;
    }

    if (this.tab() === 'decouvrir' && !this.newsForm.link_url.trim()) {
      this.newsActionError.set(
        'Une carte « À découvrir » n’a pas de texte rédigé : sans lien, elle ne mènerait nulle part.',
      );
      return;
    }

    if (this.tab() === 'actualites' && sanitizeRichText(this.newsForm.body ?? '').trim() === '') {
      this.newsActionError.set(
        'Rédigez le contenu de l’article — sans texte, il basculerait dans « À découvrir » plutôt que sur la page Actualités.',
      );
      return;
    }

    this.newsActionError.set(null);
    this.newsSaving.set(true);

    const form = this.newsForm;
    const request$ =
      editing === 'new'
        ? this.admin.createNews({
            title: form.title.trim(),
            excerpt: form.excerpt.trim() || undefined,
            category: form.category.trim() || undefined,
            body: form.body || undefined,
            image: form.image as File,
            video: form.video ?? undefined,
            videoUrl: form.video ? undefined : form.video_url.trim() || undefined,
            linkUrl: form.link_url.trim() || undefined,
            linkLabel: form.link_label.trim() || undefined,
            isPublished: form.is_published,
            position: form.position,
          })
        : this.admin.updateNews(editing.id, {
            title: form.title.trim(),
            excerpt: form.excerpt.trim() || undefined,
            category: form.category.trim() || undefined,
            body: form.body || undefined,
            image: form.image ?? undefined,
            video: form.video ?? undefined,
            removeVideo: form.removeVideo,
            // On n'écrase l'URL que si aucun fichier n'a été déposé —
            // le fichier l'emporte déjà côté serveur, envoyer les deux
            // prêterait à confusion sur ce qui sera réellement affiché.
            videoUrl: form.video ? undefined : form.video_url.trim(),
            linkUrl: form.link_url.trim(),
            linkLabel: form.link_label.trim(),
            isPublished: form.is_published,
            position: form.position,
          });

    request$.subscribe({
      next: () => {
        this.newsSaving.set(false);
        this.editingNews.set(null);
        this.loadNews();
      },
      error: (error: HttpErrorResponse) => {
        this.newsSaving.set(false);
        this.newsActionError.set(this.messageFor(error));
      },
    });
  }

  protected deleteNews(article: NewsArticleAdmin): void {
    this.newsActionError.set(null);
    this.admin.deleteNews(article.id).subscribe({
      next: () => this.newsArticles.update((list) => list.filter((item) => item.id !== article.id)),
      error: (error: HttpErrorResponse) => this.newsActionError.set(this.messageFor(error)),
    });
  }

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
      return first ?? body?.message ?? 'Données invalides.';
    }
    if (error.status === 403) {
      const body = error.error as { message?: string } | null;
      return body?.message ?? 'Action réservée aux comptes disposant du droit « paramètres ».';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
