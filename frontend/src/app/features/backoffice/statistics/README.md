# `statistics/` — La rubrique Statistiques du back-office (F13.1)

## 1. Expliqué simplement

C'est **le business de la plateforme en images** : ce qu'elle encaisse, d'où
vient l'activité, où les affaires se perdent en chemin, et ce qui rapporte le
plus. Six graphiques et six chiffres, sur une période qu'on choisit en haut de
l'écran.

Avant cette rubrique, le back-office savait tout compter et ne savait rien
montrer : il affichait des compteurs (« 12 biens en attente »), jamais une
évolution. Or un dirigeant ne pilote pas sur un compteur, il pilote sur une
tendance.

### Ce qu'on y voit

| Bloc | Question à laquelle il répond |
|---|---|
| Six tuiles d'en-tête | Combien, et **est-ce mieux qu'avant** ? |
| Revenus dans le temps | La courbe monte-t-elle ? Que reste-t-il à la plateforme ? |
| D'où vient l'activité | Quel univers métier produit les réservations ? |
| Tunnel commercial | À quel étage perd-on les clients ? |
| Où en sont les réservations | Combien d'annulations, combien d'honorées ? |
| Ce qui rapporte le plus | Quelle part chaque annonce prend-elle dans le total ? |

### Qui y a accès

**Pas toute l'équipe.** La rubrique est gardée par `gerer:paiements`, comme
Paiements et Reversements : elle consolide le chiffre d'affaires, et le cahier
des charges (§7) borne l'agent Kaikun à un « accès financier limité ». Un agent
sans ce droit ne voit même pas l'entrée dans le rail.

### Pourquoi une rubrique à part, et non des graphiques sur la Vue d'ensemble

Les deux écrans répondent à des questions différentes, à des rythmes
différents. La Vue d'ensemble s'ouvre **chaque matin** : ce qui attend, ce qui
alerte, ce qu'il faut traiter aujourd'hui. Statistiques se consulte **en fin de
mois** : des tendances sur des mois. Les mélanger aurait allongé l'écran
opérationnel de contenus qu'on fait défiler sans les lire, et noyé les files
d'attente qu'on vient justement y chercher.

---

## 2. Détails techniques

### Aucune bibliothèque de graphiques

Tout est **du SVG écrit à la main**, dans des composants Angular autonomes.
Ce n'est pas de l'artisanat pour le plaisir :

- une bibliothèque impose son allure, qu'il faut ensuite combattre à coups de
  surcharges pour retrouver la charte ;
- Chart.js dessine dans un `<canvas>`, qui ne rend rien au **rendu serveur** —
  actif sur ce projet ;
- le poids : la rubrique entière, six graphiques compris, pèse **~40 ko**
  (10 ko compressés). Une bibliothèque seule en pèse davantage.

Contrepartie assumée : c'est du code à nous, donc à entretenir. D'où ce README
et les commentaires nourris dans chaque composant.

### La couleur est MESURÉE, jamais choisie à l'œil

`charts/chart-tokens.ts` est la source de vérité des couleurs de données, et
son en-tête porte les mesures. La palette catégorielle a été passée à un
validateur avant d'être écrite, sur le fond réel des cartes (`#ffffff`) :

- bande de clarté OKLCH, plancher de chroma ;
- séparation sous **protanopie / deutéranopie** (sévérité 1) : pire paire
  voisine ΔE **8,1** (seuil 8) ;
- vision normale : pire paire voisine ΔE **15,1** (plancher 15) ;
- contraste sur le fond : les cinq teintes **au-dessus de 3:1**.

⚠️ **Deux teintes de la charte sont volontairement assombries** : l'or `#d3ae52`
ne tenait que 2,11:1 sur blanc et le rose `#e87ba4` 2,69:1. Une part de
graphique à 2:1 est une part qu'on devine plutôt qu'on ne la voit. Les versions
retenues (`#b0862b`, `#c4517f`) gardent la teinte, franchissent le seuil.

