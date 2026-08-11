import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';

import { RevenuePoint } from '../../../../models/statistics.model';
import { CHART_INK, compactXof, fullXof, niceTicks, seriesColor } from './chart-tokens';

/** Une série tracée : son nom, sa couleur, et ses valeurs alignées sur l'axe. */
interface PlottedSeries {
  label: string;
  color: string;
  line: string;
  area: string;
  lastX: number;
  lastY: number;
}

/**
 * Courbe des revenus dans le temps (F13.1) — le graphique qui ouvre la
 * rubrique.
 *
 * **Deux séries, UN SEUL axe.** Volume brut et commission sont tous deux des
 * francs CFA : les superposer est légitime, et la commission — qui est une
 * PART du volume — court naturellement sous lui. C'est la lecture recherchée :
 * l'écart entre les deux courbes est ce que la plateforme reverse.
 *
 * On aurait pu ajouter le nombre de réservations sur un second axe à droite.
 * Ç'aurait été une faute : l'alignement de deux échelles sans rapport est
 * arbitraire, et il fabrique à l'œil une corrélation que les données ne
 * contiennent pas. Le nombre de réservations a donc son propre graphique.
 *
 * Le dessin est un SVG à `viewBox` fixe mis à l'échelle par la largeur
 * disponible : il reste net à toute taille, s'imprime proprement, et ne coûte
 * aucune dépendance.
 */
