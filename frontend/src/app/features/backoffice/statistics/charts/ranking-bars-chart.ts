import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

import { TopListing } from '../../../../models/statistics.model';
import { fullXof, seriesColor } from './chart-tokens';

/** Une ligne du palmarès, avec sa largeur relative. */
interface Row {
  label: string;
  line: string;
  bookings: number;
  amount: string;
  width: number;
}

/**
 * Palmarès des annonces qui rapportent (F13.1).
 *
 * **Une seule couleur pour toutes les barres.** La tentation est de foncer la
 * teinte à mesure que le montant grimpe : ce serait dire deux fois la même
 * chose — la longueur de la barre porte déjà le classement — et dépenser le
 * seul canal libre, l'identité, pour une information déjà lisible. Les annonces
 * n'ont pas d'ordre naturel entre elles ; elles forment UNE série, donc une
 * couleur, donc pas de légende : le titre de la carte dit ce qui est mesuré.
 *
 * Barres HORIZONTALES, à dessein : les noms d'annonces sont longs
 * (« Nuitée — Villa des Almadies »). En colonnes verticales il aurait fallu les
 * incliner ou les tronquer ; à l'horizontale, ils se lisent normalement.
 */
@Component({
  selector: 'app-ranking-bars-chart',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (rows().length === 0) {
      <p class="rk__empty">Aucune réservation payante sur la période.</p>
    } @else {
      <ol class="rk">
        @for (row of rows(); track row.label; let i = $index) {
          <li class="rk__row">
            <div class="rk__head">
              <span class="rk__label" [title]="row.label">{{ row.label }}</span>
              <span class="rk__amount">{{ row.amount }}</span>
            </div>

            <div class="rk__track">
              <div
                class="rk__bar"
                [style.width.%]="row.width"
                [style.background]="color"
                [style.animation-delay.ms]="i * 70"
              ></div>
            </div>

            <p class="rk__meta">
              {{ row.line }} · {{ row.bookings }}
              {{ row.bookings > 1 ? 'réservations' : 'réservation' }}
            </p>
          </li>
        }
      </ol>
    }
  `,
  styles: `
    :host {
      display: block;
    }

    .rk {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .rk__row + .rk__row {
      margin-top: 16px;
    }

    .rk__head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 6px;
    }

    /* Le nom est tronqué proprement s'il déborde — jamais rogné par la barre
       elle-même. L'intitulé complet reste disponible en infobulle native et
       dans la vue tableau. */
    .rk__label {
      font-size: 0.86rem;
      color: var(--k-ink);
      font-weight: 500;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      min-width: 0;
    }

    .rk__amount {
      flex: none;
      font-size: 0.86rem;
      font-weight: 700;
      color: var(--k-ink);
    }

    .rk__track {
      height: 10px;
      border-radius: var(--k-radius-pill);
      background: #f2f5fa;
      overflow: hidden;
    }

    .rk__bar {
      height: 100%;
      border-radius: var(--k-radius-pill);
      transform-origin: left center;
      animation: rk-grow 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
    }

    .rk__meta {
      margin: 5px 0 0;
      font-size: 0.75rem;
      color: var(--k-muted);
    }

    .rk__empty {
      margin: 0;
      color: var(--k-muted);
      font-size: 0.85rem;
    }

    @keyframes rk-grow {
      from {
        transform: scaleX(0);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .rk__bar {
        animation: none;
      }
    }
  `,
})
export class RankingBarsChartComponent {
  readonly listings = input.required<readonly TopListing[]>();

  /** Slot 1 de la palette : une série, une couleur. */
  protected readonly color = seriesColor(0);

  protected readonly rows = computed<Row[]>(() => {
    const listings = this.listings();
    // Référence : le PREMIER du classement, qui vaut donc 100 % de la barre.
    const top = Math.max(...listings.map((listing) => listing.gross_volume_xof), 0);

    return listings.map((listing) => ({
      label: listing.label,
      line: listing.line,
      bookings: listing.bookings,
      amount: fullXof(listing.gross_volume_xof),
      width: top > 0 ? Math.max(2, (listing.gross_volume_xof / top) * 100) : 0,
    }));
  });
}
