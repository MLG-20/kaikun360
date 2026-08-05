# `shared/` — Composants d'interface réutilisables

Composants, pipes et directives **standalone** réutilisés dans plusieurs
fonctionnalités. Tous sont « présentiels » : pilotés par leurs `input()` /
`output()`, sans dépendance à une fonctionnalité précise
([`../features`](../features)) ni à la logique de session.

## Catalogue des composants (F0.3 / F0.4)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `HeaderComponent` | `app-header` | En-tête global **réellement fixe** (sticky porté par l'hôte) : logo, **méga-menus déroulants par univers** (cartes icône + titre + description, ouverture survol/clavier, fermeture Échap/navigation/clic-dehors), lien Kaikun Pro, CTA connexion, **menu mobile en accordéons**. Aligné sur le prototype client ; chaque lien mène à une page réelle (F2.3 → F2.7). |
| `FooterComponent` | `app-footer` | Pied de page : marque, réseaux sociaux, trois colonnes de liens et **bandeau légal** (F8.15.e — les textes de cadre du CDC §4.2, séparés des liens d'aide : on ne cherche pas les CGV comme on cherche la FAQ). ⚠️ Chaque lien `/pages/:slug` suppose la page en base, posée par `PublicPagesSeeder`. |
| `OrbitHeroComponent` | `app-orbit-hero` | « Signature orbitale » du hero : anneaux tournants + univers en orbite, carte centrale interactive (repris de la maquette client, charte Kaikun). |
| `ListingCardComponent` | `app-listing-card` | Carte de bien / service du catalogue (image ou dégradé de repli, badge, titre, localisation, prix, CTA). |
| `VerificationBadgeComponent` | `app-verification-badge` | Pastille de vérification (« Vérifié », « Vérifié notaire »…), tons `default` / `gold`. |
| `GalleryComponent` | `app-gallery` | Galerie photo : image principale cliquable (→ plein écran), miniatures, navigation clavier, compteur. Enrichie en F2.6 (voir plus bas). |

## Catalogue & recherche (F2.1)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `CatalogComponent` | `app-catalog` | **Catalogue filtrable, triable et paginé**, réutilisé sur toutes les pages d'univers. Générique : l'univers vient d'un `input()` ; filtres/tri/page vivent dans l'URL (recherches partageables). |
| `SearchEngineComponent` | `app-search-engine` | **Moteur de recherche global** : onglets d'univers + ville/mots-clés + budget. Navigue vers `/recherche` avec des paramètres alignés sur les filtres du backend. **Entrée `live`** (F8.11) : posée sur la page de résultats, elle fait qu'un onglet d'univers **applique le changement aussitôt** au lieu d'attendre « Rechercher ». Les trois champs sont des `linkedSignal` **branchés sur l'URL**. |

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
| `ScrollTopComponent` | `app-scroll-top` | **Bouton « retour en haut » global.** Monté une fois dans la racine (`app.html`) → **toutes les pages en héritent** (position fixe). N'apparaît qu'une fois la page défilée (> 400 px) ; un **anneau de progression de lecture** entoure le chevron. Animations vivantes : **chevron qui flotte** en continu + **halo qui pulse** (invite au clic) ; survol premium (dégradé de marque, anneau doré, chevron qui bondit). SSR-safe (`afterNextRender`), `prefers-reduced-motion` respecté (animations figées, remontée instantanée). |
| `BackLinkComponent` | `app-back-link` | **Bouton « ← Retour » générique.** Revient à la **page précédente** (via l'historique de navigation) plutôt qu'à une cible fixe : posé sur un écran atteint depuis plusieurs endroits (le menu **ou** une notification), il ramène là d'où l'on vient réellement. Repli SSR-safe sur `fallback` (défaut `/mon-espace`) quand l'historique est vide (accès direct, rechargement). Entrées : `label` (défaut « Retour »), `fallback`. |

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
- `app-search-engine` mappe la « ville » sur la recherche plein-texte `q` ; le
  filtrage géographique par identifiant (et les dates de disponibilité)
  arriveront avec les pages d'univers (F2.3). ⚠️ **Exception mobilité** : un
  départ programmé n'a **aucun champ libre ni aucun filtre de prix** côté
  serveur — la ville est donc envoyée en `departure`, et le budget est **omis**
  plutôt que transmis à un filtre inexistant, qui aurait laissé croire à un
  tri qui n'a pas lieu.
- ⚠️ **Deux défauts corrigés en F8.11**, tous deux vécus comme « les filtres ne
  marchent pas » : (1) les onglets d'univers ne reflétaient pas l'URL et ne
  changeaient rien au clic, si bien que l'onglet *Nuitées* pouvait s'allumer
  au-dessus d'un catalogue titrant *Immobilier* ; (2) les champs texte du
  catalogue n'appliquaient leur filtre qu'à la **perte de focus** (`(change)`) —
  taper un mot puis fixer l'écran ne filtrait rien. La touche **Entrée** vaut
  désormais validation.

### Entrées principales

- **`app-listing-card`** : `title` (requis), `location`, `price`, `priceUnit`,
  `badge`, `cta`, `image`, `link` (F2.3 : cible `routerLink` de la fiche ;
  `null` = carte non cliquable). Quand `link` est fourni, un **lien étiré**
  couvre toute la carte (le titre sert de libellé accessible). **Favoris (tous
  univers)** : si `favoritable` (`{ type, id }`) est fourni, un **cœur** s'affiche
  en surimpression (au-dessus du lien étiré) ; la carte reste présentielle et émet
  `favoriteToggle` — la page hôte (via [`FavoriteStore`](../core/state/favorite-store.ts))
  appelle le service, gère `favorited`/`favoriteBusy` et redirige l'anonyme vers
  la connexion. `app-catalog` et l'accueil câblent ce cœur automatiquement.
- **`app-verification-badge`** : `label`, `tone` (`default` | `gold`).
- **`app-gallery`** : `images` (requis, `string[]`), `alt`.
- **`app-detail-layout`** : `title` (requis), `images`, `galleryAlt` + emplacements de projection `[crumbs]` / `[meta]` / défaut / `[aside]` (F2.6).
- **`app-reviews`** : `data` (requis, `ReviewList | null`), `heading` (F2.6).
- **`app-whatsapp-button`** : `subject`, `reference`, `label` (F2.6).
- **`app-orbit-hero`** : aucune entrée (données internes des univers).
- **`app-catalog`** : `universe` (requis) — clé d'univers du registre.
- **`app-search-engine`** : aucune entrée (navigue lui-même vers `/recherche`).

## Saisie de contenu éditorial (F8.3)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `RichTextEditorComponent` | `app-rich-text-editor` | **Éditeur de texte enrichi** du back-office : titres, gras/italique, listes, liens, citation — **par des boutons, aucune balise à taper**. Utilisé pour le corps des pages de contenu (`Paramètres → Contenu → Pages`). |

**À quoi ça sert, en clair :** le corps d'une page publique (mentions légales,
CGU, à propos) est du HTML. Avant, le back-office n'offrait qu'une grande zone
de texte : pour obtenir un titre, l'agent devait écrire lui-même `<h2>…</h2>`.
En pratique, soit il appelait un développeur pour changer une virgule, soit il
tapait du texte brut qui arrivait en un seul pavé sur le site. Désormais il
écrit, il sélectionne, il clique — comme dans un traitement de texte.

**Côté technique :**
- **Aucune dépendance** : `contenteditable` + `document.execCommand`. Déprécié
  mais universel, et une bibliothèque d'édition apporterait son propre format de
  document plus ~150 Ko au premier chargement du site, pour six boutons.
- Implémente **`ControlValueAccessor`** : `[(ngModel)]="…"` fonctionne tel quel.
  Entrées : `placeholder`, `ariaLabel`, `rows`.
- **Le format stocké n'a pas changé** : c'est le même HTML qu'avant, donc les
  pages déjà en base s'ouvrent sans conversion et le rendu public est intact.
- [`rich-text.sanitizer.ts`](components/rich-text-editor/rich-text.sanitizer.ts) —
  **liste blanche stricte** (`p, br, strong, em, u, h2, h3, h4, ul, ol, li, a,
  blockquote`), appliquée à l'ouverture, **au collé** et à la sortie du champ.
  Ce qui n'y figure pas est **déballé** : on garde le texte, on jette la balise —
  la rédaction n'est jamais perdue. Un `<h1>` devient un `<h2>` (le titre de la
  page en est déjà un), `<b>`/`<i>` deviennent `<strong>`/`<em>`, un lien en
  `javascript:` perd son adresse mais garde son libellé, un lien sortant reçoit
  `target="_blank" rel="noopener noreferrer"`, et le texte laissé nu est enveloppé
  dans un `<p>`. La fonction est **idempotente** (couverte par 15 tests).
