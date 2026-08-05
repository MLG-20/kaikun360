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

  /**
   * Comparaison (F8.15.e) : `true` affiche la case « Comparer » sous la carte.
   *
   * ⚠️ Réservé à l'immobilier — c'est le seul univers dont le serveur sait
   * comparer les fiches. La carte reste présentielle : elle ignore tout du
   * plafond de 4 et de la sélection, elle affiche ce qu'on lui dit et émet.
   */
  readonly comparable = input(false);
  /** Ce bien est-il déjà dans la sélection à comparer ? */
  readonly compared = input(false);
  /**
   * Sélection pleine ET ce bien non sélectionné : la case est désactivée, avec
   * l'explication en `title` — un contrôle qui refuse un clic sans rien dire se
   * lit comme un bug.
   */
  readonly compareDisabled = input(false);
  /** Émis à la bascule de la case « Comparer ». */
  readonly compareToggle = output<void>();

  /** Clic sur le cœur : ne déclenche jamais le lien de la carte. */
  protected onFavoriteClick(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    this.favoriteToggle.emit();
  }

  /**
   * Bascule de la case « Comparer ». Même précaution que le cœur : la carte
   * entière est cliquable (lien étiré), un clic sur la case ne doit jamais
   * emmener sur la fiche.
   */
  protected onCompareClick(event: Event): void {
    event.stopPropagation();
    this.compareToggle.emit();
  }
}
