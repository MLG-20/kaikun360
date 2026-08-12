import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

import { GalleryComponent } from '../gallery/gallery';

/**
 * Coquille de fiche détaillée générique (F2.6).
 *
 * Toutes les fiches d'univers (bien immobilier, nuitée, expérience, véhicule)
 * partageaient exactement la même ossature : un bandeau de titre (fil d'Ariane +
 * titre + informations clés), éventuellement une galerie photo, puis un corps en
 * deux colonnes (contenu principal à gauche, encart d'action à droite). Ce
 * composant regroupe cette ossature une fois pour toutes ; chaque fiche n'a plus
 * qu'à FOURNIR son contenu dans les « emplacements » prévus (projection) :
 *
 * - `[crumbs]` : le fil d'Ariane (liens de retour) ;
 * - `[meta]`   : les informations clés affichées sous le titre (localisation,
 *   badge vérifié, capacité…), propres à chaque univers ;
 * - contenu par défaut (sans attribut) : les sections de la colonne principale ;
 * - `[aside]`  : l'encart latéral (prix, formulaire de demande, bouton WhatsApp).
 *
 * Le titre est passé en entrée simple (`title`) et la galerie est gérée par le
 * composant lui-même à partir de la liste d'images `images` (masquée tant
 * qu'aucune photo n'est disponible — dégradation gracieuse).
 *
 * L'habillage visuel réutilise les classes globales `uni-detail-*`
 * (`src/styles/_universe.scss`), déjà partagées par les univers.
 */
@Component({
  selector: 'app-detail-layout',
  imports: [GalleryComponent],
  templateUrl: './detail-layout.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DetailLayoutComponent {
  /** Titre principal de la fiche (nom du bien, du véhicule…). */
  readonly title = input.required<string>();

  /** Photos de la fiche ; vide → la galerie n'est pas affichée. */
  readonly images = input<string[]>([]);

  /** Texte alternatif des photos (par défaut : le titre). */
  readonly galleryAlt = input<string>('');

  /**
   * Photo de couverture du bandeau : la **première** de la liste (F13.6).
   *
   * ⚠️ « La première » n'est pas un choix arbitraire : côté serveur, la relation
   * `media` est triée principale d'abord, et c'est déjà cette même image que le
   * catalogue affiche en couverture (`photo_url`). Le bandeau de la fiche montre
   * donc exactement la photo sur laquelle le visiteur a cliqué — sans quoi il
   * douterait un instant d'être arrivé sur la bonne annonce.
   *
   * Vaut `null` quand l'annonce n'a aucune photo : le bandeau retombe alors sur
   * son dégradé de marque, et rien ne se casse.
   */
  readonly photoPrincipale = computed<string | null>(() => this.images()[0] ?? null);
}
