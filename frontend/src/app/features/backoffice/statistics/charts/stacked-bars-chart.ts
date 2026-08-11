import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';

import { BookingsByLinePoint, BusinessLine } from '../../../../models/statistics.model';
import { CHART_INK, niceTicks, seriesColor } from './chart-tokens';

/** Un segment prêt à peindre : sa géométrie et sa couleur. */
interface Segment {
  key: string;
  color: string;
  y: number;
  height: number;
}

/** Une colonne : sa zone de survol, ses segments, son total. */
interface Column {
  key: string;
  label: string;
  x: number;
  width: number;
  total: number;
  clip: string;
  segments: Segment[];
}

/**
 * Colonnes empilées — les réservations par univers métier (F13.1).
 *
 * Répond à « d'où vient l'activité, et comment la répartition bouge-t-elle ? ».
 * L'empilement est le bon geste ici parce que la question est part-à-tout : la
 * hauteur totale donne le volume de la période, et les tranches disent qui l'a
 * produit. Cinq courbes séparées auraient répondu à une autre question.
 *
 * Trois partis pris de dessin, tous au service de la lisibilité :
 *   - les colonnes sont PLAFONNÉES en largeur au lieu de remplir leur case :
 *     l'air entre elles est ce qui les rend dénombrables ;
 *   - deux tranches voisines sont séparées par un vide de 2 px à la couleur du
 *     fond, jamais par un contour — un trait autour d'une marque ajoute de
 *     l'encre qui ne porte aucune donnée ;
 *   - seul le sommet de la pile est arrondi ; la base reste franche, posée sur
 *     la ligne de zéro.
 */
