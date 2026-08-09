# Module Assistant — L'assistant transverse de la plateforme

Assistant conversationnel couvrant **toute la plateforme et les 8 rôles**. Il oriente
dans le catalogue réel, répond depuis la FAQ éditée au back-office, et passe la main
à un conseiller quand il sèche.

> **Hors cahier des charges.** Le CDC ne mentionne aucun assistant : c'est un ajout.
> D'où l'interrupteur général (`ASSISTANT_ENABLED`) et le cerveau interchangeable —
> le module doit pouvoir être coupé ou dégradé sans toucher au reste.

---

## Principe directeur : l'assistant n'a aucun privilège propre

Un assistant transverse est une **seconde porte d'entrée sur toute l'API**. Le risque
principal n'est pas qu'il réponde mal, c'est qu'il devienne un contournement des
autorisations. Trois règles y répondent :

1. **Les outils n'accèdent jamais à la base « à la main ».** Ils passent par les scopes
   publics (`Property::published()`, `Stay::bookable()`, `Vehicle::published()`,
   `TourismExperience::published()`, `Faq::published()`) et — dès F10.2 — par les policies
   existantes, exécutées **au nom de l'utilisateur** porté par `AssistantContext`.
2. **L'assistant propose, il n'écrit pas.** Il renvoie des `AssistantAction` que le panneau
   affiche en boutons ; c'est le clic de l'utilisateur qui appelle l'endpoint métier
   d'origine (`POST /api/v1/messages/support` pour une escalade), avec ses Form Requests
   et ses policies. Aucune logique métier n'est dupliquée.
3. **La sortie des outils est fermée.** `ToolResult` ne transporte que des champs destinés
   à l'affichage — jamais un identifiant technique, un jeton ou une adresse e-mail.

Conséquence : une injection de prompt réussie ne débloque rien. Le cerveau peut demander
ce qu'il veut, le scope et la policy répondent non.

---

## Architecture — un contrat, un cerveau interchangeable (phase F10.0)

```
POST /api/v1/assistant/messages     ← contrat figé ; Angular ne connaît que ça
        │
   AssistantBrain (interface)
        ├── RuleBasedBrain   ← F10.0 · déterministe · 0 clé, 0 coût, 0 réseau
        └── ClaudeBrain      ← F10.4 · modèle de langage, MÊMES outils
        │
   ToolRegistry ── trousse assemblée selon le rôle de l'appelant
```

Les deux cerveaux reçoivent la même trousse et produisent la même `AssistantReply` :
basculer se fait par `ASSISTANT_DRIVER`, sans toucher au frontend ni aux tests. Le
déterministe reste aussi le **repli** si les clés deviennent indisponibles.

### Fichiers

| Fichier | Rôle |
|---|---|
| `Contracts/AssistantBrain.php` | le cerveau — unique point de variation |
| `Contracts/AssistantTool.php` | contrat d'outil (`name`, `description`, `isAvailableFor`, `run`) |
| `Support/AssistantContext.php` | qui parle ; résout le rôle **le plus privilégié** détenu |
| `Support/AssistantAction.php` | geste proposé (`link` / `support` / `contact`) |
| `Support/ToolResult.php` | sortie fermée d'un outil |
| `Support/AssistantReply.php` | contrat consommé par Angular |
| `Tools/ToolRegistry.php` | assemble et filtre la trousse par rôle |
| `Brains/RuleBasedBrain.php` | intentions par mots-clés → outil |

---