- ⚠️ **La réponse d'une FAQ n'utilise pas cet éditeur** : la page publique la rend
  en `{{ answer }}` (texte brut), pas en HTML — les balises s'y afficheraient en
  clair. Elle garde son `<textarea>`.

## Écrire au support (F8.12)

| Composant | Sélecteur | Rôle |
| --- | --- | --- |
| `ContactSupportComponent` | `app-contact-support` | **Ouvre un fil de discussion avec le support Kaikun**, avec le dossier concerné joint. Entrées : `contextType`, `contextId`, `subject`, `label`, `hint`. |

**À quoi ça sert, en clair :** la messagerie existait depuis F3.7 — lire un fil,
répondre, compter les non-lus, notifier — mais **rien ne savait en OUVRIR un**.
Aucun écran n'appelait `startConversation()` ; tous les fils visibles venaient du
seeder, et l'état vide de « Mes messages » promettait un bouton (« une
conversation s'ouvre lorsque vous contactez le support depuis une annonce ou une
demande ») qui n'existait nulle part. Ce composant est ce bouton.

**Côté technique :**
- Appelle `POST /messages/support` (`MessageService.startWithSupport`) : **aucun
  destinataire n'est envoyé**. Le serveur assigne un agent de permanence — le
  client n'écrit jamais directement au propriétaire ou au prestataire, c'est
  l'agent qui décidera d'ajouter le tiers au fil. Contourner cette règle ferait
  sortir l'échange de la plateforme, où plus personne ne voit rien.
