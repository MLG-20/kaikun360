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
| `/contact` | `ContactPageComponent` (`contact/`) | `POST /contact` + `GET /contact-info` + `GET /whatsapp/link` |

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

Deux façons de nous joindre :

- **Formulaire public** (F2.8.1) — nom, e-mail, sujet (facultatif), message —
  envoyé via `ContactService.send()` (`POST /contact`). **Pas d'authentification**
  (un prospect doit pouvoir écrire) ; le backend limite le débit (anti-spam) et
  les messages sont **traités par l'équipe** depuis le back-office
  (`can:traiter:demandes`). Erreurs 422 réparties par champ, bandeau de succès au
  retour.
- **Siège + canaux directs** — carte **Google Maps** (embed iframe, sans clé
  API) centrée sur les coordonnées du siège, adresse et **e-mail** : tout vient
  de `GET /contact-info` (réglages back-office, `ContactService.info()`) — rien
  n'est codé en dur ; l'admin change l'adresse/les coordonnées via
  `PATCH /admin/settings`. **WhatsApp** via `app-whatsapp-button` (numéro backend).
  L'URL de l'iframe est assainie (`bypassSecurityTrustResourceUrl`, construite à
  partir de coordonnées numériques de confiance) ; la carte se masque si les
  coordonnées sont absentes.

On oriente enfin vers les parcours métier existants (déposer un bien, devenir
prestataire, FAQ). Aucun bouton mort.

## Le contenu vient de la base

Les endpoints existent (B13.4) mais la base est vide par défaut. Deux seeders,
qui ne jouent pas le même rôle :

```bash
php artisan db:seed --class=PublicPagesSeeder  # les PAGES — attendues en production
php artisan db:seed --class=ContentSeeder      # la FAQ de démo (appelle le précédent)
```

⚠️ **Les pages légales ne sont pas de la démonstration.** Le CDC §4.2 en impose
six (CGU, CGV, confidentialité, cookies, conditions de mandat, politique de
remboursement) et le §13 les classe en priorité **Haute** : elles doivent exister
en production. D'où un seeder à part, dont la garde d'idempotence est **par
slug** — une page ajoutée à la liste se pose sur une base déjà remplie, une page
déjà présente n'est **jamais** réécrite (le texte relu par le juriste et saisi au
back-office survit à une relance). `ContentSeeder`, lui, garde le tout-ou-rien
qui convient à de la démo.

⚠️ **Un lien du pied de page suppose donc un seeder rejoué.** Ajouter une entrée
dans `footer.ts` sans ajouter le slug dans `PublicPagesSeeder` produit un 404 sur
une page obligatoire.

## Styles

- Bandeaux : `.uni-hero` ([`_universe.scss`](../../../styles/_universe.scss)).
- Section d'orientation Contact : `.conv-section-title`
  ([`_conversion.scss`](../../../styles/_conversion.scss)).
- Spécifiques : accordéons FAQ (`faq-page.scss`), prose éditoriale
  (`content-page.scss`, classe `.content-prose` — ⚠️ sous `:host ::ng-deep`,
  seule façon d'atteindre du HTML injecté par `[innerHTML]` ; sans cela les
  règles ne s'appliquent à rien et le reset global affiche la page en un pavé
  compact aux liens invisibles), cartes Contact
  (`contact-page.scss`).
