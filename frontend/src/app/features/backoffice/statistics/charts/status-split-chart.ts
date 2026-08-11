import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

import { StatusShare } from '../../../../models/statistics.model';
import { STATUS_COLORS, fullNumber } from './chart-tokens';

/** Une part prête à afficher. */
interface Share {
  key: string;
  label: string;
  count: number;
  /** Déjà mis en forme (virgule décimale française). */
  percent: string;
  /** Part en pourcentage, pour la largeur de la barre. */
  width: number;
  color: string;
}

/**
 * Où en sont les réservations de la période (F13.1).
 *
 * **Une barre part-à-tout, pas un camembert.** Cinq parts dont deux se
 * ressemblent sont impossibles à comparer sur un disque : l'œil compare mal des
 * angles. Alignées sur une même barre, elles se comparent par leur longueur,
 * ce que l'œil fait très bien.
 *
 * **Couleurs d'ÉTAT, pas d'identité.** Ici la teinte veut dire quelque chose —
 * terminé est bon, annulé est mauvais, en attente est en suspens. Ce sont donc
 * les jetons d'état de la charte, jamais la palette des univers métier : une
 * même couleur ne peut pas signifier « le tourisme » sur un graphique et
 * « c'est grave » sur le suivant. Et parce qu'une couleur ne doit jamais porter
 * seule le sens, chaque part est nommée ET chiffrée juste en dessous.
 */
@Component({
  selector: 'app-status-split-chart',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (total() === 0) {
      <p class="sp__empty">Aucune réservation sur la période.</p>
    } @else {
      <div
        class="sp__bar"
        role="img"
        [attr.aria-label]="ariaLabel()"
      >
        @for (share of shares(); track share.key) {
          <span
            class="sp__part"
            [style.width.%]="share.width"
            [style.background]="share.color"
          ></span>
        }
      </div>

      <ul class="sp__list">
        @for (share of shares(); track share.key) {
          <li class="sp__item">
            <span class="sp__key" [style.background]="share.color" aria-hidden="true"></span>
            <span class="sp__label">{{ share.label }}</span>
            <span class="sp__value">{{ number(share.count) }}</span>
            <span class="sp__pct">{{ share.percent }} %</span>
          </li>
        }
      </ul>
    }
  `,
  styles: `
    :host {
      display: block;
    }

    /* Les parts sont séparées par un VIDE à la couleur du fond (le gap du
       flex), pas par un contour : un trait autour de chaque part ajouterait de
       l'encre sans donnée. */
    .sp__bar {
      display: flex;
      gap: 2px;
      height: 22px;
      border-radius: var(--k-radius-pill);
      overflow: hidden;
      background: #f2f5fa;
    }

    .sp__part {
      display: block;
      min-width: 2px;
      animation: sp-in 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
    }

    .sp__part:first-child {
      border-radius: var(--k-radius-pill) 0 0 var(--k-radius-pill);
    }

    .sp__part:last-child {
      border-radius: 0 var(--k-radius-pill) var(--k-radius-pill) 0;
    }

    .sp__list {
      list-style: none;
      margin: 18px 0 0;
      padding: 0;
    }

    .sp__item {
      display: grid;
      grid-template-columns: 12px 1fr auto auto;
      align-items: center;
      gap: 10px;
      padding: 7px 0;
      border-bottom: 1px solid var(--k-line);
      font-size: 0.84rem;

      &:last-child {
        border-bottom: 0;
      }
    }

    .sp__key {
      width: 11px;
      height: 11px;
      border-radius: 3px;
    }

    .sp__label {
      color: var(--k-ink);
    }

    .sp__value {
      font-weight: 700;
      color: var(--k-ink);
      font-variant-numeric: tabular-nums;
    }

    .sp__pct {
      color: var(--k-muted);
      font-size: 0.78rem;
      min-width: 44px;
      text-align: right;
      font-variant-numeric: tabular-nums;
    }

    .sp__empty {
      margin: 0;
      color: var(--k-muted);
      font-size: 0.85rem;
    }

    @keyframes sp-in {
      from {
        transform: scaleX(0);
        transform-origin: left center;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .sp__part {
        animation: none;
      }
    }
  `,
})
export class StatusSplitChartComponent {
  readonly statuses = input.required<readonly StatusShare[]>();

  protected readonly number = fullNumber;

  protected readonly total = computed(() =>
    this.statuses().reduce((sum, status) => sum + status.count, 0),
  );

  /** Les parts NON VIDES : une part à zéro n'a rien à montrer sur une barre. */
  protected readonly shares = computed<Share[]>(() => {
    const total = this.total();

    return this.statuses()
      .filter((status) => status.count > 0)
      .map((status) => ({
        key: status.key,
        label: status.label,
        count: status.count,
        width: total > 0 ? (status.count / total) * 100 : 0,
        // Une décimale, virgule française : « 6,8 % » et non « 6.8 % ».
        percent: total > 0
          ? String(Math.round((status.count / total) * 1000) / 10).replace('.', ',')
          : '0',
        color: STATUS_COLORS[status.key] ?? STATUS_COLORS['en_attente'],
      }));
  });

  protected readonly ariaLabel = computed(
    () =>
      'Répartition des réservations par statut : ' +
      this.shares()
        .map((share) => `${share.label}, ${share.count}`)
        .join(' ; '),
  );
}
