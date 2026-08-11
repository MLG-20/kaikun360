import { ChangeDetectionStrategy, Component, input, signal } from '@angular/core';

/** Une entrée de légende : un libellé et la couleur de sa marque. */
export interface LegendEntry {
  label: string;
  color: string;
  /** `line` pour les courbes, `rect` pour les aplats — la légende imite la marque. */
  shape?: 'line' | 'rect';
}

/**
 * Coquille commune à tous les graphiques de la rubrique Statistiques (F13.1).
 *
 * Porte ce qui doit être IDENTIQUE d'un graphique à l'autre — le cadre, le
 * titre, la légende, et surtout la bascule « Données ».
 *
 * **Pourquoi une vue tableau sur chaque graphique.** Un graphique encode par la
 * position et la couleur ; ni l'une ni l'autre n'est disponible à qui navigue au
 * lecteur d'écran, et la couleur ne l'est pas complètement à qui distingue mal
 * les teintes. Le tableau est l'équivalent exact du dessin : les mêmes valeurs,
 * lisibles autrement. Ce n'est pas une option de confort, c'est la condition
 * pour que l'information ne soit jamais servie par la couleur SEULE — et c'est
 * aussi ce qui permet à l'équipe de recopier un chiffre exact dans un rapport,
 * là où le graphique ne donne qu'un ordre de grandeur.
 *
 * Les deux vues restent dans le DOM et s'échangent par l'attribut `hidden` :
 * basculer ne redessine donc rien et ne fait pas sauter la mise en page.
 */
@Component({
  selector: 'app-chart-card',
  imports: [],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <article class="cc">
      <header class="cc__head">
        <div class="cc__heading">
          <h2 class="cc__title">{{ title() }}</h2>
          @if (subtitle()) {
            <p class="cc__sub">{{ subtitle() }}</p>
          }
        </div>

        <button
          type="button"
          class="cc__toggle"
          [attr.aria-pressed]="showTable()"
          (click)="showTable.set(!showTable())"
        >
          {{ showTable() ? 'Graphique' : 'Données' }}
        </button>
      </header>

      @if (legend().length) {
        <ul class="cc__legend">
          @for (entry of legend(); track entry.label) {
            <li class="cc__legend-item">
              <span
                class="cc__key"
                [class.cc__key--line]="entry.shape === 'line'"
                [style.background]="entry.color"
                aria-hidden="true"
              ></span>
              {{ entry.label }}
            </li>
          }
        </ul>
      }

      <div class="cc__body" [hidden]="showTable()">
        <ng-content />
      </div>

      <div class="cc__table" [hidden]="!showTable()">
        <ng-content select="[chartTable]" />
      </div>

      @if (note()) {
        <p class="cc__note">{{ note() }}</p>
      }
    </article>
  `,
  styles: `
    :host {
      display: block;
    }

    .cc {
      background: var(--k-card);
      border: 1px solid var(--k-line);
      border-radius: var(--k-radius-lg);
      padding: 22px 24px 20px;
      box-shadow: var(--k-shadow);
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .cc__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 4px;
    }

    .cc__title {
      font-family: var(--k-font-display);
      font-weight: 700;
      font-size: 1.02rem;
      color: var(--k-ink);
      margin: 0;
    }

    .cc__sub {
      margin: 4px 0 0;
      font-size: 0.82rem;
      color: var(--k-muted);
      max-width: 52ch;
    }

    /* Discret par défaut : la bascule est un service, pas un appel à l'action.
       Elle ne doit pas concurrencer la donnée du regard. */
    .cc__toggle {
      flex: none;
      border: 1px solid var(--k-line);
      background: transparent;
      color: var(--k-muted);
      font: inherit;
      font-size: 0.76rem;
      padding: 5px 12px;
      border-radius: var(--k-radius-pill);
      cursor: pointer;
      transition:
        color 0.15s ease,
        border-color 0.15s ease,
        background 0.15s ease;

      &:hover,
      &:focus-visible {
        color: var(--k-brand);
        border-color: var(--k-brand);
        background: var(--k-brand-050);
      }
    }

    .cc__legend {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 18px;
      list-style: none;
      margin: 14px 0 0;
      padding: 0;
    }

    .cc__legend-item {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      font-size: 0.79rem;
      /* Le texte de légende porte l'encre, jamais la couleur de la série : une
         teinte claire serait illisible en petit corps. C'est la MARQUE colorée
         à côté qui porte l'identité. */
      color: var(--k-muted);
    }

    .cc__key {
      width: 11px;
      height: 11px;
      border-radius: 3px;
      flex: none;

      /* Pour une courbe, la clé imite la marque : un trait, pas un carré. */
      &--line {
        width: 14px;
        height: 3px;
        border-radius: 2px;
      }
    }

    .cc__body {
      margin-top: 16px;
      flex: 1 1 auto;
      min-width: 0;
    }

    .cc__table {
      margin-top: 16px;
      /* Un tableau large défile DANS sa carte : la page, elle, ne bouge pas. */
      overflow-x: auto;
    }

    .cc__note {
      margin: 14px 0 0;
      font-size: 0.75rem;
      color: var(--k-muted);
      font-style: italic;
    }

    /* Feuille de données partagée par tous les graphiques (le contenu projeté
       est stylé ici pour n'écrire la mise en forme qu'une fois). */
    .cc__table ::ng-deep table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
    }

    .cc__table ::ng-deep th,
    .cc__table ::ng-deep td {
      text-align: left;
      padding: 7px 12px 7px 0;
      border-bottom: 1px solid var(--k-line);
      white-space: nowrap;
    }

    .cc__table ::ng-deep th {
      color: var(--k-muted);
      font-weight: 600;
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .cc__table ::ng-deep td {
      color: var(--k-ink);
    }

    /* Les colonnes de nombres s'alignent : ici, et SEULEMENT ici, les chiffres
       prennent une largeur fixe. Sur un grand nombre isolé (une tuile), la même
       règle donnerait un « 121 » tout mou. */
    .cc__table ::ng-deep td.num,
    .cc__table ::ng-deep th.num {
      text-align: right;
      font-variant-numeric: tabular-nums;
    }
  `,
})
export class ChartCardComponent {
  readonly title = input.required<string>();
  readonly subtitle = input<string>('');
  /** Note de bas de carte : la précaution de lecture, quand il en faut une. */
  readonly note = input<string>('');
  readonly legend = input<readonly LegendEntry[]>([]);

  /** Vue tableau affichée à la place du dessin. */
  protected readonly showTable = signal(false);
}
