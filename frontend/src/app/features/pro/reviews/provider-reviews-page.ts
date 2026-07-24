import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { ProviderService } from '../../../core/api/provider.service';
import { ProviderReview, ProviderReviews } from '../../../models/provider.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** Une ligne de l'histogramme de répartition des notes (5★ → 1★). */
interface RatingBar {
  /** Note concernée (5 → 1). */
  star: number;
  /** Nombre d'avis à cette note. */
  count: number;
  /** Part du total, en pourcentage (largeur de la barre). */
  percent: number;
}

/**
 * Écran « Avis reçus » de l'espace prestataire (F5.5), monté sous
 * `/espace-prestataire/avis`. Réunit tous les avis publiés qui concernent le
 * prestataire (issus de `GET /providers/reviews`) : ceux laissés sur ses
 * ressources (véhicules, expériences) ET les avis directs déposés par le client
 * d'une mission terminée.
 *
 * En tête, une synthèse de notation (note moyenne, total, répartition par
 * étoiles) ; en dessous, la liste des avis avec leur source, leur auteur et leur
 * commentaire. Un état vide encourageant tant qu'aucun avis n'est publié.
 */
@Component({
  selector: 'app-provider-reviews-page',
  imports: [BackLinkComponent],
  templateUrl: './provider-reviews-page.html',
  styleUrl: './provider-reviews-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderReviewsPageComponent {
  private readonly providers = inject(ProviderService);

  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly data = signal<ProviderReviews | null>(null);

  /** Vrai quand la réponse est chargée mais ne contient aucun avis. */
  protected readonly isEmpty = computed(() => (this.data()?.summary.count ?? 0) === 0);

  /**
   * Répartition des notes en barres, de 5★ à 1★. Le pourcentage est calculé sur
   * le total d'avis (0 % si aucun) pour dimensionner chaque barre.
   */
  protected readonly bars = computed<RatingBar[]>(() => {
    const summary = this.data()?.summary;
    if (!summary) {
      return [];
    }
    const total = summary.count;
    return [5, 4, 3, 2, 1].map((star) => {
      const count = summary.distribution[String(star)] ?? 0;
      return { star, count, percent: total > 0 ? Math.round((count / total) * 100) : 0 };
    });
  });

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.providers.reviews().subscribe({
      next: (res) => {
        this.data.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.loadError.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Cinq positions d'étoiles (pleines jusqu'à `rating`) pour l'affichage. */
  protected stars(rating: number): boolean[] {
    return [1, 2, 3, 4, 5].map((position) => position <= Math.round(rating));
  }

  /** Accord singulier / pluriel de « avis » (invariable, mais l'article change). */
  protected reviewLabel(count: number): string {
    return count > 1 ? `${count} avis publiés` : `${count} avis publié`;
  }

  /** Formate la date de dépôt d'un avis (jour mois année). */
  protected formatDate(iso: string | null): string {
    if (!iso) {
      return '';
    }
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }

  /** Initiale de l'auteur pour la pastille d'avatar (ou « ? » si anonyme). */
  protected initial(review: ProviderReview): string {
    return review.author?.name?.trim()?.charAt(0)?.toUpperCase() || '?';
  }
}
