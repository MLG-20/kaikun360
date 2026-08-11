import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';

import { TopListing } from '../../../../models/statistics.model';
import { CHART_INK, NEUTRAL_SHARE, RANKING_RAMP, compactXof, fullXof } from './chart-tokens';

/** Une part du disque, prête à peindre. */
interface Slice {
  key: string;
  label: string;
  detail: string;
  amount: string;
  color: string;
  /** Longueur de l'arc, en centièmes du tour. */
  length: number;
  /** Décalage cumulé, en centièmes du tour. */
  offset: number;
  percent: string;
}

/**
 * Ce qui rapporte le plus (F13.2) — diagramme circulaire.
 *
 * **C'est un part-à-tout, et il l'est vraiment.** Les cinq annonces de tête ne
 * font pas le chiffre d'affaires à elles seules ; une part « Autres annonces »,
 * calculée par différence avec le volume total de la période, ferme le disque.
 * Sans elle, les parts totaliseraient 100 % d'un ensemble qui n'est pas le tout
 * — un camembert dont la somme n'est pas le tout est un mensonge de forme, et
 * il exagère mécaniquement le poids des premiers.
 *
 * **Une seule teinte, du foncé au clair.** Un classement est ORDONNÉ : la
 * couleur porte le rang. Le « reste » est en gris, hors rampe, parce qu'il n'a
 * pas de rang.
 *
 * ⚠️ **Un disque compare mal deux parts voisines** — l'œil compare mal des
 * angles. C'est assumé ici parce que la question posée est « quelle place
 * prend cette annonce dans le total ? », à laquelle le disque répond très bien,
 * et parce que **chaque part est chiffrée dans la légende** : la comparaison
 * exacte se fait sur les nombres, pas sur les angles.
 */