@Component({
  selector: 'app-stacked-bars-chart',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="sb">
      <svg
        class="sb__svg"
        [attr.viewBox]="'0 0 ' + W + ' ' + H"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        [attr.aria-label]="ariaLabel()"
        (pointerleave)="hover.set(null)"
      >
        @for (tick of ticks(); track tick) {
          <line
            [attr.x1]="PAD_L"
            [attr.x2]="W - PAD_R"
            [attr.y1]="y(tick)"
            [attr.y2]="y(tick)"
            [attr.stroke]="tick === 0 ? ink.axis : ink.grid"
            stroke-width="1"
          />
          <text [attr.x]="PAD_L - 10" [attr.y]="y(tick) + 4" text-anchor="end" class="sb__tick">
            {{ tick }}
          </text>
        }

        @for (column of columns(); track column.key; let i = $index) {
          <!-- Le sommet arrondi est obtenu en DÉCOUPANT la pile entière : les
               tranches restent de simples rectangles, et l'arrondi ne s'applique
               qu'une fois, au bon endroit. -->
          <clipPath [attr.id]="clipId(i)">
            <path [attr.d]="column.clip" />
          </clipPath>

          <g [attr.clip-path]="'url(#' + clipId(i) + ')'" class="sb__col">
            @for (segment of column.segments; track segment.key) {
              <rect
                [attr.x]="column.x"
                [attr.y]="segment.y"
                [attr.width]="column.width"
                [attr.height]="segment.height"
                [attr.fill]="segment.color"
              />
            }
          </g>

          <!-- Cible de survol : toute la HAUTEUR de la case, pas la seule pile.
               Viser une tranche de trois pixels est impossible ; viser la
               colonne, non. -->
          <rect
            [attr.x]="column.x - gap() / 2"
            [attr.y]="PAD_T"
            [attr.width]="column.width + gap()"
            [attr.height]="H - PAD_T - PAD_B"
            fill="transparent"
            (pointerenter)="hover.set(i)"
          />

          @if (i % labelStride() === 0) {
            <text
              [attr.x]="column.x + column.width / 2"
              [attr.y]="H - 10"
              text-anchor="middle"
              class="sb__tick"
            >
              {{ column.label }}
            </text>
          }
        }
      </svg>

      @if (hovered(); as column) {
        <div
          class="sb__tip"
          [style.left.%]="((column.x + column.width / 2) / W) * 100"
          [class.sb__tip--flip]="hover()! > columns().length / 2"
        >
          <p class="sb__tip-date">{{ column.label }}</p>
          @for (segment of tipRows(column); track segment.key) {
            <p class="sb__tip-row">
              <span class="sb__tip-key" [style.background]="segment.color"></span>
              <strong>{{ segment.count }}</strong>
              <span class="sb__tip-name">{{ segment.label }}</span>
            </p>
          }
          <p class="sb__tip-total">{{ column.total }} au total</p>
        </div>
      }
    </div>
  `,
  styles: `
    :host {
      display: block;
    }

    .sb {
      position: relative;
    }

    .sb__svg {
      display: block;
      width: 100%;
      height: auto;
      overflow: visible;
    }

    .sb__tick {
      font-family: var(--k-font-body);
      font-size: 11px;
      fill: #66738b;
    }

    /* Les piles montent de la ligne de zéro. La base de la transformation est
       ancrée en bas du dessin, pour que la croissance parte du sol. */
    .sb__col {
      transform-box: view-box;
      transform-origin: center bottom;
      animation: sb-grow 0.7s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
    }

    @keyframes sb-grow {
      from {
        transform: scaleY(0);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .sb__col {
        animation: none;
      }
    }

    .sb__tip {
      position: absolute;
      top: 8px;
      transform: translateX(12px);
      pointer-events: none;
      background: var(--k-card);
      border: 1px solid var(--k-line);
      border-radius: var(--k-radius-sm);
      box-shadow: var(--k-shadow);
      padding: 9px 12px;
      min-width: 150px;
      z-index: 2;

      &--flip {
        transform: translateX(calc(-100% - 12px));
      }
    }

    .sb__tip-date {
      margin: 0 0 6px;
      font-size: 0.72rem;
      color: var(--k-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .sb__tip-row {
      display: flex;
      align-items: center;
      gap: 7px;
      margin: 0 0 3px;
      font-size: 0.82rem;
      color: var(--k-ink);
    }

    .sb__tip-key {
      width: 10px;
      height: 10px;
      border-radius: 3px;
      flex: none;
    }

    .sb__tip-name {
      color: var(--k-muted);
      font-size: 0.76rem;
    }

    .sb__tip-total {
      margin: 7px 0 0;
      padding-top: 6px;
      border-top: 1px solid var(--k-line);
      font-size: 0.76rem;
      color: var(--k-muted);
    }
  `,
})
export class StackedBarsChartComponent {
  readonly lines = input.required<readonly BusinessLine[]>();
  readonly points = input.required<readonly BookingsByLinePoint[]>();

  protected readonly W = 760;
  // Hauteur choisie pour le RATIO, pas en pixels : le SVG est mis à l'échelle
  // par sa largeur. À 760 × 260, une carte pleine largeur donne un graphique
  // d'environ 400 px de haut — assez pour lire la pente, sans occuper à lui
  // seul tout l'écran (mesuré à la capture : 300 en donnait 480).
  protected readonly H = 260;
  protected readonly PAD_L = 46;
  protected readonly PAD_R = 18;
  protected readonly PAD_T = 16;
  protected readonly PAD_B = 30;

  /** Épaisseur maximale d'une colonne : au-delà, le dessin devient massif. */
  private readonly MAX_BAR = 24;
  /** Vide, à la couleur du fond, entre deux tranches d'une même pile. */
  private readonly SEGMENT_GAP = 2;
  /** Rayon du sommet de la pile. */
  private readonly TOP_RADIUS = 4;

  protected readonly ink = CHART_INK;
  protected readonly hover = signal<number | null>(null);

  /** Sommet de l'axe : le plus haut TOTAL empilé, arrondi au repère rond. */
  protected readonly ticks = computed(() => {
    const max = Math.max(0, ...this.points().map((point) => this.total(point)));

    // Des réservations se comptent en entiers : une graduation « 2,5 » n'aurait
    // pas de sens. ⚠️ C'est `niceTicks` qui doit le savoir — filtrer les valeurs
    // non entières APRÈS coup jetterait aussi la dernière graduation, donc le
    // sommet de l'échelle, et les piles sortiraient du cadre par le haut.
    return niceTicks(max, 4, true);
  });

  private readonly maxValue = computed(() => {
    const ticks = this.ticks();

    return ticks[ticks.length - 1] || 1;
  });

  protected readonly labelStride = computed(() =>
    Math.max(1, Math.ceil(this.points().length / 6)),
  );

  /** Largeur d'une case (colonne + son air). */
  protected readonly slot = computed(() => {
    const count = Math.max(1, this.points().length);

    return (this.W - this.PAD_L - this.PAD_R) / count;
  });

  /** Air laissé de part et d'autre d'une colonne dans sa case. */
  protected readonly gap = computed(() => this.slot() - Math.min(this.MAX_BAR, this.slot() * 0.62));

  /** Toutes les colonnes, prêtes à peindre. */
  protected readonly columns = computed<Column[]>(() => {
    const width = Math.min(this.MAX_BAR, this.slot() * 0.62);
    const baseline = this.H - this.PAD_B;

    return this.points().map((point, index) => {
      const x = this.PAD_L + index * this.slot() + (this.slot() - width) / 2;
      const total = this.total(point);

      const segments: Segment[] = [];
      let cursor = baseline;

      this.lines().forEach((line, lineIndex) => {
        const value = point.values[line.key] ?? 0;

        if (value <= 0) {
          return;
        }

        const rawHeight = (value / this.maxValue()) * (this.H - this.PAD_T - this.PAD_B);
        cursor -= rawHeight;

        segments.push({
          key: line.key,
          color: seriesColor(lineIndex),
          y: cursor,
          // Le vide de séparation est PRIS SUR la tranche, ce qui la raccourcit
          // de deux pixels sans jamais la faire disparaître : une tranche
          // minuscule reste visible, elle est simplement fine.
          height: Math.max(1, rawHeight - this.SEGMENT_GAP),
        });
      });

      return {
        key: point.key,
        label: point.label,
        x,
        width,
        total,
        clip: this.topRounded(x, cursor, width, baseline),
        segments,
      };
    });
  });

  protected readonly hovered = computed(() => {
    const index = this.hover();

    return index === null ? null : (this.columns()[index] ?? null);
  });

  protected readonly ariaLabel = computed(() => {
    const points = this.points();

    if (points.length === 0) {
      return 'Aucune réservation sur la période.';
    }

    return `Réservations par univers métier, de ${points[0].label} à ${points[points.length - 1].label}. Le détail chiffré est disponible via le bouton Données.`;
  });

  /**
   * Lignes de l'infobulle : les univers PRÉSENTS ce mois-là, du plus gros au
   * plus petit. Lister les cinq univers dont trois à zéro noierait la lecture.
   */
  protected tipRows(column: Column): { key: string; label: string; color: string; count: number }[] {
    const point = this.points().find((p) => p.key === column.key);

    if (!point) {
      return [];
    }

    return this.lines()
      .map((line, index) => ({
        key: line.key,
        label: line.label,
        color: seriesColor(index),
        count: point.values[line.key] ?? 0,
      }))
      .filter((row) => row.count > 0)
      .sort((a, b) => b.count - a.count);
  }

  protected clipId(index: number): string {
    return `stack-clip-${index}`;
  }

  protected y(value: number): number {
    const usable = this.H - this.PAD_T - this.PAD_B;

    return this.H - this.PAD_B - (value / this.maxValue()) * usable;
  }

  /** Total empilé d'un point. */
  private total(point: BookingsByLinePoint): number {
    return Object.values(point.values).reduce((sum, value) => sum + value, 0);
  }

  /**
   * Silhouette d'une pile : sommet arrondi, base franche.
   *
   * Une pile vide renvoie un tracé vide plutôt qu'un rectangle de hauteur
   * nulle — un mois sans activité ne doit laisser aucune trace de peinture.
   */
  private topRounded(x: number, top: number, width: number, baseline: number): string {
    if (top >= baseline) {
      return '';
    }

    const r = Math.min(this.TOP_RADIUS, width / 2, baseline - top);

    return [
      `M ${x} ${baseline}`,
      `L ${x} ${top + r}`,
      `Q ${x} ${top} ${x + r} ${top}`,
      `L ${x + width - r} ${top}`,
      `Q ${x + width} ${top} ${x + width} ${top + r}`,
      `L ${x + width} ${baseline}`,
      'Z',
    ].join(' ');
  }
}
