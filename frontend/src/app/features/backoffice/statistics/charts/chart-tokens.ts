/**
 * Jetons graphiques de la rubrique Statistiques (F13.1).
 *
 * SOURCE DE VÉRITÉ des couleurs de données. Tous les graphiques y puisent :
 * aucune teinte n'est écrite en dur dans un composant, faute de quoi la
 * cohérence tiendrait à la mémoire de celui qui écrit le prochain.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * ⚠️ CES VALEURS SONT MESURÉES, PAS CHOISIES À L'ŒIL.
 *
 * La palette catégorielle ci-dessous a été passée au validateur de
 * visualisation avant d'être écrite ici, sur le fond réel des cartes
 * (`#ffffff`). Résultats retenus :
 *   - bande de clarté OKLCH : les 5 teintes dans 0,43–0,77 ;
 *   - plancher de chroma : les 5 au-dessus de 0,10 (aucune ne vire au gris) ;
 *   - séparation daltonisme (protanopie/deutéranopie, sévérité 1) : pire paire
 *     voisine ΔE 8,1 — au-dessus du seuil de 8 ;
 *   - vision normale : pire paire voisine ΔE 15,1 — au-dessus du plancher 15 ;
 *   - contraste sur le fond : les 5 au-dessus de 3:1.
 *
 * Deux teintes de la charte sont volontairement ASSOMBRIES par rapport aux
 * jetons de marque : l'or `#d3ae52` ne tenait que 2,11:1 sur blanc et le rose
 * `#e87ba4` 2,69:1 — sous le seuil de 3:1. Une part de camembert à 2:1 est une
 * part qu'un lecteur devine plutôt qu'il ne la voit. Les versions retenues
 * (`#b0862b`, `#c4517f`) gardent la teinte de la charte et franchissent le
 * seuil.
 *
 * **Toute modification de cette liste doit être re-validée** — l'ORDRE en fait
 * partie : c'est lui qui garantit que deux univers voisins dans une pile
 * restent distinguables par un lecteur daltonien.
 * ─────────────────────────────────────────────────────────────────────────
 */

/**
 * Palette catégorielle — l'identité des séries (quel univers métier).
 *
 * L'ordre suit celui des univers renvoyés par le serveur
 * (`nuitees, mobilite, tourisme, team_building, sur_mesure`). Une couleur suit
 * donc un MÉTIER, jamais son rang du moment : si le tourisme passe devant la
 * mobilité en volume, les deux gardent leur teinte. Un lecteur qui a appris
 * « le vert, c'est le tourisme » ne doit pas être détrompé au premier filtre.
 */
export const SERIES_COLORS: readonly string[] = [
  '#0348fb', // bleu de marque — Nuitées
  '#eb6834', // orange — Mobilité
  '#38a774', // vert de marque — Tourisme
  '#b0862b', // or de la charte, assombri pour le contraste — Team building
  '#c4517f', // rose profond — Sur-mesure
];

/**
 * Rampe ORDINALE (une seule teinte, du clair au foncé) pour l'entonnoir.
 *
 * Un entonnoir n'a pas des étages « différents » mais des étages ORDONNÉS :
 * cinq couleurs sans rapport donneraient cinq identités là où il y a une
 * progression. La teinte qui fonce à mesure qu'on descend fait voir l'ordre
 * dans la couleur elle-même.
 *
 * Validée en rampe ordinale : clarté strictement décroissante, écart adjacent
 * ΔL ≥ 0,06, extrémité claire à 2,03:1 sur le fond (elle reste visible), une
 * seule teinte (dispersion 5°).
 */
export const FUNNEL_RAMP: readonly string[] = ['#9ab4fd', '#6d8ffb', '#3f6afa', '#0348fb'];

/**
 * Couleurs d'ÉTAT — réservées, jamais réutilisées comme « série n° 6 ».
 *
 * Elles disent bon/attention/grave, pas « ceci est le quatrième machin ». Les
 * confondre avec la palette catégorielle ferait passer une teinte d'identité
 * pour un jugement — un univers métier coloré en rouge se lirait comme un
 * problème.
 */
export const STATUS_COLORS: Readonly<Record<string, string>> = {
  en_attente: '#8792a8', // gris-bleu : ni bon ni mauvais, en suspens
  confirmee: '#0348fb',
  en_cours: '#b0862b',
  terminee: '#198754', // le succès de la charte
  annulee: '#c73b4d', // le danger de la charte
};

