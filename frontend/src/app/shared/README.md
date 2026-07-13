# `shared/` — Composants d'interface réutilisables

Composants, pipes et directives **standalone** réutilisés dans plusieurs
fonctionnalités. Tous sont « présentiels » : pilotés par leurs `input()` /
`output()`, sans dépendance à une fonctionnalité précise
([`../features`](../features)) ni à la logique de session.

## Catalogue des composants (F0.3 / F0.4)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `HeaderComponent` | `app-header` | En-tête global : logo, méga-nav des 5 univers, CTA connexion, menu mobile. |
| `FooterComponent` | `app-footer` | Pied de page : marque, colonnes de liens, mention légale. |
| `OrbitHeroComponent` | `app-orbit-hero` | « Signature orbitale » du hero : anneaux tournants + univers en orbite, carte centrale interactive (repris de la maquette client, charte Kaikun). |
| `ListingCardComponent` | `app-listing-card` | Carte de bien / service du catalogue (image ou dégradé de repli, badge, titre, localisation, prix, CTA). |
| `VerificationBadgeComponent` | `app-verification-badge` | Pastille de vérification (« Vérifié », « Vérifié notaire »…), tons `default` / `gold`. |
| `GalleryComponent` | `app-gallery` | Galerie photo : image principale + miniatures cliquables (alimentée par l'API Médias). |

## Catalogue & recherche (F2.1)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `CatalogComponent` | `app-catalog` | **Catalogue filtrable, triable et paginé**, réutilisé sur toutes les pages d'univers. Générique : l'univers vient d'un `input()` ; filtres/tri/page vivent dans l'URL (recherches partageables). |
| `SearchEngineComponent` | `app-search-engine` | **Moteur de recherche global** : onglets d'univers + ville/mots-clés + budget. Navigue vers `/recherche` avec des paramètres alignés sur les filtres du backend. |

**Comment ça marche, en clair :** l'utilisateur choisit un univers (Immobilier,
Nuitées, Transport, Tourisme, Mobilité), tape une ville et un budget, puis lance
la recherche. Il arrive sur une page de résultats où il peut affiner (type, prix,
tri…). Toute la recherche est dans l'adresse de la page → on peut la partager ou
la remettre en favori.

**Côté technique :**
- `app-catalog` prend `[universe]` (une des clés de [`catalog.config.ts`](components/catalog/catalog.config.ts))
  et lit ses filtres dans les query params. Le **registre `UNIVERSES`** décrit,
  pour chaque univers, comment charger les données (`CatalogService`), quels
  filtres afficher et comment mapper un élément d'API vers une `app-listing-card`.
  **Ajouter un univers = ajouter une entrée** dans ce registre, sans toucher au
  composant.
- La couche de données est [`core/api/catalog.service.ts`](../core/api/catalog.service.ts)
  (5 index publics `/properties`, `/stays`, `/vehicles`, `/experiences`,
  `/mobility-services`), enveloppe paginée typée [`Paginated<T>`](../core/api/pagination.model.ts).
- `app-search-engine` mappe pour l'instant la « ville » sur la recherche
  plein-texte `q` ; le filtrage géographique par identifiant (et les dates de
  disponibilité) arriveront avec les pages d'univers (F2.3).

### Entrées principales

- **`app-listing-card`** : `title` (requis), `location`, `price`, `priceUnit`,
  `badge`, `cta`, `image`.
- **`app-verification-badge`** : `label`, `tone` (`default` | `gold`).
- **`app-gallery`** : `images` (requis, `string[]`), `alt`.
- **`app-orbit-hero`** : aucune entrée (données internes des univers).
- **`app-catalog`** : `universe` (requis) — clé d'univers du registre.
- **`app-search-engine`** : aucune entrée (navigue lui-même vers `/recherche`).

## Directives (F1)

| Directive | Attribut | Rôle |
| --- | --- | --- |
| `PasswordRevealDirective` | `appPasswordReveal` | Ajoute un petit **bouton « œil »** à un champ mot de passe pour **afficher/masquer** la saisie. |

**À quoi ça sert, en clair :** quand on tape un mot de passe, on ne voit que des
points ; l'œil permet de **vérifier ce qu'on a saisi** (utile pour éviter les
fautes de frappe). Un clic l'affiche en clair, un autre le masque.

**Comment l'utiliser :** ajouter l'attribut `appPasswordReveal` sur un
`<input type="password">`. La directive se charge de tout (elle glisse le bouton à
droite du champ) et **ne modifie pas la valeur** : elle fonctionne donc telle
quelle avec les formulaires réactifs (`formControlName`). Utilisée sur les pages
connexion, inscription et réinitialisation de mot de passe.

## Conventions

- **Préfixe de sélecteur** : `app-` (défini dans `angular.json`).
- **Classes du design system** : préfixe `k-` (`.k-btn`, `.k-card`, `.k-wrap`,
  `.k-eyebrow`…), définies globalement dans `src/styles/` — voir les jetons
  (`_tokens.scss`) et la base (`_base.scss`).
- **Détection de changements** : `ChangeDetectionStrategy.OnPush`, état local via
  **signals** ; entrées via `input()` (API signaux).
- **Accessibilité** : libellés ARIA sur les éléments interactifs, `focus-visible`
  géré globalement, `prefers-reduced-motion` respecté pour les animations.
