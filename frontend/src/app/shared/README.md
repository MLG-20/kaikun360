# `shared/` — Composants d'interface réutilisables

Composants, pipes et directives **standalone** réutilisés dans plusieurs
fonctionnalités. Tous sont « présentiels » : pilotés par leurs `input()` /
`output()`, sans dépendance à une fonctionnalité précise
([`../features`](../features)) ni à la logique de session.

## Catalogue des composants (F0.3 / F0.4)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `HeaderComponent` | `app-header` | En-tête global **réellement fixe** (sticky porté par l'hôte) : logo, **méga-menus déroulants par univers** (cartes icône + titre + description, ouverture survol/clavier, fermeture Échap/navigation/clic-dehors), lien Kaikun Pro, CTA connexion, **menu mobile en accordéons**. Aligné sur le prototype client ; chaque lien mène à une page réelle (F2.3 → F2.7). |
| `FooterComponent` | `app-footer` | Pied de page : marque, colonnes de liens, mention légale. |
| `OrbitHeroComponent` | `app-orbit-hero` | « Signature orbitale » du hero : anneaux tournants + univers en orbite, carte centrale interactive (repris de la maquette client, charte Kaikun). |
| `ListingCardComponent` | `app-listing-card` | Carte de bien / service du catalogue (image ou dégradé de repli, badge, titre, localisation, prix, CTA). |
| `VerificationBadgeComponent` | `app-verification-badge` | Pastille de vérification (« Vérifié », « Vérifié notaire »…), tons `default` / `gold`. |
| `GalleryComponent` | `app-gallery` | Galerie photo : image principale cliquable (→ plein écran), miniatures, navigation clavier, compteur. Enrichie en F2.6 (voir plus bas). |

## Catalogue & recherche (F2.1)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `CatalogComponent` | `app-catalog` | **Catalogue filtrable, triable et paginé**, réutilisé sur toutes les pages d'univers. Générique : l'univers vient d'un `input()` ; filtres/tri/page vivent dans l'URL (recherches partageables). |
| `SearchEngineComponent` | `app-search-engine` | **Moteur de recherche global** : onglets d'univers + ville/mots-clés + budget. Navigue vers `/recherche` avec des paramètres alignés sur les filtres du backend. |

## Conversion (F2.5)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `LeadFormComponent` | `app-lead-form` | **Formulaire de demande de contact réutilisable** des pages de conversion (Construction, Gestion locative, Diaspora, Team building). Dépose une demande via `POST /requests` avec le bon `service_type`. **Auth requise** : un visiteur non connecté est invité à se connecter (retour sur la page via `redirectPath`) ; un envoi réussi affiche la référence de la demande. |

**Côté technique :** entrées `serviceType` (requis), `redirectPath` (requis),
`heading`, `intro`, `ctaLabel`, `defaultMessage` (signal — le simulateur de
construction l'actualise en direct tant que l'utilisateur n'a pas édité le
champ), `showCity`, `showBudget`. S'appuie sur `AuthService.isAuthenticated`,
`RequestService.create` et les primitives de formulaire du design system
(`.k-field`/`.k-input`/`.k-form-info`/`.k-form-error`).

## Composants transverses (F2.6)

Ces trois briques sont partagées par **toutes** les fiches d'univers, pour ne
plus recopier le même code d'une page à l'autre.

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `DetailLayoutComponent` | `app-detail-layout` | **Coquille de fiche détaillée générique.** Fournit l'ossature commune à toutes les fiches (bandeau titre + fil d'Ariane + informations clés, galerie, corps en 2 colonnes) ; chaque fiche n'a plus qu'à y **glisser son contenu** dans les emplacements prévus. |
| `ReviewsComponent` | `app-reviews` | **Bloc témoignages / avis.** Affiche la note moyenne en étoiles + la liste des avis publiés. Se masque tout seul si les avis ne sont pas chargés ; affiche « Aucun avis pour le moment » si la liste est vide. |
| `WhatsAppButtonComponent` | `app-whatsapp-button` | **Bouton WhatsApp contextuel.** Ouvre une conversation vers le support avec un message **déjà prérempli** selon la page. Se masque si aucun numéro de support n'est paramétré. |

**Comment ça marche, en clair :**
- **La fiche générique** : avant, chaque type de fiche (bien, nuitée, expérience,
  véhicule) réécrivait la même structure de page. Désormais une seule « coquille »
  contient cette structure, et chaque fiche lui fournit uniquement ce qui change
  (son titre, son fil d'Ariane, ses sections, son encart de contact). Résultat :
  un rendu homogène partout et un seul endroit à maintenir.
- **Les avis** : où qu'ils soient affichés, ils ont la même présentation (note en
  étoiles dorées + commentaires). On passe simplement la réponse de l'API des avis
  au composant.
- **Le bouton WhatsApp** : quand on le pose sur une page, on lui dit « de quoi il
  s'agit » (ex. le titre du bien) ; il demande au serveur le bon lien et le bon
  numéro (jamais écrit en dur dans le code), puis ouvre WhatsApp avec un message
  tout prêt. Pratique surtout pour la diaspora et la mobilité (réservation via un
  conseiller).

**Côté technique :**
- `app-detail-layout` : entrées `title` (requis), `images` (`string[]`, défaut `[]`
  — la galerie n'apparaît que s'il y a au moins une photo), `galleryAlt`.
  Projection de contenu par attributs : `[crumbs]` (fil d'Ariane), `[meta]`
  (informations clés sous le titre), contenu par défaut (sections de la colonne
  principale), `[aside]` (encart latéral). Réutilise les classes globales
  `uni-detail-*` de [`_universe.scss`](../../styles/_universe.scss). **Les 4 fiches
  d'univers** ([`features/immo`](../features/immo), [`stay`](../features/stay),
  [`explore`](../features/explore), [`mobility`](../features/mobility)) sont bâties
  dessus.
- `app-reviews` : entrée `data` (requis, `ReviewList | null` de
  [`core/api/review.service.ts`](../core/api/review.service.ts)), `heading`.
- `app-whatsapp-button` : entrées `subject`, `reference`, `label` ; s'appuie sur
  [`core/api/whatsapp.service.ts`](../core/api/whatsapp.service.ts)
  (`GET /whatsapp/link`, B16.3). Le message et le numéro sont composés côté
  backend d'après le paramétrage back-office.
- **`app-gallery` (enrichie F2.6)** : image principale cliquable ouvrant une vue
  plein écran (« lightbox ») ; navigation par flèches ‹ › et au clavier (←/→ pour
  feuilleter, Échap pour fermer) ; compteur « i / n » ; étoile dorée sur la photo
  mise en avant ; repli « Aucune photo disponible » quand la liste est vide.
  ⚠️ Tant que les médias ne sont pas exposés par l'API, les fiches ne fournissent
  pas d'images → la galerie reste masquée (dégradation gracieuse assumée).

---

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
  `badge`, `cta`, `image`, `link` (F2.3 : cible `routerLink` de la fiche ;
  `null` = carte non cliquable). Quand `link` est fourni, un **lien étiré**
  couvre toute la carte (le titre sert de libellé accessible).
- **`app-verification-badge`** : `label`, `tone` (`default` | `gold`).
- **`app-gallery`** : `images` (requis, `string[]`), `alt`.
- **`app-detail-layout`** : `title` (requis), `images`, `galleryAlt` + emplacements de projection `[crumbs]` / `[meta]` / défaut / `[aside]` (F2.6).
- **`app-reviews`** : `data` (requis, `ReviewList | null`), `heading` (F2.6).
- **`app-whatsapp-button`** : `subject`, `reference`, `label` (F2.6).
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