/** Chrome du graphique : tout ce qui n'est pas une donnée doit s'effacer. */
export const CHART_INK = {
  /** Grille : un cran au-dessus du fond, jamais en pointillés. */
  grid: '#eef1f6',
  /** Ligne de base / axe. */
  axis: '#dfe5ee',
  /** Texte des graduations et libellés. */
  muted: '#66738b',
  /** Fond des cartes — sert aussi d'écart entre deux aplats qui se touchent. */
  surface: '#ffffff',
} as const;

/**
 * Couleur de la série n° `index`, sans jamais fabriquer de teinte nouvelle.
 *
 * Au-delà de la palette on RECOMMENCE délibérément à la première couleur
 * plutôt que de générer une teinte de plus : une sixième couleur inventée
 * serait indiscernable d'une existante sous daltonisme. En pratique le serveur
 * ne renvoie que cinq univers ; ce repli est un garde-fou, pas un usage. Si un
 * sixième univers apparaît un jour, la bonne réponse est de regrouper, pas
 * d'ajouter une teinte.
 */
export function seriesColor(index: number): string {
  return SERIES_COLORS[index % SERIES_COLORS.length];
}

/**
 * Montant en francs CFA, compacté pour tenir sur un axe.
 *
 * Un axe qui affiche « 12 400 000 F » sur cinq graduations passe son temps à
 * chevaucher la courbe. « 12,4 M » se lit d'un coup d'œil, et la valeur exacte
 * reste accessible à l'infobulle et à la vue tableau — jamais perdue.
 */
export function compactXof(value: number): string {
  const abs = Math.abs(value);

  if (abs >= 1_000_000) {
    return trimZero(value / 1_000_000) + ' M';
  }
  if (abs >= 1_000) {
    return trimZero(value / 1_000) + ' k';
  }

  return String(Math.round(value));
}

/** Montant complet, séparateurs français. Pour les infobulles et les tableaux. */
export function fullXof(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(Math.round(value)) + ' F';
}

/** Nombre entier, séparateurs français. */
export function fullNumber(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value);
}

/** Une décimale, sans « ,0 » inutile (1,5 M mais 12 M). */
function trimZero(value: number): string {
  const rounded = Math.round(value * 10) / 10;

  return String(rounded).replace('.', ',');
}

/**
 * Graduations « rondes » couvrant [0, max] — la DERNIÈRE est toujours ≥ `max`.
 *
 * Un axe qui va de 0 à 87 342 par pas de 21 835,5 est exact et illisible. On
 * arrondit le pas à la puissance de dix la plus proche (1, 2, 2,5, 5 ou 10
 * fois), ce qui donne des repères que l'œil retient : 0, 25 k, 50 k…
 *
 * ⚠️ **Le sommet est le premier multiple du pas AU-DESSUS du maximum, pas le
 * dernier en dessous.** La différence n'est pas cosmétique : l'appelant se sert
 * de la dernière graduation comme échelle, si bien qu'un sommet trop bas fait
 * sortir la donnée de son cadre. Vu en recette — un volume de 5 508 000 F sur
 * un axe qui s'arrêtait à 4 M : la courbe débordait par le haut de sa carte et
 * passait derrière le bouton « Données ». Le tracé était juste, l'axe était
 * faux.
 *
 * `integerOnly` sert aux grandeurs qui se comptent (des réservations) : un pas
 * de 2,5 y produirait des graduations sans signification — et, une fois les
 * valeurs non entières écartées à l'affichage, un sommet à nouveau trop bas.
 */
export function niceTicks(max: number, count = 4, integerOnly = false): number[] {
  if (max <= 0) {
    // Un graphique entièrement vide garde quand même un axe : sans lui, la
    // carte paraîtrait cassée plutôt que calme.
    return [0, 1];
  }

  const rough = max / count;
  const magnitude = 10 ** Math.floor(Math.log10(rough));
  const normalized = rough / magnitude;
  let step = (normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 2.5 ? 2.5 : normalized <= 5 ? 5 : 10) * magnitude;

  if (integerOnly) {
    step = Math.max(1, Math.ceil(step));
  }

  // Sommet arrondi vers le HAUT : il couvre le maximum, il ne l'effleure pas.
  const top = Math.ceil(max / step) * step;

  const ticks: number[] = [];
  for (let value = 0; value <= top + step * 0.001; value += step) {
    ticks.push(value);
  }

  return ticks;
}