## Endpoint

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/assistant/messages` | **public**, authentification facultative |

Corps : `message` (obligatoire) et `history` (facultatif, tableau de `{role, text}`).
Réponse : `{ data: { reply: { text, items, actions, tool } } }`.

L'endpoint est **sans état** : l'historique voyage avec la requête, et **aucune
conversation n'est stockée** — y compris après F10.2, où l'arbitrage a été de s'en tenir
là (voir « Journalisation » plus bas). Rien à protéger, rien à purger au RGPD.

L'authentification est facultative : le contrôleur résout l'appelant via
`$request->user('sanctum')` — le garde par défaut ne résoudrait rien hors du middleware
`auth:sanctum`.

---

## Garde-fous

| Menace | Parade | Où |
|---|---|---|
| Injection de prompt | les outils passent par scopes/policies ; le cerveau n'a aucun accès direct | `AssistantTool` |
| Fuite entre locataires | même point de contrôle | idem |
| Déni de portefeuille | `throttle:assistant` (12/min par compte ou IP) **en plus** de `throttle:api` | `routes/api.php`, `AppServiceProvider` |
| Saturation par l'entrée | `message` ≤ 500 car., `history` ≤ 10 tours, rôles d'historique en liste blanche | `AssistantMessageRequest` |
| Exfiltration par la réponse | `ToolResult` ne transporte que des champs d'affichage | `ToolResult` |
| Incident / budget non validé | `ASSISTANT_ENABLED=false` → 503, sans déploiement | `config/assistant.php` |

---

## Outils (F10.0 — publics)

| Outil | Ce qu'il fait | Écriture |
|---|---|---|
| `rechercher_catalogue` | 4 univers (immobilier, nuitées, tourisme, transport) ; 3 résultats + lien profond | non |
| `consulter_faq` | FAQ publiée ; recherche par mots significatifs (mots outils écartés) | non |
| `contacter_support` | propose un fil (connecté) ou le formulaire de contact (visiteur) | **non** — propose seulement |

L'action `support` transporte `subject` **et `body`** : `POST /api/v1/messages/support`
exige `body` (`StartSupportConversationRequest`), et le corps reprend le message d'origine
— l'agent doit lire ce que la personne a réellement écrit, pas un sujet tronqué.

⚠️ Le lien « Voir mes messages » qui accompagne l'escalade est construit par
**`SpaceLink`** (F8.8), pas écrit en dur (correctif F10.1). Le site a **quatre espaces
connectés** et `/mon-espace` est gardé par le rôle `client` : un propriétaire ou un
prestataire cliquant sur ce bouton était refoulé. Toute adresse d'espace produite par ce
module doit passer par `SpaceLink`.

`rechercher_catalogue` renvoie **trois résultats maximum** puis un lien profond vers la vraie
page : l'assistant oriente, le catalogue vend. Le transport n'est pas filtré par ville — un
véhicule n'est pas rattaché à une commune, c'est le trajet qui l'est ; filtrer dessus
donnerait zéro résultat et laisserait croire au catalogue vide.

`RuleBasedBrain` reconnaît les lieux en confrontant le message à la liste **réelle** des
régions, des communes, des **zones touristiques** des biens et des **destinations** des
circuits publiés (mise en cache 1 h), plutôt qu'à une liste écrite en dur qui vieillirait
mal. La salutation wolof du prototype est conservée (identité de marque), mais la
conversation se poursuit en français — seule langue traitée correctement.

⚠️ **Les deux dernières sources ont été ajoutées en F10.1, après un essai sur le serveur
réel.** Le vocabulaire ne portait que les communes et les régions, alors que la recherche
sait filtrer sur `tourist_zone` et `destination` : « Saly », « Casamance » ou « Gorée »
n'étaient donc jamais transmis à l'outil, qui répondait par les trois derniers circuits
publiés — n'importe où dans le pays, en ayant l'air d'avoir compris. **Règle à retenir :
le vocabulaire de compréhension doit couvrir tout ce que la recherche sait exploiter.**
Ces sources sont bornées aux annonces **publiées**, sans quoi l'assistant reconnaîtrait
une destination que le catalogue public ignore.

---

## Outils (F10.2 — espaces connectés)

| Outil | Rôles | Ce qu'il montre | Écriture |
|---|---|---|---|
| `mes_reservations` | client, entreprise | 3 dernières réservations : référence, période, statut, montant | non |
| `mes_demandes` | client | demandes déposées : type, ville, statut | non |
| `mes_biens` | propriétaire | biens déposés, **tous statuts confondus** | non |
| `mes_missions` | prestataire | missions affectées : intitulé, statut, date, montant | non |
| `mes_projets_diaspora` | client | projets au pays + nombre de comptes rendus | non |

⚠️ **`mes_demandes` n'est PAS ouvert à l'entreprise, malgré les apparences.** Les deux
espaces ont un écran « Mes demandes », mais ce ne sont pas les mêmes demandes :
`/espace-entreprise/demandes` liste des **demandes de team building**, pas des
`ServiceRequest`. Le lien d'une fiche aurait pointé vers l'écran d'un autre registre —
au mieux une fiche introuvable, au pire celle qui porte le même numéro. Deux écrans
homonymes ne sont pas un écran commun.

Tous héritent de **`PersonalRecordsTool`**, qui rassemble les règles que ces
outils ne doivent pas pouvoir oublier : session obligatoire, rôle attendu,
lecture seule, sortie fermée, et adresses d'écran passées par `SpaceLink`.

⚠️ **Le cloisonnement n'est pas redémontré, il est RECOPIÉ du contrôleur HTTP**
qui sert le même écran (`where('user_id', …)`, `where('owner_id', …)`,
`whereHas('provider', …)`). Réécrire une condition d'appartenance « à peu près
pareil » est la façon la plus banale de créer une fuite. Cas le plus piégeux :
`provider_missions.provider_id` pointe sur **`providers`**, pas sur `users` —
seule relation du projet dans ce cas ; un `where('provider_id', $user->id)`
écrit de bonne foi compilerait et montrerait les missions d'un autre.

⚠️ **`mes_biens` montre des annonces NON publiées, et ce n'est pas une entorse.**
`rechercher_catalogue` filtre par `published()` parce qu'il répond à *tout le
monde* ; `mes_biens` filtre par `owner_id` parce qu'il répond au *seul
propriétaire*. Dans les deux cas, l'assistant montre ce que l'appelant verrait en
ouvrant son espace — ni plus, ni moins. C'est même l'intérêt de l'outil : un bien
en attente de validation est invisible partout ailleurs, et son propriétaire
croit régulièrement l'avoir mal déposé.

**Reconnaissance : un possessif ET un sujet.** « je cherche une réservation »
veut le catalogue, « où en est **ma** réservation » veut un dossier. Sans
l'exigence d'un mot de possession, la détection avalerait la moitié des
recherches du site. C'est aussi ce qui permet de reconnaître « mon bien » sans
réintroduire le faux positif de « je voudrais **bien** savoir comment payer »
(défaut corrigé en F10.0). Ces règles passent **avant** le catalogue dans
`RuleBasedBrain` : « ma réservation de villa » contient « villa ».

**Un outil hors trousse ne bloque pas.** Un client qui écrit « mes missions »
poursuit son chemin dans les règles suivantes plutôt que de buter sur un refus.

## Journalisation vers le back-office (F10.2)

**Arbitrage produit : aucune conversation n'est stockée.** Ce qui remonte à
l'équipe, ce sont les seules **escalades** — et elles y sont déjà, puisque le fil
de support porte le message d'origine dans son corps. Il manquait une chose : que
l'agent sache d'où vient la demande. Le sujet du fil est donc préfixé
**« Assistant — »**.

Deux fils identiques, l'un tapé dans la messagerie et l'autre passé par
l'assistant, ne s'instruisent pas pareil : le second signale au passage une
question que l'assistant n'a pas su traiter, donc une **FAQ à compléter**.

Aucune table, aucune donnée personnelle conservée en plus, rien à purger au RGPD.
⚠️ Le **corps** du fil n'est jamais retouché : l'agent doit lire les mots exacts
de la personne. On habille l'étiquette, pas le contenu.

---

## Configuration

| Variable | Défaut | Rôle |
|---|---|---|
| `ASSISTANT_ENABLED` | `true` | interrupteur général (503 si `false`) |
| `ASSISTANT_DRIVER` | `rules` | cerveau ; valeur inconnue → repli déterministe |
| `ASSISTANT_RATE_PER_MINUTE` | `12` | plafond du limiteur `assistant` |

Plafonds d'entrée dans `config/assistant.php` (`message_length`, `history_turns`,
`results_per_tool`) — ajustables sans redéploiement.

---

## Tests

`tests/Feature/Assistant/` — **43 tests, 146 assertions**.

- `AssistantGuardrailsTest` (13) — plafonds, débit 429, interrupteur 503 (y compris
  **avant la validation**), cloisonnement par rôle, charge utile d'escalade complète,
  lien de messagerie **résolu selon le rôle**, et **l'assistant ne crée aucune conversation**.
- `AssistantToolsTest` (15) — données réelles, budget, accueil, repli, non-régression des
  faux positifs de budget, **destinations touristiques comprises** (et absentes du
  vocabulaire tant qu'elles ne sont pas publiées), et surtout
  **`test_un_bien_non_publie_ne_fuite_pas_par_assistant`** : le test central du module.
  Sans lui, tout le travail de validation des annonces (F7) serait contournable en posant
  une question dans une bulle de discussion.
- `AssistantPersonalToolsTest` (15) — les outils des espaces connectés (F10.2). La question
  qu'ils posent n'est pas « l'assistant répond-il ? » mais **« ne répond-il qu'à la bonne
  personne ? »** : pour chaque outil, le dossier d'un **tiers** est créé à côté de celui de
  l'appelant. Un test qui ne vérifierait que « je vois mon dossier » passerait au vert sur un
  code qui montre aussi celui du voisin. S'y ajoutent la trousse par rôle (un client n'a pas
  l'outil « missions », et sa question ne bute pas dessus), la non-régression de l'adverbe
  « bien », et **« consulter ses dossiers n'écrit rien »**.

---

## Limites connues du socle (F10.0)

À dire franchement, pour que la démo ne promette pas plus que le code ne tient :

- **Pas de suivi de conversation.** L'historique est reçu, validé et plafonné, mais le
  cerveau déterministe ne s'en sert pas : chaque message est traité isolément. Une relance
  du type « et moins cher ? » n'est donc pas comprise et retombe sur le support. Le champ
  existe déjà dans le contrat parce que c'est `ClaudeBrain` (F10.4) qui l'exploitera —
  le frontend n'aura rien à changer ce jour-là.
- **Compréhension par mots-clés.** Une formulation qui n'emploie aucun mot reconnu part au
  support. C'est volontaire : mieux vaut passer la main qu'inventer une réponse.
- **Français uniquement.** Le wolof se limite aux salutations.
- **Débit compté par IP** pour les visiteurs anonymes : derrière le partage d'adresses des
  opérateurs mobiles, plusieurs visiteurs peuvent se partager le même quota. Ajustable via
  `ASSISTANT_RATE_PER_MINUTE` si le trafic réel le demande.

---

## Suite prévue

| Tranche | Contenu |
|---|---|
| ~~**F10.1**~~ | ✅ **livrée** — panneau Angular : bulle publique + espaces connectés (voir `frontend/src/app/shared/components/assistant/`) |
| ~~**F10.2**~~ | ✅ **livrée** — 5 outils des espaces connectés + journalisation par préfixe d'escalade |
| **F10.2** | outils client / propriétaire / prestataire / entreprise / diaspora (via policies) + journalisation |
| **F10.3** | outils back-office en **lecture seule** (vérification CDC §6) |
| **F10.4** | `ClaudeBrain` derrière le contrat déjà en place |

Le back-office reste en lecture seule en F10 : un assistant qui déclenche un reversement
sur une phrase mal comprise engage de l'argent réel. Si l'écriture s'ouvre plus tard, ce
sera avec confirmation humaine explicite à chaque geste.
