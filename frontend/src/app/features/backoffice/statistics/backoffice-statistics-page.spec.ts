import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { AdminService } from '../../../core/api/admin.service';
import { BusinessStatistics } from '../../../models/statistics.model';
import { BackofficeStatisticsPageComponent } from './backoffice-statistics-page';

/**
 * Tests de la rubrique Statistiques (F13.1).
 *
 * Ce que ces tests protègent, c'est la LECTURE des graphiques, pas la beauté du
 * dessin :
 *   - un univers métier à zéro doit rester une colonne de la grille, sinon les
 *     couleurs glisseraient d'un métier à l'autre au fil des mois ;
 *   - chaque graphique doit avoir sa vue tableau, seule façon d'atteindre les
 *     valeurs sans passer par la couleur et la position ;
 *   - changer de période ne doit jamais vider l'écran (on garde le dessin
 *     précédent le temps du rechargement).
 */
describe('BackofficeStatisticsPageComponent', () => {
  /** Le catalogue de périodes servi par le serveur depuis F13.2. */
  const periodes = [
    { key: '7j', label: '7 derniers jours' },
    { key: '15j', label: '15 derniers jours' },
    { key: '30j', label: '30 derniers jours' },
    { key: '6m', label: '6 derniers mois' },
    { key: '12m', label: '12 derniers mois' },
  ];

  /** Douze mois dont plusieurs vides — le cas qui casse les courbes naïves. */
  function statistics(periodKey = '12m'): BusinessStatistics {
    const months = ['sept. 25', 'oct. 25', 'nov. 25', 'déc. 25', 'janv. 26', 'févr. 26', 'mars 26', 'avr. 26', 'mai 26', 'juin 26', 'juil. 26', 'août 26'];
    const gross = [0, 0, 1_250_000, 2_100_000, 1_800_000, 3_400_000, 4_150_000, 3_900_000, 5_600_000, 6_200_000, 7_450_000, 8_900_000];
    const counts: [number, number, number, number, number][] = [
      [0, 0, 0, 0, 0],
      [0, 0, 0, 0, 0],
      [3, 1, 0, 0, 0],
      [5, 2, 1, 0, 0],
      [4, 2, 1, 0, 1],
      [7, 3, 2, 1, 0],
      [9, 4, 2, 0, 1],
      [8, 5, 3, 1, 1],
      [12, 6, 4, 1, 2],
      [14, 7, 3, 2, 1],
      [16, 9, 5, 2, 2],
      [19, 11, 6, 3, 2],
    ];
    const lines = [
      { key: 'nuitees', label: 'Nuitées' },
      { key: 'mobilite', label: 'Mobilité' },
      { key: 'tourisme', label: 'Tourisme' },
      { key: 'team_building', label: 'Team building' },
      { key: 'sur_mesure', label: 'Sur-mesure' },
    ];

    return {
      // ⚠️ Le libellé SUIT la clé, comme le fait le vrai serveur. Le laisser
      // figé à « 12 derniers mois » ferait passer au vert un test qui ne
      // vérifierait que ce double.
      period: {
        key: periodKey,
        label: periodes.find((p) => p.key === periodKey)?.label ?? '12 derniers mois',
        granularity: periodKey.endsWith('j') ? 'day' : 'month',
        from: '2025-09-01',
        to: '2026-08-11',
      },
      periods: periodes,
      headline: {
        gross_volume_xof: { value: 44_750_000, previous: 31_200_000 },
        commission_xof: { value: 5_370_000, previous: 3_744_000 },
        bookings: { value: 187, previous: 142 },
        average_basket_xof: { value: 253_000, previous: 241_000 },
        cancellation_rate: { value: 8.6, previous: 11.2 },
        new_users: { value: 312, previous: 268 },
      },
      revenue_series: months.map((label, i) => ({
        key: `2026-${i}`,
        label,
        gross_volume_xof: gross[i],
        commission_xof: Math.round(gross[i] * 0.12),
      })),
      bookings_by_line: {
        lines,
        points: months.map((label, i) => ({
          key: `2026-${i}`,
          label,
          values: Object.fromEntries(lines.map((line, j) => [line.key, counts[i][j]])),
        })),
      },
      funnel: [
        { key: 'requests', label: 'Demandes reçues', count: 428 },
        { key: 'quotes', label: 'Devis émis', count: 264 },
        { key: 'bookings', label: 'Réservations', count: 187 },
        { key: 'completed', label: 'Prestations honorées', count: 141 },
      ],
      top_listings: [
        { label: 'Nuitée — Villa des Almadies', line: 'Nuitées', bookings: 24, gross_volume_xof: 8_400_000 },
        { label: 'Nuitée — Résidence Saly Portudal', line: 'Nuitées', bookings: 19, gross_volume_xof: 6_150_000 },
        { label: 'Toyota Land Cruiser', line: 'Mobilité', bookings: 31, gross_volume_xof: 4_960_000 },
        { label: 'Circuit Sine-Saloum 3 jours', line: 'Tourisme', bookings: 12, gross_volume_xof: 3_600_000 },
        { label: 'Dakar → Saint-Louis', line: 'Mobilité', bookings: 48, gross_volume_xof: 2_400_000 },
      ],
      booking_statuses: [
        { key: 'en_attente', label: 'En attente', count: 14 },
        { key: 'confirmee', label: 'Confirmées', count: 26 },
        { key: 'en_cours', label: 'En cours', count: 6 },
        { key: 'terminee', label: 'Terminées', count: 141 },
        { key: 'annulee', label: 'Annulées', count: 18 },
      ],
    };
  }

  const calls: string[] = [];

  beforeEach(async () => {
    calls.length = 0;

    await TestBed.configureTestingModule({
      imports: [BackofficeStatisticsPageComponent],
      providers: [
        {
          provide: AdminService,
          useValue: {
            statistics: (periode?: string) => {
              calls.push(periode ?? '');

              return of(statistics(periode));
            },
          },
        },
      ],
    }).compileComponents();
  });

  it('affiche les six indicateurs, les cinq cartes et le filtre de période', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    expect(host.querySelectorAll('app-stat-tile').length).toBe(6);
    expect(host.querySelectorAll('app-chart-card').length).toBe(5);
    expect(host.querySelectorAll('.st-filter__btn').length).toBe(5);

    // La tuile de tête est unique : deux « chiffres les plus importants » ne
    // forment plus une hiérarchie.
    expect(host.querySelectorAll('.st--hero').length).toBe(1);
  });

  it('donne à chaque graphique sa vue tableau', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    // Cinq cartes, cinq tableaux : aucune valeur de cet écran n'est
    // accessible par la seule couleur ou la seule position.
    expect(host.querySelectorAll('[chartTable] table').length).toBe(5);
    expect(host.querySelectorAll('app-chart-card .cc__toggle').length).toBe(5);
  });

  it('garde une colonne par univers métier, même à zéro', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    // Le tableau des univers a 1 colonne de période + 5 univers, toujours, y
    // compris pour les mois où trois d'entre eux sont à zéro. C'est ce qui
    // permet à une couleur de rester attachée à un métier.
    const headers = host.querySelectorAll('[chartTable] table')[1].querySelectorAll('thead th');
    expect(headers.length).toBe(6);
    expect(headers[5].textContent?.trim()).toBe('Sur-mesure');

    // La légende nomme les cinq univers : l'identité ne repose jamais sur la
    // seule teinte.
    expect(host.querySelectorAll('app-chart-card')[1].querySelectorAll('.cc__legend-item').length).toBe(5);
  });

  it('recharge la période demandée sans vider l’écran', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    const boutons = host.querySelectorAll<HTMLButtonElement>('.st-filter__btn');
    // Le premier bouton est « 7 derniers jours » depuis F13.2.
    boutons[0].click();
    await fixture.whenStable();

    expect(calls).toEqual(['12m', '7j']);
    // Le corps de page est toujours là — jamais remplacé par un écran d'attente.
    expect(host.querySelector('.st-body')).toBeTruthy();
    expect(host.querySelectorAll('app-chart-card').length).toBe(5);
  });

  it('ferme le camembert avec une part « Autres annonces »', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    // Les cinq annonces de tête ne font pas tout le chiffre d'affaires : sans
    // la part du reste, les parts totaliseraient 100 % d'un ensemble qui n'est
    // pas le tout, et le disque exagérerait le poids des premières.
    const parts = host.querySelectorAll('app-ranking-donut-chart .dn__item');
    expect(parts.length).toBe(6);
    expect(parts[5].textContent).toContain('Autres annonces');

    // Et les parts couvrent bien le tour complet.
    const pourcentages = Array.from(parts).map((part) =>
      Number((part.textContent?.match(/([\d,]+) %/)?.[1] ?? '0').replace(',', '.')),
    );
    expect(pourcentages.reduce((a, b) => a + b, 0)).toBeCloseTo(100, 0);
  });

  it('accorde la formule de comparaison à la période choisie', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    host.querySelectorAll<HTMLButtonElement>('.st-filter__btn')[0].click();
    await fixture.whenStable();

    // Dérivée du libellé servi par le serveur, donc juste pour toute période
    // ajoutée côté serveur sans retouche ici.
    expect(host.querySelector('app-stat-tile .st__vs')?.textContent?.trim()).toBe(
      'vs 7 jours précédents',
    );
  });

  it('affiche un taux de passage entre chaque étage du tunnel', async () => {
    const fixture = TestBed.createComponent(BackofficeStatisticsPageComponent);
    await fixture.whenStable();
    const host = fixture.nativeElement as HTMLElement;

    // Quatre étages, trois passages : 264/428, 187/264, 141/187.
    const liens = host.querySelectorAll('app-funnel-chart .fn__link');
    expect(liens.length).toBe(3);
    expect(liens[0].textContent).toContain('62 %');
  });
});