@Component({
  selector: 'app-revenue-area-chart',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="ch">
      <svg
        class="ch__svg"
        [attr.viewBox]="'0 0 ' + W + ' ' + H"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        [attr.aria-label]="ariaLabel()"
        (pointermove)="track($event)"
        (pointerleave)="hover.set(null)"
      >
        <defs>
          <!-- Les aires sont des LAVIS, pas des aplats : un bloc saturé sous
               une courbe écrase le trait qui porte l'information. -->
          @for (serie of series(); track serie.label; let i = $index) {
            <linearGradient [attr.id]="gradientId(i)" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" [attr.stop-color]="serie.color" stop-opacity="0.20" />
              <stop offset="100%" [attr.stop-color]="serie.color" stop-opacity="0.01" />
            </linearGradient>
          }
        </defs>

        <!-- Grille : traits pleins d'un cran au-dessus du fond. Jamais de
             pointillés — ils se lisent comme un seuil ou une projection. -->
        @for (tick of ticks(); track tick) {
          <line
            [attr.x1]="PAD_L"
            [attr.x2]="W - PAD_R"
            [attr.y1]="y(tick)"
            [attr.y2]="y(tick)"
            [attr.stroke]="tick === 0 ? ink.axis : ink.grid"
            stroke-width="1"
          />
          <text
            [attr.x]="PAD_L - 10"
            [attr.y]="y(tick) + 4"
            text-anchor="end"
            class="ch__tick"
          >
            {{ compact(tick) }}
          </text>
        }

        <!-- Graduations de l'axe du temps : une sur N, pour ne jamais
             chevaucher. Trente jours affichés en trente dates seraient une
             bouillie ; on en montre six et l'infobulle donne le reste. -->
        @for (point of points(); track point.key; let i = $index) {
          @if (i % labelStride() === 0) {
            <text [attr.x]="x(i)" [attr.y]="H - 10" text-anchor="middle" class="ch__tick">
              {{ point.label }}
            </text>
          }
        }

        @for (serie of series(); track serie.label; let i = $index) {
          <path [attr.d]="serie.area" [attr.fill]="'url(#' + gradientId(i) + ')'" class="ch__area" />
          <path
            [attr.d]="serie.line"
            fill="none"
            [attr.stroke]="serie.color"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
            pathLength="1"
            class="ch__line"
          />
        }

        <!-- Repère vertical : le lecteur vise une DATE, jamais un trait de deux
             pixels. Il s'aimante au point le plus proche. -->
        @if (hover() !== null) {
          <line
            [attr.x1]="x(hover()!)"
            [attr.x2]="x(hover()!)"
            [attr.y1]="PAD_T"
            [attr.y2]="H - PAD_B"
            [attr.stroke]="ink.axis"
            stroke-width="1"
          />
          @for (serie of series(); track serie.label) {
            <circle
              [attr.cx]="x(hover()!)"
              [attr.cy]="valueY(serie, hover()!)"
              r="4.5"
              [attr.fill]="serie.color"
              [attr.stroke]="ink.surface"
              stroke-width="2"
            />
          }
        }

        <!-- Pastille de fin de chaque courbe, cerclée du fond sur 2 px pour
             rester nette là où les deux tracés se croisent. Elle marque « où
             l'on en est » — la valeur exacte, elle, est portée par les tuiles
             du haut de page, l'infobulle et la vue tableau. -->
        @for (serie of series(); track serie.label) {
          <circle
            [attr.cx]="serie.lastX"
            [attr.cy]="serie.lastY"
            r="4.5"
            [attr.fill]="serie.color"
            [attr.stroke]="ink.surface"
            stroke-width="2"
          />
        }
      </svg>

      <!-- Infobulle : la VALEUR domine, le nom de la série suit. Le lecteur
           sait déjà de quelle courbe il s'agit ; c'est le chiffre qu'il veut. -->
      @if (hover() !== null) {
        <div
          class="ch__tip"
          [style.left.%]="(x(hover()!) / W) * 100"
          [class.ch__tip--flip]="hover()! > points().length / 2"
        >
          <p class="ch__tip-date">{{ points()[hover()!].label }}</p>
          @for (serie of series(); track serie.label) {
            <p class="ch__tip-row">
              <span class="ch__tip-key" [style.background]="serie.color"></span>
              <strong>{{ full(rawValue(serie, hover()!)) }}</strong>
              <span class="ch__tip-name">{{ serie.label }}</span>
            </p>
          }
        </div>
      }
    </div>
  `,
  styles: `
    :host {
      display: block;
    }

    .ch {
      position: relative;
    }

    .ch__svg {
      display: block;
      width: 100%;
      height: auto;
      overflow: visible;
      touch-action: pan-y;
    }

    .ch__tick {
      font-family: var(--k-font-body);
      font-size: 11px;
      fill: #66738b;
    }

    /* Entrée : la courbe se trace, l'aire se remplit derrière. L'attribut
       pathLength="1" normalise la longueur du tracé, si bien que la même
       animation marche quelle que soit la forme de la courbe.

       ⚠️ L'état MASQUÉ vit dans le "from" de l'animation, jamais sur la règle
       de base — et l'animation est déclarée "backwards" pour qu'il s'applique
       quand même avant le premier pas. Mettre stroke-dashoffset:1 sur .ch__line
       elle-même paraît équivalent : ça ne l'est pas. Une courbe cachée par sa
       règle de base ne réapparaît QUE si l'animation se joue — et elle ne se
       joue pas au rendu serveur, ni quand le navigateur ou une extension coupe
       les animations. Le défaut a été vu à la capture : le graphique principal
       de l'écran s'affichait vide, avec ses seules pastilles de fin flottant
       dans le blanc. Ainsi écrit, l'absence d'animation donne un graphique
       tracé, jamais un graphique absent. */
    .ch__line {
      animation: ch-draw 0.9s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
    }

    .ch__area {
      animation: ch-fade 0.6s ease-out 0.35s backwards;
    }

    @keyframes ch-draw {
      from {
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
      }

      to {
        stroke-dasharray: 1;
        stroke-dashoffset: 0;
      }
    }

    @keyframes ch-fade {
      from {
        opacity: 0;
      }
    }

    /* Une animation n'est jamais indispensable à la lecture : qui demande moins
       de mouvement reçoit le graphique déjà tracé. */
    @media (prefers-reduced-motion: reduce) {
      .ch__line,
      .ch__area {
        animation: none;
      }
    }

    .ch__tip {
      position: absolute;
      top: 8px;
      transform: translateX(12px);
      pointer-events: none;
      background: var(--k-card);
      border: 1px solid var(--k-line);
      border-radius: var(--k-radius-sm);
      box-shadow: var(--k-shadow);
      padding: 9px 12px;
      min-width: 148px;
      z-index: 2;

      /* Passé la moitié du graphique, l'infobulle bascule à gauche du repère :
         sinon elle sortirait de la carte au dernier point, celui qu'on regarde
         le plus. */
      &--flip {
        transform: translateX(calc(-100% - 12px));
      }
    }

    .ch__tip-date {
      margin: 0 0 6px;
      font-size: 0.72rem;
      color: var(--k-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .ch__tip-row {
      display: flex;
      align-items: center;
      gap: 7px;
      margin: 0 0 3px;
      font-size: 0.82rem;
      color: var(--k-ink);

      &:last-child {
        margin-bottom: 0;
      }
    }

    /* Dans une infobulle, la clé de série est un TRAIT (la marque est une
       courbe), pas un carré : à cette densité un aplat pèse trop. */
    .ch__tip-key {
      width: 12px;
      height: 3px;
      border-radius: 2px;
      flex: none;
    }

    .ch__tip-name {
      color: var(--k-muted);
      font-size: 0.76rem;
    }
  `,
})
export class RevenueAreaChartComponent {
  readonly points = input.required<readonly RevenuePoint[]>();

  /** Géométrie du dessin (unités du `viewBox`, indépendantes des pixels). */
  protected readonly W = 760;
  // Hauteur choisie pour le RATIO, pas en pixels : le SVG est mis à l'échelle
  // par sa largeur. À 760 × 250, une carte pleine largeur donne un graphique
  // d'environ 400 px de haut — assez pour lire la pente, sans occuper à lui
  // seul tout l'écran (mesuré à la capture : 300 en donnait 480).
  protected readonly H = 250;
  protected readonly PAD_L = 56;
  protected readonly PAD_R = 18;
  protected readonly PAD_T = 16;
  protected readonly PAD_B = 30;

  protected readonly ink = CHART_INK;
  protected readonly compact = compactXof;
  protected readonly full = fullXof;

  /** Index du point survolé (null = pointeur hors du graphique). */
  protected readonly hover = signal<number | null>(null);

  /**
   * Sommet de l'axe des ordonnées : la plus grande valeur des DEUX séries,
   * arrondie au repère rond supérieur. Un axe par série donnerait deux échelles
   * — précisément ce qu'on refuse.
   */
  protected readonly ticks = computed(() => {
    const max = Math.max(0, ...this.points().map((p) => p.gross_volume_xof));

    return niceTicks(max);
  });

  private readonly maxValue = computed(() => {
    const ticks = this.ticks();

    return ticks[ticks.length - 1] || 1;
  });

  /**
   * Une graduation d'abscisse sur `stride` : on vise environ six repères, quel
   * que soit le nombre de points (12 mois → tous ; 30 jours → un sur cinq).
   */
  protected readonly labelStride = computed(() => Math.max(1, Math.ceil(this.points().length / 6)));

  /** Les deux séries prêtes à tracer. */
  protected readonly series = computed<PlottedSeries[]>(() => {
    const points = this.points();

    if (points.length === 0) {
      return [];
    }

    return [
      this.build('Volume brut', seriesColor(0), points.map((p) => p.gross_volume_xof)),
      this.build('Commission', seriesColor(2), points.map((p) => p.commission_xof)),
    ];
  });

  /** Libellé lu par les technologies d'assistance à la place du dessin. */
  protected readonly ariaLabel = computed(() => {
    const points = this.points();

    if (points.length === 0) {
      return 'Aucune donnée sur la période.';
    }

    return `Évolution du volume brut et de la commission, de ${points[0].label} à ${points[points.length - 1].label}. Le détail chiffré est disponible via le bouton Données.`;
  });

  /** Abscisse du point n° `index`. */
  protected x(index: number): number {
    const count = this.points().length;

    if (count <= 1) {
      return this.PAD_L;
    }

    return this.PAD_L + (index * (this.W - this.PAD_L - this.PAD_R)) / (count - 1);
  }

  /** Ordonnée d'une valeur en francs. */
  protected y(value: number): number {
    const usable = this.H - this.PAD_T - this.PAD_B;

    return this.H - this.PAD_B - (value / this.maxValue()) * usable;
  }

  /** Valeur brute d'une série à un index donné (pour l'infobulle). */
  protected rawValue(serie: PlottedSeries, index: number): number {
    const point = this.points()[index];

    return serie.label === 'Commission' ? point.commission_xof : point.gross_volume_xof;
  }

  /** Ordonnée d'une série à un index donné (pour la pastille du repère). */
  protected valueY(serie: PlottedSeries, index: number): number {
    return this.y(this.rawValue(serie, index));
  }

  protected gradientId(index: number): string {
    return `revenue-wash-${index}`;
  }

  /**
   * Aimante le repère sur le point le plus proche du pointeur.
   *
   * On convertit la position en pixels vers les unités du `viewBox` (le SVG est
   * mis à l'échelle par sa largeur), puis on arrondit à l'index le plus proche.
   * Le lecteur n'a donc jamais à viser juste : être *le plus près* suffit.
   */
  protected track(event: PointerEvent): void {
    const svg = event.currentTarget as SVGSVGElement;
    const box = svg.getBoundingClientRect();

    if (box.width === 0 || this.points().length === 0) {
      return;
    }

    const inViewBox = ((event.clientX - box.left) / box.width) * this.W;
    const span = this.W - this.PAD_L - this.PAD_R;
    const count = this.points().length;

    const ratio = count <= 1 ? 0 : (inViewBox - this.PAD_L) / span;
    const index = Math.round(ratio * (count - 1));

    this.hover.set(Math.min(count - 1, Math.max(0, index)));
  }

  /** Construit les tracés (ligne + aire) d'une série. */
  private build(label: string, color: string, values: readonly number[]): PlottedSeries {
    const line = values
      .map((value, i) => `${i === 0 ? 'M' : 'L'} ${this.x(i).toFixed(1)} ${this.y(value).toFixed(1)}`)
      .join(' ');

    const baseline = this.H - this.PAD_B;
    const area = `${line} L ${this.x(values.length - 1).toFixed(1)} ${baseline} L ${this.x(0).toFixed(1)} ${baseline} Z`;

    const lastIndex = values.length - 1;

    return {
      label,
      color,
      line,
      area,
      lastX: this.x(lastIndex),
      lastY: this.y(values[lastIndex]),
    };
  }
}
