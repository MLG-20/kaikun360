import { FUNNEL_RAMP, SERIES_COLORS, compactXof, niceTicks, seriesColor } from './chart-tokens';

/**
 * Tests des jetons graphiques (F13.1).
 *
 * L'essentiel porte sur `niceTicks`, dont la dernière graduation sert d'ÉCHELLE
 * aux composants : si elle passe sous le maximum des données, le tracé sort de
 * son cadre. C'est arrivé en recette (un volume de 5 508 000 F sur un axe qui
 * s'arrêtait à 4 M, courbe débordant par-dessus le bouton « Données »), et
 * c'est du calcul pur — donc verrouillable.
 */
describe('niceTicks', () => {
  /** La propriété qui compte, quelle que soit l'entrée. */
  function couvre(max: number, ticks: number[]): boolean {
    return ticks[ticks.length - 1] >= max;
  }

  it('termine toujours au-dessus du maximum', () => {
    // Le cas exact de la recette.
    const ticks = niceTicks(5_508_000);
    expect(couvre(5_508_000, ticks)).toBe(true);
    expect(ticks[0]).toBe(0);
  });

  it('couvre le maximum sur une large plage de valeurs', () => {
    for (const max of [1, 2, 3, 7, 9, 15, 87, 342, 1_000, 4_999, 5_508_000, 12_345_678]) {
      const ticks = niceTicks(max);
      expect(couvre(max, ticks), `max=${max} → ${ticks.join(', ')}`).toBe(true);
    }
  });

  it('ne produit que des graduations entières quand on le demande', () => {
    // Sans ce mode, un maximum de 9 donne un pas de 2,5 : des graduations qui
    // n'ont pas de sens pour un nombre de réservations — et dont le filtrage
    // après coup rabaissait l'échelle sous les données.
    for (const max of [1, 3, 7, 9, 15, 23, 41]) {
      const ticks = niceTicks(max, 4, true);
      expect(ticks.every(Number.isInteger), `max=${max} → ${ticks.join(', ')}`).toBe(true);
      expect(couvre(max, ticks), `max=${max} → ${ticks.join(', ')}`).toBe(true);
    }
  });

  it('garde un axe même sans aucune donnée', () => {
    // Une carte vide doit paraître calme, pas cassée.
    expect(niceTicks(0)).toEqual([0, 1]);
  });

  it('produit des repères ronds, pas des valeurs exactes', () => {
    expect(niceTicks(87_342)).toEqual([0, 25_000, 50_000, 75_000, 100_000]);
  });
});

describe('compactXof', () => {
  it('compacte avec la virgule française', () => {
    expect(compactXof(0)).toBe('0');
    expect(compactXof(2_500)).toBe('2,5 k');
    expect(compactXof(1_200_000)).toBe('1,2 M');
    // Pas de « ,0 » inutile.
    expect(compactXof(12_000_000)).toBe('12 M');
  });
});

describe('palette', () => {
  it('ne fabrique jamais une teinte au-delà de la palette', () => {
    // Une couleur générée serait indiscernable d'une existante sous daltonisme.
    // Au-delà, on RECOMMENCE la palette — on ne l'étend pas.
    expect(seriesColor(0)).toBe(SERIES_COLORS[0]);
    expect(seriesColor(SERIES_COLORS.length)).toBe(SERIES_COLORS[0]);
    expect(SERIES_COLORS).toHaveLength(5);
  });

  it('garde une rampe d’entonnoir strictement ordonnée du clair au foncé', () => {
    // La rampe encode l'ORDRE des étages : la casser rendrait la progression
    // illisible. Contrôle grossier mais suffisant : la somme des composantes
    // RVB doit décroître à chaque palier.
    const clarte = FUNNEL_RAMP.map(
      (hex) =>
        parseInt(hex.slice(1, 3), 16) + parseInt(hex.slice(3, 5), 16) + parseInt(hex.slice(5, 7), 16),
    );

    for (let i = 1; i < clarte.length; i++) {
      expect(clarte[i]).toBeLessThan(clarte[i - 1]);
    }
  });
});
