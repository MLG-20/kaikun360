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
| `/actualites/:id` | `NewsDetailPageComponent` (`news-detail-page/`) | `GET /news/{id}` via `NewsService.get()` |

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

### Détail d'une actualité (`/actualites/:id`) — F16.3

La carte de l'accueil (`home-page`) n'affiche que titre et résumé : cette page
sert le **corps complet** de l'article, sur le même modèle que
`ContentPageComponent` (adressée par identifiant plutôt que par slug — les
actualités n'en portent pas). `[innerHTML]` pour le corps riche, même prose
(`.news-prose`, sous `:host ::ng-deep`) que `.content-prose`.

> ⚠️ **Piège d'hydratation sur la vidéo, distinct de celui du carrousel
> d'accueil.** Poser `videoEmbedUrl` (le `SafeResourceUrl` de l'iframe YouTube/
> Vimeo) dès la résolution de l'article — y compris au RENDU SERVEUR — crée
> deux instances différentes de l'objet assaini (une par le serveur, une par le
> client à l'hydratation) pour la **même** URL. Angular voit une valeur
> « changée » et réaffecte l'attribut `src` de l'iframe déjà présente dans le
> HTML serveur : le navigateur traite ça comme une navigation, annule la
> requête vidéo en cours et la relance — juste après l'hydratation. Un
> visiteur qui clique « lecture » dans cette fenêtre (courant sur mobile, où la
> vignette est visible avant la fin du démarrage du JS) voit l'iframe repartir
> de zéro : son clic semble sans effet. Correctif : `videoEmbedUrl` n'est posé
> qu'**une fois côté navigateur** (`isPlatformBrowser`) — aucune iframe vidéo
> n'existe donc dans le HTML serveur, la vignette s'affiche d'abord, l'iframe
> est créée une seule fois sans jamais entrer en conflit avec une version
> serveur.

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

### Pages de secours — `/erreur` et le 404 (F10.1.a)

Un seul composant, `error-page/`, deux routes qui le montent avec un `data.kind`
différent (`serveur` / `introuvable`).

⚠️ **Ces deux routes n'existaient pas, et le manque était silencieux.**
`errorInterceptor` renvoyait vers `/erreur` **depuis F0**, à chaque réponse `0` ou
`5xx` ; aucune route « attrape-tout » ne couvrait par ailleurs les adresses
inconnues. Résultat : `NG04002: 'erreur'` levé dans le processus Node au rendu
serveur, et — au navigateur — une navigation **purement annulée**, laissant la
personne sur sa page précédente sans un mot d'explication, persuadée que son clic
n'avait rien fait. Un lien périmé partagé sur WhatsApp tombait dans le vide.

- **La page n'appelle aucune API.** C'est celle qu'on atteint quand le serveur ne
  répond plus : un appel de plus produirait une seconde erreur, donc une nouvelle
  redirection vers elle-même.
- **`?depuis=`** — l'intercepteur y met l'adresse quittée, ce qui permet un
  bouton **« Réessayer »** honnête plutôt qu'un simple retour à l'accueil (=
  abandonner ce qu'on faisait). ⚠️ Ce paramètre vient de l'URL, donc de
  n'importe qui : **seuls les chemins internes sont suivis** (`/…`, jamais
  `//…` ni `https://…`). Une page d'erreur est exactement le lien qu'on envoie à
  quelqu'un d'inquiet — en faire un tremplin d'hameçonnage serait le pire
  endroit. 4 tests vitest verrouillent ce filtre.
- **Jamais indexées** (`seo.index: false`). ⚠️ **Limite assumée : le 404 répond
  en HTTP 200** (« soft 404 ») — le statut se règle dans `app.routes.server.ts`,
  dont la règle `**` couvre aussi toutes les pages publiques légitimes ; y poser
  `status: 404` les marquerait introuvables. Le `noindex` écarte la page des
  résultats, ce qui traite le vrai risque.
- ⚠️ **La route `**` doit rester la dernière du fichier de routes** : elle accepte
  tout, ce qui rendrait inatteignable n'importe quelle route déclarée après elle.

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
