# `features/home/` — La page d'accueil de Kaikun 360

> **En une phrase :** c'est la **vitrine** du site — la première page que voit un
> visiteur. Elle raconte, de haut en bas, tout ce que Kaikun 360 sait faire et
> met en confiance, puis invite à passer à l'action.

---

## 1. Expliqué simplement

Quand quelqu'un arrive sur le site, il faut qu'en quelques secondes il comprenne
**ce qu'on propose** et **pourquoi il peut nous faire confiance**. La page
d'accueil déroule donc une histoire, section après section :

1. **L'accroche (« hero »)** — une phrase forte (« Tout votre projet sénégalais,
   vérifié et suivi ») et trois repères rassurants (régions couvertes, univers,
   biens vérifiés). À droite, une animation « orbitale » illustre les univers
   (chaque univers reste accessible via la barre de navigation et la grille
   « Nos univers » juste en dessous). Sur téléphone, l'animation passe sous le
   texte pour laisser la priorité à l'accroche.

2. **Nos univers** — une grille des **9 grands services** (immobilier, nuitées,
   tourisme, transport, construction, gestion locative, diaspora, team building,
   entreprises). Chaque tuile est cliquable : celles déjà en ligne mènent
   directement au **catalogue filtré** correspondant ; les autres descendent vers
   leur section plus bas dans la page (leur page complète arrive plus tard).

3. **Le protocole de confiance** — un bandeau sombre au message clair : « la
   confiance n'est pas une promesse, c'est un protocole ». Trois garanties
   concrètes contre les arnaques : vérification documentée (notaire, géomètre),
   tout est filmé et daté, et un numéro de suivi unique par projet. C'est le
   cœur du discours de Kaikun 360, en particulier pour la **diaspora**.

4. **La vitrine** — quelques **biens réels** tirés du catalogue (données vivantes
   récupérées depuis le serveur), avec un bouton « voir tout le catalogue ». Si le
   serveur ne répond pas, la section se replie proprement sans casser le reste de
   la page.

5. **Diaspora** — un bandeau dédié aux Sénégalais de l'étranger : les bénéfices
   concrets (référent unique, reporting photo/vidéo daté, paiements locaux) et une
   « carte de suivi » illustrée.

6. **Aller plus loin** — d'autres services (team building, gestion locative,
   livraison/conciergerie, colonies).

7. **Le simulateur de construction** — une invitation à estimer son budget « en
   30 secondes » (le calcul complet arrivera avec la page Construction).

8. **Les chiffres** — un bandeau de statistiques qui installe la crédibilité.

9. **L'appel final** — « prêt à démarrer ? » avec les boutons créer un compte /
   explorer le catalogue.

---

## 2. Détails techniques

- **Composant** : `HomePageComponent` (`home-page.ts`), autonome (standalone),
  `ChangeDetectionStrategy.OnPush`. Routé sur `''` dans le cadre principal
  (`main-layout`), voir [`../../app.routes.ts`](../../app.routes.ts).
- **Composants réutilisés** : `app-orbit-hero` et `app-listing-card` (cartes de la
  vitrine), tous décrits dans [`../../shared/README.md`](../../shared/README.md).
  (Le moteur de recherche `app-search-engine` reste disponible pour la page
  `/recherche` ; il a été retiré du hero de l'accueil.)
- **Vitrine (données réelles)** : `HomePageComponent` injecte le
  [`CatalogService`](../../core/api/catalog.service.ts) et appelle
  `properties({ per_page: 6, sort: 'recent' })` dans `ngOnInit`. Les éléments sont
  convertis en cartes via le **convertisseur `toCard`** du registre
  [`catalog.config.ts`](../../shared/components/catalog/catalog.config.ts) — donc
  un affichage strictement identique à la page de résultats. Un signal
  `featuredState` (`loading` | `ready` | `failed`) pilote l'affichage :
  squelettes animés pendant le chargement, repli propre en cas d'erreur réseau.
- **Navigation des tuiles d'univers** : chaque tuile porte `commands` / `query` /
  `fragment`. Les univers du catalogue pointent vers `/recherche?univers=…` ; les
  autres vers une **ancre** de la même page (`#simulateur`, `#diaspora`,
  `#services`, `#team-building`). Le défilement vers les ancres est activé au
  niveau du routeur (`withInMemoryScrolling` dans
  [`../../app.config.ts`](../../app.config.ts)).
- **Styles** : `home-page.scss`, une section de styles par section de page, dans
  l'ordre du template. S'appuie sur les jetons et primitives du design system
  ([`../../../styles/_tokens.scss`](../../../styles/_tokens.scss),
  [`_base.scss`](../../../styles/_base.scss)). Responsive à deux paliers
  (≤ 960 px : grilles en 2 colonnes ; ≤ 620 px : 1 colonne).
- **Contenus statiques** : les tableaux `universes`, `guarantees`, `services`,
  `diasporaPoints`, `simulatorSteps`, `stats`, `trust` sont des données de
  **présentation** (pas d'appel réseau) ; seules les cartes de la vitrine sont
  dynamiques.

### Ce qui reste à brancher (phases suivantes)

- Les CTA « estimation » et diaspora mènent pour l'instant à l'inscription ; les
  **pages dédiées** (Construction avec simulateur en direct, Diaspora, Gestion
  locative, Team building, Kaikun Pro) arrivent en **F2.5**.
- Le filtrage géographique du moteur de recherche (ville → identifiant de commune)
  arrive en **F2.3**.
