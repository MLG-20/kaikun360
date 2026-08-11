import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/**
 * Tuile d'indicateur (F13.1) — un chiffre, et ce qu'il vaut par rapport à
 * avant.
 *
 * **Un chiffre seul ne dit rien.** « 12 400 000 F » n'est ni bon ni mauvais
 * tant qu'on ignore ce que valait la période précédente. La variation est donc
 * une partie de l'indicateur, pas une décoration.
 *
 * **La couleur suit le SENS, pas le signe.** Un taux d'annulation qui monte est
 * une mauvaise nouvelle ; un chiffre d'affaires qui monte, une bonne. La tuile
 * reçoit donc `goodWhenUp` et colore en conséquence — vert quand c'est
 * favorable, rouge quand ça ne l'est pas, indépendamment de la flèche. Et parce
 * que la couleur ne doit jamais porter seule le sens, la flèche et le
 * pourcentage disent la même chose en toutes lettres.
 */
@Component({
  selector: 'app-stat-tile',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <article class="st" [class.st--hero]="hero()">
      <p class="st__label">{{ label() }}</p>
      <p class="st__value">{{ display() }}</p>

      @if (variation(); as v) {
        <p class="st__delta" [class.st__delta--good]="v.good" [class.st__delta--bad]="!v.good">
          <span aria-hidden="true">{{ v.up ? '↑' : '↓' }}</span>
          <!-- Le pourcentage porte lui-même l'insécabilité, PAS le paragraphe :
               voir la note sur .st__pct. -->
          <span class="st__pct">{{ v.text }}</span>
          <span class="st__vs">{{ comparison() }}</span>
        </p>
      } @else {
        <p class="st__delta st__delta--flat">
          {{ noComparison() }}
        </p>
      }
    </article>
  `,
  styles: `
    :host {
      display: block;
      /* Autorise la tuile à rétrécir avec sa colonne de grille. */
      min-width: 0;
    }

    .st {
      background: var(--k-card);
      border: 1px solid var(--k-line);
      border-radius: var(--k-radius-lg);
      padding: 15px 16px;
      height: 100%;
      box-shadow: var(--k-shadow);
    }

    .st__label {
      margin: 0 0 8px;
      font-size: 0.78rem;
      color: var(--k-muted);
    }

    /* Grand nombre : chiffres PROPORTIONNELS. La largeur fixe (tabular-nums)
       est faite pour aligner des colonnes ; sur une valeur isolée de cette
       taille, elle donne un nombre distendu. */
    .st__value {
      margin: 0;
      font-family: var(--k-font-body);
      font-weight: 700;
      /* Taille FLUIDE : six tuiles côte à côte laissent peu de place, et un
         montant en francs est long. Le clamp évite qu'un « 1 225 080 F » aille
         à la ligne au milieu du nombre. */
      font-size: clamp(1.02rem, 1.35vw, 1.32rem);
      line-height: 1.15;
      color: var(--k-ink);
      letter-spacing: -0.01em;
    }

    /* La tuile de tête porte le chiffre d'affaires : c'est le seul nombre de la
       page qui a droit à cette taille. Deux nombres « les plus importants » ne
       sont plus une hiérarchie. */
    .st--hero {
      background: linear-gradient(160deg, var(--k-navy) 0%, var(--k-navy-2) 100%);
      border-color: transparent;

      .st__label {
        color: rgba(255, 255, 255, 0.7);
      }

      .st__value {
        color: #fff;
        font-size: clamp(1.3rem, 1.8vw, 1.95rem);
      }

      .st__vs {
        color: rgba(255, 255, 255, 0.55);
      }

      .st__delta--flat {
        color: rgba(255, 255, 255, 0.6);
      }
    }

    /* La variation tient sur DEUX lignes : le pourcentage, puis la période de
       comparaison en dessous. Sur une seule ligne, une tuile étroite coupait
       « +43,4 » de son « % » et « vs 12 mois » de ses « précédents » — trois
       fragments empilés qui ne se lisaient plus comme une phrase.

       ⚠️ L'insécabilité est portée par les FRAGMENTS (.st__pct, .st__vs), jamais
       par ce paragraphe. Posée ici, elle s'appliquait aussi au message de repli
       « Première activité sur ce poste » : une phrase longue devenue insécable
       impose sa largeur en minimum à toute la tuile, donc à toute la colonne de
       grille. Vu en recette sur des données réelles sans historique — les six
       tuiles affichaient ce message, et la tuile de tête, seule assez
       compressible pour céder, se retrouvait écrasée à 75 px. Invisible sur des
       données de démonstration, qui avaient toutes une période précédente. */
    .st__delta {
      display: flex;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 0 5px;
      margin: 9px 0 0;
      font-size: 0.78rem;
      font-weight: 600;

      &--good {
        color: var(--k-success);
      }

      &--bad {
        color: var(--k-danger);
      }

      &--flat {
        color: var(--k-muted);
        font-weight: 400;
      }
    }

    .st--hero .st__delta--good {
      /* Le vert de la charte n'a pas assez de contraste sur le fond marine :
         sur la tuile de tête, la variation favorable prend une version claire
         de la même teinte. */
      color: #7fe3b0;
    }

    .st--hero .st__delta--bad {
      color: #ff9aa7;
    }

    /* « +43,4 % » ne se coupe jamais entre le nombre et son signe. */
    .st__pct {
      white-space: nowrap;
    }

    .st__vs {
      /* Passe systématiquement à la ligne : la période de comparaison est un
         complément, pas une part du chiffre. */
      flex-basis: 100%;
      margin-top: 2px;
      font-weight: 400;
      color: var(--k-muted);
      font-size: 0.72rem;
    }
  `,
})
export class StatTileComponent {
  readonly label = input.required<string>();
  /** Valeur déjà mise en forme (francs, pourcentage, nombre…). */
  readonly display = input.required<string>();
  readonly value = input.required<number>();
  readonly previous = input.required<number>();
  /** Une hausse est-elle une bonne nouvelle ? (faux pour un taux d'annulation) */
  readonly goodWhenUp = input<boolean>(true);
  /** Tuile de tête, sur fond marine. Une seule par écran. */
  readonly hero = input<boolean>(false);
  /** Formule de comparaison, ex. « vs 12 mois précédents ». */
  readonly comparison = input<string>('sur la période précédente');

  /**
   * La variation, ou `null` quand elle n'a pas de sens.
   *
   * Passer de 0 à 40 000 F n'est pas « +∞ % » ni « +100 % » : c'est un
   * démarrage. On préfère ne rien afficher plutôt qu'un pourcentage faux —
   * l'écran est lu par des gens qui décident.
   */
  protected readonly variation = computed(() => {
    const previous = this.previous();
    const value = this.value();

    if (previous === 0 || value === previous) {
      return null;
    }

    const change = ((value - previous) / Math.abs(previous)) * 100;
    const up = change > 0;

    return {
      up,
      good: up === this.goodWhenUp(),
      text: (up ? '+' : '−') + Math.abs(Math.round(change * 10) / 10).toString().replace('.', ',') + ' %',
    };
  });

  /** Ce qu'on écrit quand il n'y a pas de variation à montrer. */
  protected readonly noComparison = computed(() => {
    if (this.value() === this.previous()) {
      return 'Stable ' + this.comparison();
    }

    return this.previous() === 0 && this.value() > 0
      ? 'Première activité sur ce poste'
      : 'Pas de comparaison possible';
  });
}