⚠️ **L'ORDRE de la palette fait partie de ce qui est validé** : c'est lui qui
garantit que deux univers voisins dans une pile restent distinguables. Toute
modification de la liste — y compris un simple réordonnancement — doit être
re-validée.

Trois familles de couleurs, trois usages qui ne se mélangent jamais :

| Famille | Rôle | Où |
|---|---|---|
| `SERIES_COLORS` | **identité** (quel univers métier) | colonnes empilées, palmarès |
| `FUNNEL_RAMP` | **ordre** (une teinte, du clair au foncé) | tunnel commercial |
| `STATUS_COLORS` | **état** (bon / en suspens / grave) | répartition par statut |

Une couleur d'état ne sert jamais de « série n° 6 », et l'inverse non plus : une
même teinte ne peut pas signifier « le tourisme » sur un graphique et « c'est
grave » sur le suivant.

### Les règles de dessin qui ne se négocient pas

- **Un seul axe des ordonnées, toujours.** Volume brut et commission partagent
  la même courbe parce qu'ils partagent l'unité (le franc). Ajouter le nombre de
  réservations sur un axe de droite aurait fabriqué à l'œil une corrélation
  absente des données — l'alignement de deux échelles sans rapport est
  arbitraire. Le nombre de réservations a donc son propre graphique.
- **Une couleur suit un métier, jamais son rang.** Si le tourisme passe devant
  la mobilité en volume, les deux gardent leur teinte. L'ordre vient du serveur
  (`LINE_LABELS`) et il est figé.
- **Pas de contour autour des marques** : deux tranches d'une pile sont séparées
  par un **vide de 2 px à la couleur du fond**, et les pastilles portent un
  **anneau de fond de 2 px**. Un trait autour d'une marque ajoute de l'encre qui
  ne porte aucune donnée.
- **Sommet de pile arrondi, base franche**, posée sur la ligne de zéro.
- **Colonnes plafonnées à 24 px** au lieu de remplir leur case : l'air entre
  elles est ce qui les rend dénombrables.
- **Grille en traits pleins**, jamais en pointillés — un pointillé se lit comme
  un seuil ou une projection.
- **Jamais un nombre sur chaque point.** L'axe, la légende, l'infobulle et la
  vue tableau portent le reste.

### Chaque graphique a sa vue tableau — et ce n'est pas du confort

Le bouton « Données » de chaque carte échange le dessin contre un tableau des
mêmes valeurs. Un graphique encode par la **position** et la **couleur** ; ni
l'une ni l'autre n'est disponible à qui navigue au lecteur d'écran, et la
couleur ne l'est pas complètement à qui distingue mal les teintes. Le tableau
est l'équivalent exact du dessin, et il sert aussi à l'équipe pour recopier un
chiffre juste dans un rapport, là où le graphique ne donne qu'un ordre de
grandeur.

Les deux vues **restent dans le DOM** et s'échangent par `hidden` : basculer ne
redessine rien et ne fait pas sauter la mise en page.

### ⚠️ Le piège des animations d'entrée (défaut réel, corrigé à la capture)

L'état **masqué** d'une animation d'entrée doit vivre dans le `from` de la
`@keyframes`, avec `animation-fill-mode: backwards` — **jamais sur la règle de
base de l'élément**.

Écrit dans l'autre sens (`stroke-dashoffset: 1` sur `.ch__line`, puis une
animation qui le ramène à 0), le résultat paraît identique… tant que l'animation
se joue. Elle ne se joue pas au rendu serveur, ni quand le navigateur ou une
extension coupe les animations. Le graphique principal de l'écran s'affichait
alors **vide**, ses seules pastilles de fin flottant dans le blanc — sans la
moindre erreur en console.

La règle générale : **l'absence d'animation doit donner le graphique tracé, pas
le graphique absent.** Tous les composants d'ici suivent ce motif, et tous
respectent `prefers-reduced-motion`.

