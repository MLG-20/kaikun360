/**
 * Statistiques business du back-office (F13.1) — miroir exact de
 * `BusinessMetricsAggregator::metrics()` côté Laravel
 * (`GET /admin/statistiques?periode=…`).
 *
 * ⚠️ Ces types décrivent des SÉRIES, pas des compteurs. C'est la différence
 * avec `DashboardSnapshot` : là-bas des valeurs instantanées (« combien à
 * traiter maintenant »), ici des valeurs situées dans le temps et comparées à
 * la période précédente (« comment va l'entreprise »). Seules les secondes se
 * dessinent.
 */

/** Un indicateur d'en-tête : sa valeur, et celle de la période précédente. */
export interface HeadlineMetric {
  value: number;
  previous: number;
}

/** Un point de la courbe des revenus. */
export interface RevenuePoint {
  key: string;
  label: string;
  gross_volume_xof: number;
  commission_xof: number;
}

/** Un univers métier (l'ordre de la liste EST l'ordre des couleurs). */
export interface BusinessLine {
  key: string;
  label: string;
}

/** Un point des colonnes empilées : le compte de chaque univers ce mois-là. */
export interface BookingsByLinePoint {
  key: string;
  label: string;
  values: Record<string, number>;
}

/** Un étage du tunnel commercial. */
export interface FunnelStage {
  key: string;
  label: string;
  count: number;
}

/** Une ligne du palmarès des annonces. */
export interface TopListing {
  label: string;
  line: string;
  bookings: number;
  gross_volume_xof: number;
}

/** Une part de la répartition par statut. */
export interface StatusShare {
  key: string;
  label: string;
  count: number;
}

/** Une période proposée au filtre. */
export interface StatisticsPeriod {
  key: string;
  label: string;
}

/** La réponse complète servant TOUS les graphiques de la rubrique. */
export interface BusinessStatistics {
  period: {
    key: string;
    label: string;
    /** `month` ou `day` — décide du pas de l'axe des abscisses. */
    granularity: string;
    from: string;
    to: string;
  };
  /** Le catalogue des périodes, servi par le serveur pour n'exister qu'une fois. */
  periods: StatisticsPeriod[];
  headline: {
    gross_volume_xof: HeadlineMetric;
    commission_xof: HeadlineMetric;
    bookings: HeadlineMetric;
    average_basket_xof: HeadlineMetric;
    cancellation_rate: HeadlineMetric;
    new_users: HeadlineMetric;
  };
  revenue_series: RevenuePoint[];
  bookings_by_line: {
    lines: BusinessLine[];
    points: BookingsByLinePoint[];
  };
  funnel: FunnelStage[];
  top_listings: TopListing[];
  booking_statuses: StatusShare[];
}
