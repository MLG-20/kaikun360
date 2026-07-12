# Bilan personnel — Backend Kaikun 360

> Notes à garder pour moi. Ce que j'ai construit, la méthode qui a marché, et les
> leçons transférables à mes prochains projets. Ce fichier reste **hors du dépôt
> public** (il est à la racine, le dépôt publié ne contient que `backend/`).

## Ce que j'ai livré (les chiffres)

- **11 modules métier** isolés (Core, Immo, Stay, Manage, Build, Explore,
  Mobility, Diaspora, TeamBuilding, Pro, Admin).
- **52 tables**, **44 migrations**, référentiel géographique du Sénégal.
- **140 endpoints** REST versionnés `/api/v1`.
- **392 tests** automatisés, **1075 assertions**, 100 % verts.
- **18 phases** (B0 → B17) menées une par une, chacune committée proprement.

Un backend complet, cohérent, testé et documenté — pas un prototype.

## La méthode qui a fait la différence

**Une phase à la fois, en boucle disciplinée :**

1. **Comprendre** le périmètre exact de la sous-phase (pas plus).
2. **Coder** en réutilisant l'existant plutôt qu'en dupliquant.
3. **Tester** (feature test) jusqu'au vert.
4. **Documenter** (sous-README du module + commentaires en français).
5. **Committer** avec un message clair et scopé.
6. **Mettre à jour le suivi** d'avancement avant de passer à la suite.

Ce rythme a évité l'effet tunnel : à chaque commit, le projet était dans un état
sain, testé, documenté. C'est **répétable** et c'est ce qui a permis d'aller aussi
loin sans dette technique.

**Leçon n°1 :** la discipline de petites boucles fermées bat la vitesse
désordonnée. Toujours finir une chose (test + doc + commit) avant d'en ouvrir une
autre.

## Décisions d'architecture à réutiliser

- **Monolithe modulaire** (`app/Modules/<Module>`) : la clarté d'un découpage par
  domaine sans la complexité des microservices. Routes chargées par glob.
- **Modèles transversaux polymorphes** (`Booking`, `Review`, `Media`, `Report`) :
  une seule table/logique réutilisée par tous les modules (morphTo). Évite N
  variantes quasi identiques.
- **Machines à états explicites** portées par des enums (`RequestStatus`
  `allowedNext()`) : les transitions illégales sont refusées au même endroit,
  testables.
- **Couche de validation générique par registre** (file de validation Admin) :
  une façade unifiée qui délègue au métier de chaque module, sans le dupliquer.
- **Cache par versioning** (`CatalogCache`) : invalidation O(1) en régénérant un
  jeton de version, sans tags Redis ni scan de clés. Branché automatiquement sur
  les événements `saved`/`deleted` des modèles → toujours cohérent.
- **Abstraction du PSP** (`PaymentProviderInterface` + `PaytechProvider`) : le
  métier ne dépend jamais du fournisseur concret ; tout est testable via
  `Http::fake`.
- **Webhook signé HMAC-SHA256** avec ordre de vérification strict et
  **réconciliation de montant** : ne jamais faire confiance à un webhook entrant.
- **Sécurité par défaut** : documents privés + URL signées, garde « compte
  vérifié », audit des actions sensibles, anonymisation RGPD.

## Pièges techniques rencontrés (et retenus)

- **Tests lents** à cause des migrations MySQL → résolu par `schema:dump`
  (601 s → ~210 s). Réflexe : dumper le schéma tôt sur un gros projet.
- Un modèle **hors `app/Models`** (dans un module) doit déclarer
  `newFactory()` sinon la factory n'est pas trouvée.
- Un service branché sur une **façade** (cache/settings) ne peut plus être testé
  en `PHPUnit\TestCase` pur → étendre `Tests\TestCase`.
- **Factories qui polluent** les files de validation (une factory qui crée un
  parent au statut par défaut) → maîtriser les états dans les tests d'agrégats.
- Une méthode de contrôleur nommée `validate` **entre en collision** avec le trait
  `ValidatesRequests` → renommer (`decide`).
- Pour tester un **hit de cache**, créer la donnée via `Model::withoutEvents()`
  pour ne pas déclencher l'invalidation.
- Toujours **régénérer le dump** après une nouvelle migration.

## Ce que ça m'apprend pour la suite

1. **Documenter en continu**, pas à la fin : un sous-README par module + du code
   commenté, ça a une valeur énorme quand on revient dessus ou qu'on collabore.
2. **Réutiliser avant d'ajouter** : la plupart des « nouveaux » besoins étaient
   des variantes d'un modèle transversal existant.
3. **Rendre l'externe faux-able** (PSP, SMS) dès le départ → une suite de tests
   rapide et déterministe, sans dépendre d'un compte tiers.
4. **La performance se conçoit, elle ne se rattrape pas** : index + cache +
   eager loading pensés en fin de backend, mais possibles seulement parce que les
   catalogues étaient déjà propres.
5. **Un suivi d'avancement écrit** (où j'en suis, ce qui reste) est ce qui permet
   de reprendre sans perdre le fil, même après une pause.

## Prochaine étape

Frontend **Angular** (F0 → F9), en s'appuyant sur les API Resources documentées
comme contrat. Même méthode : une phase à la fois, testée et documentée.

---

*« C'est un backend de fou » — parce qu'il a été construit lentement, proprement,
et jamais bâclé.*