### ⚠️ Deux pièges de largeur, vus en recette sur des données réelles

Les deux venaient d'un cas que les données de démonstration ne contenaient pas :
**aucun historique avant la fenêtre analysée**, donc les six tuiles affichant
« Première activité sur ce poste ».

- **`white-space: nowrap` se pose sur le fragment court, jamais sur son
  conteneur.** Posé sur le paragraphe de variation, il rendait insécable *tout*
  ce qui pouvait y passer — y compris ce message d'une phrase entière. Chaque
  tuile imposait alors sa largeur en minimum à sa colonne, et la tuile de tête,
  seule assez compressible pour céder, se retrouvait écrasée à 75 px.
- **`1fr` vaut `minmax(auto, 1fr)`**, et ce minimum `auto` est le min-content de
  la piste : une seule tuile au contenu insécable suffit à rendre les colonnes
  inégales. Dans une grille dont les pistes doivent rester égales, écrire
  **`minmax(0, 1fr)`**.

La grille des tuiles est passée à **6 → 3 → 2 colonnes** : ces trois nombres
divisent six exactement, donc jamais de case vide en fin de rangée à aucune
largeur. C'est ce qui a fait renoncer à la tuile de tête sur deux colonnes
(2 + 5 = 7 unités) : sept ne se répartit proprement qu'en une rangée, laquelle
exige plus de largeur que le back-office n'en offre son rail déduit.

### Les couleurs des tuiles ne codent rien — et c'est écrit

Demandées par le client en F13.3 « pour l'UI ». Un liseré et une pastille
d'icône par tuile, dans `TILE_ACCENTS`.

⚠️ **La couleur n'y encode aucune donnée** : chaque tuile porte son libellé en
toutes lettres. C'est ce qui la dispense des contrôles de séparation imposés aux
séries — mais **pas du contraste**, mesuré sur le fond blanc.

⚠️ **Ces teintes sont prises HORS de `SERIES_COLORS`**, délibérément. Sur cet
écran le bleu de marque veut déjà dire « Nuitées » et l'orange « Mobilité » :
une tuile bleue aurait suggéré un lien avec un univers métier. Teal, indigo et
violet n'existent nulle part ailleurs dans les graphiques.

⚠️ **Le chiffre et le libellé restent en encre.** La couleur habille le cadre
(liseré, pastille), jamais le texte : une valeur en teinte claire se lit mal, et
c'est justement la valeur qu'on vient chercher.

### Le camembert du palmarès : deux règles qui le rendent honnête

Demandé par le client en F13.2 (c'était une liste de barres). Un disque a deux
défauts connus, tous deux traités plutôt qu'ignorés :

1. **Un part-à-tout doit vraiment faire le tout.** Les cinq annonces de tête ne
   font pas le chiffre d'affaires de la période à elles seules ; une part
   **« Autres annonces »**, calculée par différence avec le volume total des
   tuiles d'en-tête, ferme le disque. Sans elle, les parts totaliseraient 100 %
   d'un ensemble qui n'est pas le tout, et le disque **exagérerait
   mécaniquement** le poids des premières.
2. **L'œil compare mal des angles.** Deux parts voisines ne se départagent pas à
   la vue. C'est pourquoi **chaque part est chiffrée dans la légende** (montant
   et pourcentage) : le disque répond à « quelle place cela prend-il ? », les
   nombres répondent à « laquelle est la plus grosse ? ». Sans cette légende
   chiffrée, la forme serait le mauvais choix.

**Cinq parts + le reste**, jamais plus : c'est aussi ce qui fixe le palmarès à
cinq entrées côté serveur. La rampe ordinale ne compte que cinq paliers — une
sixième marche ne tient pas dans la plage de clarté utilisable sans passer sous
l'un des deux seuils (mesuré). Le gris du « reste » est **hors rampe** : cet
agrégat n'a pas de rang, lui donner le palier suivant le ferait passer pour le
sixième du classement.

