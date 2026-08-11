import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { AdminService } from '../../../core/api/admin.service';
import { BusinessStatistics } from '../../../models/statistics.model';
import { ChartCardComponent, LegendEntry } from './charts/chart-card';
import { FunnelChartComponent } from './charts/funnel-chart';
import { RankingBarsChartComponent } from './charts/ranking-bars-chart';
import { RevenueAreaChartComponent } from './charts/revenue-area-chart';
import { StackedBarsChartComponent } from './charts/stacked-bars-chart';
import { StatTileComponent } from './charts/stat-tile';
import { StatusSplitChartComponent } from './charts/status-split-chart';
import { fullNumber, fullXof, seriesColor } from './charts/chart-tokens';

/**
 * Rubrique « Statistiques » du back-office (F13.1) — le business de la
 * plateforme en images.
 *
 * **Pourquoi une rubrique à part, et non des graphiques sur la Vue
 * d'ensemble.** Les deux écrans répondent à des questions différentes. La Vue
 * d'ensemble est l'écran d'ouverture de journée : ce qui attend, ce qui alerte,
 * ce qu'il faut traiter maintenant. Celui-ci est l'écran de pilotage : des
 * tendances, sur des mois, qu'on ne regarde pas tous les jours. Les mélanger
 * aurait allongé l'écran opérationnel de contenus qu'on y fait défiler sans les
 * lire, et noyé les files d'attente qu'on y vient justement chercher.
 *
 * **Un seul filtre, en haut, qui cadre TOUT.** Chaque carte tire ses chiffres
 * du même appel et donc de la même tranche de temps. Un filtre par carte aurait
 * permis d'afficher côte à côte un chiffre d'affaires sur douze mois et un
 * nombre de réservations sur trente jours — deux vérités qui, mises l'une à
 * côté de l'autre, en fabriquent une fausse.
 *
 * **Rechargement sans clignotement** : pendant qu'une nouvelle période arrive,
 * l'écran garde le dessin précédent en le pâlissant. Une trame de chargement
 * ferait sauter la mise en page à chaque changement de filtre, pour un délai
 * qui se compte en dixièmes de seconde.
 */
@Component({
  selector: 'app-backoffice-statistics-page',
  imports: [
    ChartCardComponent,
    FunnelChartComponent,
    RankingBarsChartComponent,
    RevenueAreaChartComponent,
    StackedBarsChartComponent,
    StatTileComponent,
    StatusSplitChartComponent,
  ],
  templateUrl: './backoffice-statistics-page.html',
  styleUrl: './backoffice-statistics-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeStatisticsPageComponent {
  private readonly admin = inject(AdminService);

  protected readonly stats = signal<BusinessStatistics | null>(null);
  /** Premier chargement : l'écran est encore vide. */
  protected readonly loading = signal(true);
  /** Rechargement : un dessin est déjà à l'écran, on le garde. */
  protected readonly refreshing = signal(false);
  protected readonly error = signal(false);

  /** Période demandée (la réponse fait foi : le serveur peut retomber ailleurs). */
  protected readonly period = signal<string>('12m');

  protected readonly xof = fullXof;
  protected readonly number = fullNumber;

  constructor() {
    this.load('12m');
  }

  /** Change la période affichée. Sans effet si c'est déjà celle en cours. */
  protected select(key: string): void {
    if (key !== this.period() || this.error()) {
      this.load(key);
    }
  }

  private load(key: string): void {
    this.period.set(key);
    this.error.set(false);

    if (this.stats() === null) {
      this.loading.set(true);
    } else {
      this.refreshing.set(true);
    }

    this.admin.statistics(key).subscribe({
      next: (data) => {
        this.stats.set(data);
        this.loading.set(false);
        this.refreshing.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
        this.refreshing.set(false);
      },
    });
  }

  /**
   * Légende de la courbe des revenus. Les clés sont des TRAITS, comme les
   * marques qu'elles désignent.
   */
  protected readonly revenueLegend: readonly LegendEntry[] = [
    { label: 'Volume brut', color: seriesColor(0), shape: 'line' },
    { label: 'Commission plateforme', color: seriesColor(2), shape: 'line' },
  ];

  /** Légende des univers métier, dans l'ordre figé renvoyé par le serveur. */
  protected readonly lineLegend = computed<LegendEntry[]>(
    () =>
      this.stats()?.bookings_by_line.lines.map((line, index) => ({
        label: line.label,
        color: seriesColor(index),
        shape: 'rect' as const,
      })) ?? [],
  );

  /**
   * Formule de comparaison affichée sous chaque variation, accordée à la
   * période choisie : « vs 30 jours précédents » est plus clair qu'un vague
   * « période précédente » quand on vient de changer le filtre.
   */
  protected readonly comparison = computed(() => {
    switch (this.stats()?.period.key) {
      case '30j':
        return 'vs 30 jours précédents';
      case '6m':
        return 'vs 6 mois précédents';
      default:
        return 'vs 12 mois précédents';
    }
  });

  /** Taux d'annulation mis en forme (une décimale, virgule française). */
  protected percent(value: number): string {
    return String(Math.round(value * 10) / 10).replace('.', ',') + ' %';
  }

  /** Réservations d'un univers à un point donné (vue tableau des colonnes). */
  protected lineValue(values: Record<string, number>, key: string): number {
    return values[key] ?? 0;
  }
}
