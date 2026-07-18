import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';

import { FavoritableRef } from '../../../models/favorite.model';
import { VerificationBadgeComponent } from '../verification-badge/verification-badge';

/**
 * Carte de bien / service (F0.4) — brique du catalogue, réutilisable pour tous
 * les univers (biens, nuitées, véhicules, expériences…).
 *
 * Générique et « présentielle » : pilotée par ses `input()`, sans logique métier.
 * En l'absence d'image, un dégradé de marque sert de vignette de repli.
 *
 * Favoris (tous univers) : si `favoritable` est fourni, un **cœur** est affiché
 * en surimpression. La carte reste présentielle — elle ne fait qu'émettre
 * `favoriteToggle` ; c'est la page hôte qui appelle le service et gère l'état
 * (`favorited`, `favoriteBusy`), et redirige l'anonyme vers la connexion.
 */
@Component({
  selector: 'app-listing-card',
  imports: [VerificationBadgeComponent, RouterLink],
  templateUrl: './listing-card.html',
  styleUrl: './listing-card.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ListingCardComponent {
  readonly title = input.required<string>();
  readonly location = input<string | null>(null);
  /** Prix formaté, ex. « 150 000 F ». */
  readonly price = input<string | null>(null);
  /** Unité, ex. « / mois », « / nuit ». */
  readonly priceUnit = input<string | null>(null);
  /** Libellé de badge de vérification (masqué si null). */
  readonly badge = input<string | null>(null);
  readonly cta = input('Découvrir');
  /** URL d'image ; si absente, une vignette dégradée est utilisée. */
  readonly image = input<string | null>(null);
  /**
   * Cible `routerLink` de la fiche détaillée (ex. `['/immobilier', 12]`).
   * Si `null`, la carte n'est pas cliquable (CTA neutralisé).
   */
  readonly link = input<(string | number)[] | null>(null);

  /**
   * Élément favorisable ({ type, id }). Si non-null, le cœur est affiché.
   * Null = pas de fonctionnalité de favori sur cette carte.
   */
  readonly favoritable = input<FavoritableRef | null>(null);
  /** L'élément est-il déjà dans les favoris de l'utilisateur ? (cœur plein). */
  readonly favorited = input(false);
  /** Un appel favori est-il en cours ? (cœur désactivé le temps de la requête). */
  readonly favoriteBusy = input(false);
  /** Émis au clic sur le cœur (la page hôte fait l'appel service + login si anonyme). */
  readonly favoriteToggle = output<void>();

  /** Clic sur le cœur : ne déclenche jamais le lien de la carte. */
  protected onFavoriteClick(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    this.favoriteToggle.emit();
  }
}