Détail d'implémentation qui vaut d'être connu : les arcs sont des `<circle>`
portant `pathLength="100"`, ce qui fait travailler le tracé **en centièmes de
tour** — les longueurs d'arc sont alors directement des pourcentages, sans
calcul de circonférence.

### ⚠️ La dernière graduation d'un axe est une ÉCHELLE

`niceTicks` doit produire un sommet **au-dessus** du maximum, jamais le dernier
multiple en dessous : les composants s'en servent pour convertir une valeur en
position. Vu en recette — un volume de 5 508 000 F sur un axe qui s'arrêtait à
4 M, courbe débordant de sa carte et passant derrière le bouton « Données ». Le
tracé était juste, l'axe était faux.

Corollaire : une exigence de graduations **entières** (les réservations se
comptent) s'applique au **pas**, via l'argument `integerOnly`. La filtrer après
coup — écarter les valeurs non entières de la liste rendue — jette aussi le
sommet, et rabaisse l'échelle sous les données.

Du calcul pur, donc verrouillé : `charts/chart-tokens.spec.ts` teste la
propriété « la dernière graduation couvre le maximum » sur une plage de valeurs,
avec le cas exact de la recette.

### Un seul appel, un seul filtre

`AdminService.statistics(periode)` sert **tous** les graphiques. Le filtre de
période est unique et placé au-dessus de tout ce qu'il cadre : chaque chiffre de
l'écran vient forcément de la même tranche de temps. Un filtre par carte aurait
permis d'afficher côte à côte un chiffre d'affaires sur douze mois et un nombre
de réservations sur trente jours — deux vérités qui, mises l'une à côté de
l'autre, en fabriquent une fausse.

**Rechargement sans clignotement** : pendant qu'une nouvelle période arrive, le
dessin précédent reste en place et pâlit (`.st-body--refreshing`). Une trame de
chargement ferait sauter la mise en page à chaque changement de filtre, pour un
délai qui se compte en dixièmes de seconde.

### Les fichiers

| Fichier | Rôle |
|---|---|
| `backoffice-statistics-page.*` | l'écran : filtre, tuiles, cartes, vues tableau |
| `charts/chart-tokens.ts` | **couleurs validées** + formats (francs compactés, graduations rondes) |
| `charts/chart-card.ts` | coquille commune : titre, légende, bascule « Données » |
| `charts/stat-tile.ts` | tuile d'indicateur + variation (couleur selon le **sens**, pas le signe) |
| `charts/revenue-area-chart.ts` | courbe + lavis, repère aimanté, infobulle |
| `charts/stacked-bars-chart.ts` | colonnes empilées par univers métier |
| `charts/funnel-chart.ts` | tunnel commercial + taux de passage entre étages |
| `charts/status-split-chart.ts` | barre part-à-tout des statuts |
| `charts/ranking-donut-chart.ts` | part-à-tout des annonces (rampe ordinale + « Autres ») |

### Ce que les tests protègent

`backoffice-statistics-page.spec.ts` — la **lecture** des graphiques, pas la
beauté du dessin : les cinq univers présents même à zéro (sinon les couleurs
glisseraient d'un métier à l'autre au fil des mois), une vue tableau par
graphique, le rechargement qui ne vide jamais l'écran, et les taux de passage du
tunnel.

### Pour ajouter un graphique

1. Ajouter la série côté serveur (`BusinessMetricsAggregator`) **et** au type
   `models/statistics.model.ts` — les deux sont le miroir l'un de l'autre.
2. Écrire le composant en puisant ses couleurs dans `chart-tokens.ts`. Ne jamais
   écrire une teinte en dur.
3. L'envelopper dans un `<app-chart-card>` **avec sa vue tableau**.
4. Vérifier le rendu à l'écran. Un graphique ne se juge pas au test : les trois
   défauts corrigés en F13.1 (courbe invisible, tuiles orphelines sur une
   deuxième ligne, variations coupées en trois fragments) étaient tous invisibles
   aux tests et évidents sur une capture.
