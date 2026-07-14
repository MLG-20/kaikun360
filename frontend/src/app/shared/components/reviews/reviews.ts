import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

import { ReviewList } from '../../../core/api/review.service';

/**
 * Bloc « Témoignages / avis » réutilisable (F2.6).
 *
 * Auparavant recopié à l'identique dans chaque fiche (nuitée, expérience,
 * véhicule). Regroupé ici en un seul composant : on lui passe la réponse de
 * `GET /reviews` (`ReviewList` = liste d'avis publiés + synthèse note/quantité)
 * et il affiche la note moyenne en étoiles, puis la liste des avis.
 *
 * - Si `data` vaut `null` (avis non chargés / endpoint en échec), le composant
 *   n'affiche RIEN (la fiche reste propre — on ne montre pas un bloc vide).
 * - Si la liste est vide mais chargée, on affiche « Aucun avis pour le moment ».
 *
 * S'appuie sur les classes visuelles globales `uni-rating` / `uni-stars` /
 * `uni-reviews` / `uni-review` (définies dans `src/styles/_universe.scss`), déjà
 * partagées par les univers — d'où l'absence de feuille de style propre.
 */
@Component({
  selector: 'app-reviews',
  templateUrl: './reviews.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReviewsComponent {
  /** Réponse de `GET /reviews` (avis + synthèse), ou null si indisponible. */
  readonly data = input.required<ReviewList | null>();

  /** Titre du bloc (personnalisable selon l'univers). */
  readonly heading = input('Avis');

  /** Note moyenne arrondie à l'entier, pour le nombre d'étoiles pleines. */
  protected readonly averageStars = computed(() => {
    const avg = this.data()?.summary.average;
    return avg ? Math.round(avg) : 0;
  });

  /** Gabarit fixe des 5 étoiles (évite de recréer un tableau à chaque rendu). */
  protected readonly stars = [1, 2, 3, 4, 5];
}
