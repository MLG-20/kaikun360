# Contenu institutionnel & légal (`content/`) — F2.8

## En bref (non technique)

Les pages « autour » du service : la **foire aux questions**, les pages
**institutionnelles et légales** (À propos, mentions légales, conditions
d'utilisation, confidentialité) et la page **Contact**. Ces contenus sont
rédigés et modifiés depuis le back-office : ils viennent du serveur, on ne les
écrit jamais « en dur » dans le site. Résultat : l'équipe peut mettre à jour une
CGU ou une réponse de FAQ sans nouvelle mise en production.

## En détail (technique)

Toutes ces pages sont routées sous le layout principal (en-tête + pied).

| Route | Composant | Source de données |
|-------|-----------|-------------------|
| `/faqs` | `FaqPageComponent` (`faq/`) | `GET /faqs` via `ContentService.faqs()` |
| `/pages/:slug` | `ContentPageComponent` (`content-page/`) | `GET /pages/{slug}` via `ContentService.page()` |
| `/contact` | `ContactPageComponent` (`contact/`) | statique + `GET /whatsapp/link` |

### FAQ (`/faqs`)

Charge les entrées **publiées** et les **regroupe par catégorie** (ordre imposé
par le backend via `position`). Chaque question est un accordéon natif
`<details>` (accessible, sans JavaScript). Quatre états dégradés proprement :
`loading`, `ready`, `empty` (aucune entrée → renvoi vers Contact) et `failed`.

### Pages de contenu (`/pages/:slug`)

**Un seul composant générique** sert toutes les pages adressées par slug (À
propos, mentions légales, CGU, politique de confidentialité…). Chargement par
`switchMap` sur le slug de l'URL (annule la requête précédente). Le corps (`body`)
est un **fragment HTML** rendu via `[innerHTML]` : Angular **assainit
automatiquement** le balisage (scripts et attributs dangereux retirés), ce qui
autorise titres, listes et liens sans dépendance de rendu Markdown. Le titre de
l'onglet est mis à jour avec le titre de la page. Slug absent / page non publiée
→ 404 → état « introuvable ».

> ⚠️ Le backend enveloppe la ressource sous `data.page` (et non directement dans
> `data`) : `ContentService.page()` l'aplatit pour renvoyer la `ContentPage`.

### Contact (`/contact`)

Page de **coordonnées, sans formulaire** : le backend n'expose pas d'endpoint de
contact générique (décision produit). On présente les canaux réels — **WhatsApp**
(numéro officiel résolu par le backend via `app-whatsapp-button`, jamais codé en
dur) et **e-mail** — puis on oriente vers les parcours métier existants (déposer
un bien, devenir prestataire, FAQ). Aucun bouton mort.

## Données de démonstration

Les endpoints existent (B13.4) mais la base est vide par défaut. Le contenu de
démo (FAQ + pages) est seedé côté backend :

```bash
php artisan db:seed --class=ContentSeeder   # idempotent
```

## Styles

- Bandeaux : `.uni-hero` ([`_universe.scss`](../../../styles/_universe.scss)).
- Section d'orientation Contact : `.conv-section-title`
  ([`_conversion.scss`](../../../styles/_conversion.scss)).
- Spécifiques : accordéons FAQ (`faq-page.scss`), prose éditoriale
  (`content-page.scss`, classe `.content-prose`), cartes Contact
  (`contact-page.scss`).
