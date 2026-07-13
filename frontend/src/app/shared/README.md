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

### Entrées principales

- **`app-listing-card`** : `title` (requis), `location`, `price`, `priceUnit`,
  `badge`, `cta`, `image`.
- **`app-verification-badge`** : `label`, `tone` (`default` | `gold`).
- **`app-gallery`** : `images` (requis, `string[]`), `alt`.
- **`app-orbit-hero`** : aucune entrée (données internes des univers).

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
