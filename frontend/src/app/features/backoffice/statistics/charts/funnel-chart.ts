import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

import { FunnelStage } from '../../../../models/statistics.model';
import { FUNNEL_RAMP, fullNumber } from './chart-tokens';

/** Un étage prêt à afficher : sa largeur relative et son taux de passage. */
interface Step {
  key: string;
  label: string;
  count: number;
  /** Largeur de la barre en pourcentage du premier étage. */
  width: number;
  color: string;
  /** Passage depuis l'étage précédent, en pourcentage (null pour le premier). */
  conversion: number | null;
}

/**
 * Tunnel commercial (F13.1) — de la demande reçue à la prestation honorée.
 *
 * **Une seule teinte, du clair au foncé.** Les quatre étages ne sont pas quatre
 * choses différentes : ils sont ORDONNÉS. Quatre couleurs d'identité auraient
 * dit « voici quatre catégories » là où il faut lire une progression ; la
 * teinte qui fonce à mesure qu'on descend fait voir l'ordre dans la couleur
 * elle-même. La rampe employée est validée comme rampe ordinale (clarté
 * strictement décroissante, écarts visibles, extrémité claire encore lisible
 * sur le fond).
 *
 * **Le taux entre deux étages est la vraie information.** Voir « 120 demandes »
 * puis « 30 réservations » demande un calcul mental ; l'afficher évite qu'on le
 * fasse de travers. C'est là qu'on voit à quel étage l'affaire se perd.
 */
@Component({
  selector: 'app-funnel-chart',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <ol class="fn">
      @for (step of steps(); track step.key; let i = $index) {
        <li class="fn__step">
          @if (step.conversion !== null) {
            <p class="fn__link">
              <span class="fn__arrow" aria-hidden="true">↓</span>
              {{ step.conversion }} % passent cette étape
            </p>
          }

          <div class="fn__row">
            <span class="fn__label">{{ step.label }}</span>
            <span class="fn__count">{{ number(step.count) }}</span>
          </div>

          <div class="fn__track">
            <div
              class="fn__bar"
              [style.width.%]="step.width"
              [style.background]="step.color"
              [style.animation-delay.ms]="i * 90"
            ></div>
          </div>
        </li>
      }
    </ol>
  `,
  styles: `
    :host {
      display: block;
    }

    .fn {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .fn__step + .fn__step {
      margin-top: 4px;
    }

    /* Le taux de passage vit ENTRE deux barres, là où le lecteur cherche
       naturellement ce qui s'est perdu en chemin. */
    .fn__link {
      display: flex;
      align-items: center;
      gap: 6px;
      margin: 10px 0 8px;
      font-size: 0.76rem;
      color: var(--k-muted);
    }

    .fn__arrow {
      color: var(--k-line-strong);
    }

    .fn__row {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 6px;
    }

    .fn__label {
      font-size: 0.85rem;
      color: var(--k-ink);
      font-weight: 500;
    }

    /* La valeur est directement à côté de sa barre : aucune information de cet
       écran n'exige de survoler quoi que ce soit pour être lue. */
    .fn__count {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--k-ink);
    }

    .fn__track {
      height: 14px;
      border-radius: var(--k-radius-pill);
      background: #f2f5fa;
      overflow: hidden;
    }

    .fn__bar {
      height: 100%;
      border-radius: var(--k-radius-pill);
      transform-origin: left center;
      animation: fn-grow 0.65s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
    }

    @keyframes fn-grow {
      from {
        transform: scaleX(0);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .fn__bar {
        animation: none;
      }
    }
  `,
})
export class FunnelChartComponent {
  readonly stages = input.required<readonly FunnelStage[]>();

  protected readonly number = fullNumber;

  protected readonly steps = computed<Step[]>(() => {
    const stages = this.stages();
    const first = stages[0]?.count ?? 0;

    return stages.map((stage, index) => {
      const previous = index === 0 ? null : stages[index - 1].count;

      return {
        key: stage.key,
        label: stage.label,
        count: stage.count,
        // Largeur relative au PREMIER étage, pas au précédent : c'est ce qui
        // donne à la figure sa forme d'entonnoir. Un plancher de 2 % garde
        // visible un étage à presque zéro — une barre absente se lirait comme
        // une donnée manquante plutôt que comme un effondrement.
        width: first > 0 ? Math.max(2, (stage.count / first) * 100) : 0,
        color: FUNNEL_RAMP[Math.min(index, FUNNEL_RAMP.length - 1)],
        conversion:
          previous === null ? null : previous > 0 ? Math.round((stage.count / previous) * 100) : 0,
      };
    });
  });
}