@Component({
  selector: 'app-ranking-donut-chart',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (slices().length === 0) {
      <p class="dn__empty">Aucune réservation payante sur la période.</p>
    } @else {
      <div class="dn">
        <div class="dn__figure">
          <svg viewBox="0 0 200 200" role="img" [attr.aria-label]="ariaLabel()">
            <g transform="rotate(-90 100 100)">
              @for (slice of slices(); track slice.key; let i = $index) {
                <!-- pathLength="100" fait travailler le tracé en centièmes de
                     tour : les longueurs d'arc sont alors directement des
                     pourcentages, sans calcul de circonférence. -->
                <circle
                  class="dn__arc"
                  cx="100"
                  cy="100"
                  r="72"
                  fill="none"
                  pathLength="100"
                  [attr.stroke]="slice.color"
                  [attr.stroke-width]="hover() === i ? 34 : 28"
                  [attr.stroke-dasharray]="slice.length + ' ' + (100 - slice.length)"
                  [attr.stroke-dashoffset]="-slice.offset"
                  (pointerenter)="hover.set(i)"
                  (pointerleave)="hover.set(null)"
                />
              }
            </g>

            <!-- Le centre porte le total : le disque dit des proportions, le
                 chiffre dit de quoi. -->
            <text x="100" y="95" text-anchor="middle" class="dn__total">{{ compact(total()) }}</text>
            <text x="100" y="113" text-anchor="middle" class="dn__caption">volume encaissable</text>
          </svg>
        </div>

        <!-- La légende porte les VALEURS : c'est elle qui permet de comparer
             deux annonces proches, ce qu'un angle ne permet pas. -->
        <ul class="dn__legend">
          @for (slice of slices(); track slice.key; let i = $index) {
            <li
              class="dn__item"
              [class.dn__item--on]="hover() === i"
              (pointerenter)="hover.set(i)"
              (pointerleave)="hover.set(null)"
            >
              <span class="dn__key" [style.background]="slice.color" aria-hidden="true"></span>
              <span class="dn__name" [title]="slice.label">
                {{ slice.label }}
                <small>{{ slice.detail }}</small>
              </span>
              <span class="dn__value">
                {{ slice.amount }}
                <small>{{ slice.percent }} %</small>
              </span>
            </li>
          }
        </ul>
      </div>
    }
  `,
  styles: `
    :host {
      display: block;
    }

    .dn {
      display: flex;
      align-items: center;
      gap: 22px;
      flex-wrap: wrap;
    }

    .dn__figure {
      flex: 0 0 200px;
      max-width: 200px;
    }

    .dn__figure svg {
      display: block;
      width: 100%;
      height: auto;
    }

    /* L'anneau grossit au survol au lieu de changer de couleur : la teinte
       porte le rang, elle ne doit pas bouger. */
    .dn__arc {
      transition: stroke-width 0.15s ease;
      cursor: default;
      animation: dn-in 0.7s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
    }

    @keyframes dn-in {
      from {
        opacity: 0;
        transform: scale(0.92);
        transform-origin: center;
        transform-box: fill-box;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .dn__arc {
        animation: none;
      }
    }

    .dn__total {
      font-family: var(--k-font-body);
      font-size: 21px;
      font-weight: 700;
      fill: #11213c;
    }

    .dn__caption {
      font-family: var(--k-font-body);
      font-size: 9.5px;
      fill: #66738b;
    }

    .dn__legend {
      flex: 1 1 240px;
      min-width: 0;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .dn__item {
      display: grid;
      grid-template-columns: 11px 1fr auto;
      align-items: center;
      gap: 10px;
      padding: 6px 8px 6px 0;
      border-bottom: 1px solid var(--k-line);
      border-radius: 6px;
      transition: background 0.15s ease;

      &:last-child {
        border-bottom: 0;
      }

      &--on {
        background: #f4f7fc;
      }
    }

    .dn__key {
      width: 11px;
      height: 11px;
      border-radius: 3px;
    }

    .dn__name {
      min-width: 0;
      font-size: 0.82rem;
      color: var(--k-ink);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;

      small {
        display: block;
        font-size: 0.7rem;
        color: var(--k-muted);
      }
    }

    .dn__value {
      text-align: right;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--k-ink);
      white-space: nowrap;

      small {
        display: block;
        font-weight: 400;
        font-size: 0.7rem;
        color: var(--k-muted);
      }
    }

    .dn__empty {
      margin: 0;
      color: var(--k-muted);
      font-size: 0.85rem;
    }
  `,
})
export class RankingDonutChartComponent {
  readonly listings = input.required<readonly TopListing[]>();
  /**
   * Volume encaissable TOTAL de la période — celui des tuiles d'en-tête.
   *
   * Sert à calculer la part « Autres annonces » par différence. Sans lui, le
   * disque ne représenterait que les cinq premières, en faisant croire qu'elles
   * sont le tout.
   */
  readonly total = input.required<number>();

  protected readonly ink = CHART_INK;
  protected readonly compact = compactXof;

  /** Index de la part survolée (le disque et la légende s'éclairent ensemble). */
  protected readonly hover = signal<number | null>(null);

  protected readonly slices = computed<Slice[]>(() => {
    const listings = this.listings();

    if (listings.length === 0) {
      return [];
    }

    const classe = listings.reduce((sum, listing) => sum + listing.gross_volume_xof, 0);
    // Le reste ne peut pas être négatif : si le total servi était plus petit que
    // la somme des premières (données incohérentes), on préfère un disque sans
    // part « Autres » à une part fantôme.
    const reste = Math.max(0, this.total() - classe);
    const tour = classe + reste;

    if (tour <= 0) {
      return [];
    }

    // 0,5 centième de tour retiré à chaque part : c'est l'écart à la couleur du
    // fond qui sépare deux parts voisines, à la place d'un contour.
    const ecart = 0.5;

    const parts = [
      ...listings.map((listing, index) => ({
        key: 'top-' + index,
        label: listing.label,
        detail: listing.line + ' · ' + listing.bookings + (listing.bookings > 1 ? ' réservations' : ' réservation'),
        value: listing.gross_volume_xof,
        color: RANKING_RAMP[Math.min(index, RANKING_RAMP.length - 1)],
      })),
    ];

    if (reste > 0) {
      parts.push({
        key: 'reste',
        label: 'Autres annonces',
        detail: 'Tout le reste de la période',
        value: reste,
        color: NEUTRAL_SHARE,
      });
    }

    let cumul = 0;

    return parts.map((part) => {
      const share = (part.value / tour) * 100;
      const slice: Slice = {
        key: part.key,
        label: part.label,
        detail: part.detail,
        amount: fullXof(part.value),
        color: part.color,
        // Un plancher garde visible une part minuscule : à zéro elle
        // disparaîtrait du disque alors qu'elle figure dans la légende.
        length: Math.max(0.4, share - ecart),
        offset: cumul,
        percent: String(Math.round(share * 10) / 10).replace('.', ','),
      };

      cumul += share;

      return slice;
    });
  });

  protected readonly ariaLabel = computed(
    () =>
      'Répartition du volume encaissable : ' +
      this.slices()
        .map((slice) => `${slice.label}, ${slice.percent} %`)
        .join(' ; '),
  );
}