- `contextType` est un **slug** de la liste blanche partagée avec le serveur
  (`demande`, `devis`, `reservation`, `bien`, `nuitee`, `vehicule`, `circuit`,
  `trajet`) : les deux listes doivent rester alignées, tout autre slug est refusé
  en 422. Un dossier personnel qui n'appartient pas à l'auteur est **ignoré**
  (le message part quand même).
- Après envoi, **redirection vers le fil** créé ou repris — le client voit son
  message posté et le nom de l'agent qui lui a été assigné, plutôt qu'un
  « message envoyé » sans suite. Le préfixe d'espace vient de `SPACE_CONFIG`
  (injecté en `optional`) : les quatre espaces connectés ont chacun leur
  `/messages`, un chemin en dur enverrait trois profils sur quatre dans le mur.
- Monté sur : « Mes messages » (état vide + barre d'actions), la fiche d'une
  demande, la fiche d'une réservation.

## Directives (F1)

| Directive | Attribut | Rôle |
| --- | --- | --- |
| `PasswordRevealDirective` | `appPasswordReveal` | Ajoute un petit **bouton « œil »** à un champ mot de passe pour **afficher/masquer** la saisie. |
| `RevealDirective` | `appReveal` / `appReveal="group"` | **Révèle un élément (ou les enfants d'une grille, en cascade) au défilement** — fondu + léger glissé. `IntersectionObserver`, posé dans `afterNextRender` (SSR : contenu visible sans JS), `prefers-reduced-motion` respecté. Styles globaux dans `styles/_reveal.scss`. |
| `CountUpDirective` | `[appCountUp]="valeur"` | **Anime un nombre de 0 à sa valeur finale** quand il entre à l'écran (bande de statistiques). Conserve l'habillage (« 100 % »). L'interpolation `{{ valeur }}` sert de repli SSR / sans-JS. |

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
