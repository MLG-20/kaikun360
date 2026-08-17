# Kaikun 360 — Frontend (site web Angular)

> **En une phrase :** c'est la **partie visible** de Kaikun 360 — le site web avec
> lequel les utilisateurs interagissent réellement (les pages, les boutons, les
> formulaires).

---

## À quoi sert ce dépôt (expliqué simplement)

Si le [backend](../backend/README.md) est le **moteur** invisible, ce dépôt est la
**carrosserie et le tableau de bord** : tout ce que la personne voit et manipule à
l'écran. Quand un utilisateur clique sur « Se connecter » ou remplit un
formulaire, c'est ce site qui l'affiche, puis qui va poser la question au moteur
(via l'API) et afficher la réponse.

Il est construit avec **Angular** (une technologie de sites web modernes). Le site
est pensé **« mobile d'abord »** : il doit être agréable et rapide sur téléphone,
puisque la majorité des Sénégalais navigueront depuis leur smartphone.

### Où en est le site ?

- ✅ **Le socle graphique** (la charte Kaikun : couleurs, typographies, boutons,
  cartes…) et la structure de navigation sont en place.
- ✅ **L'authentification** : créer un compte (avec choix du profil), se connecter,
  vérifier son compte par code, récupérer un mot de passe oublié. 👉 Détail :
  [`src/app/features/auth/README.md`](src/app/features/auth/README.md).
- ✅ **Les pages publiques** : accueil, univers (immobilier, nuitées, tourisme,
  transport…), moteur de recherche et catalogue, fiches détaillées, pages de
  conversion, formulaires intelligents, FAQ, contact et pages légales. Les cartes
  du catalogue et de l'accueil portent un **cœur de favori** (tous univers) :
  le client connecté ajoute/retire d'un clic ; un visiteur anonyme est invité à
  se connecter. Chaque grande page — **et enfin la page de résultats** — s'ouvre
  sur un **bandeau dont l'image et les mots se pilotent depuis le back-office**,
  sans redéploiement (voir « Les bandeaux d'en-tête » plus bas).
  👉 Détail : [`src/app/features/README.md`](src/app/features/README.md).
- ✅ **Le rendu côté serveur (SSR)** : les pages publiques sont d'abord
  **assemblées par un serveur** puis envoyées prêtes à afficher (bon pour le
  référencement Google et pour un premier affichage rapide). Voir « SSR » ci-dessous.
- ✅ **L'assistant** : un bouton **« Assistant » dans l'en-tête** ouvre un **tiroir**
  qui glisse depuis le bord droit, sur toute la hauteur. On lui décrit un besoin (« une villa à Saly sous
  60 millions », « un circuit en Casamance ») et il répond avec de **vraies
  annonces** — celles du catalogue publié, aux prix du catalogue — puis des
  **boutons** pour aller plus loin : voir la fiche, ouvrir toutes les annonces,
  écrire à un conseiller. Il répond aussi aux questions sur le fonctionnement du
  site à partir de la **FAQ tenue par l'équipe** au back-office, et il **passe la
  main** dès qu'il ne comprend pas plutôt que d'inventer.
  - **Où on le trouve** : sur tout le site public, dans les **quatre espaces
    connectés**, et depuis F10.3 dans le **back-office**. Volontairement **pas**
    sur les pages de connexion : on n'interrompt pas une saisie de mot de passe.
  - **La discussion suit l'utilisateur** d'une page à l'autre, y compris quand
    l'assistant l'envoie sur une fiche puis qu'il revient. Elle n'est **jamais
    enregistrée** sur l'ordinateur : elle disparaît avec l'onglet.
  - **L'assistant propose, il n'écrit rien.** Ouvrir un fil avec un conseiller
    n'a lieu qu'au clic, et passe par le circuit habituel de la messagerie.
  - **Connecté, il lit vos dossiers** (F10.2) : « où en est ma réservation ? »,
    « est-ce que mon annonce est en ligne ? », « quelles sont mes missions ? ».
    Chacun ne voit que **les siens**, et seulement ce que son espace lui montre
    déjà — un propriétaire retrouve ainsi son bien *en attente de validation*,
    invisible partout ailleurs. L'assistant **consulte, il ne modifie rien** :
    payer, annuler ou accepter une mission reste un geste posé sur l'écran
    concerné, avec sa confirmation.
  - **Pour l'équipe, dans le back-office** (F10.3) : « que reste-t-il à valider ? »,
    « quelles demandes restent à traiter ? », « quels messages attendent une
    réponse ? », « retrouve-moi le compte de… », « où en est le paiement PAY-… ».
    Le panneau y prend un **vocabulaire différent** (son sous-titre, son exemple de
    question et sa mention de pied), mais **ce qu'il sait faire ne dépend pas de la
    page** : la trousse est composée par le serveur à partir du compte connecté.
    ⚠️ **Chacun n'y voit que ce que ses droits lui ouvrent déjà à l'écran** — un
    agent à qui l'on n'a pas délégué « Gérer les paiements » se voit répondre que
    ses droits ne couvrent pas ce dossier, exactement comme sur l'écran. Et
    l'assistant y est en **lecture seule** : il ne valide, ne confirme et ne
    rembourse rien, ces gestes se prennent sur la fiche du dossier.
  - **Le moteur intelligent (F10.4) n'a rien changé ici.** Depuis le serveur, un
    cerveau conversationnel a remplacé la compréhension par mots-clés — l'assistant
    suit désormais le fil (« et moins cher ? ») — et **pas une ligne du panneau n'a
    bougé** : ni le service, ni le store, ni l'écran. C'était la raison d'être du
    contrat figé en F10.0. ⚠️ Conséquence à connaître pour la vérification au
    navigateur : **on ne voit pas quel moteur répond**. Les deux remplissent la même
    structure, et un repli (clé absente, fournisseur en panne) est **volontairement
    indiscernable** à l'écran — c'est le serveur qui le journalise.
  - ⚠️ L'assistant **ne figure pas au cahier des charges** : c'est un ajout, et il
    peut être **coupé côté serveur** sans déploiement — la bulle disparaît alors
    d'elle-même. Voir
    [`backend/app/Modules/Assistant/README.md`](../backend/app/Modules/Assistant/README.md).
- ✅ **Le référencement (F9.1/F9.2)** : chaque page publique part avec son titre,
  sa description, son adresse canonique et son aperçu de partage (celui qui
  s'affiche quand on colle un lien dans WhatsApp) ; les fiches y ajoutent leur
  vraie photo et leurs **données structurées** (prix, ville, disponibilité), que
  Google sait afficher directement dans ses résultats. Le site publie aussi un
  `robots.txt` et un **plan du site** listant toutes les annonces en ligne. Les
  espaces connectés, eux, sont tenus **hors des moteurs**. Voir
  « Référencement » ci-dessous.
- ✅ **L'espace client (F3)** : l'espace personnel de la personne connectée, sous
  `/mon-espace` (menu latéral sombre, en-tête épuré). Ses écrans sont en
  place — tableau de bord, **profil** (photo, identité, coordonnées, sécurité, pièces),
  **mes demandes** (liste + détail cliquable), **réservations** (liste + détail
  cliquable, **règlement** et, une fois le service terminé, **dépôt d'un avis** —
  F8.15.a : `POST /reviews` n'avait jusque-là aucun écran, et aucune réservation
  ne devenait jamais « terminée », si bien que personne n'avait jamais pu noter
  quoi que ce soit), **favoris** (tous univers, avec le cœur du
  catalogue pour les ajouter), **notifications** et **messagerie** (conversations
  + fil de discussion avec réponse). **« Projets diaspora »** (F3.8 —
  `GET/POST /diaspora-projects…`) : le client **lance et suit ses dossiers
  pilotés à distance** (achat, construction, gestion locative) et consulte, pour
  chacun, la **chronologie des rapports** de suivi (photos, vidéo, commentaires
  datés) déposés par son référent Kaikun — le cœur de la promesse « confiance par
  la preuve », et la mise en conformité du critère CDC §15. La même rubrique
  accueille depuis **F3.9** le bloc **« Mes chantiers & devis »**
  (`shared/components/construction-quotes/`), où le client **accepte ou refuse un
  devis de construction** — le chaînon qui manquait au cycle ouvert en F7.3.e2 :
  l'équipe envoyait un devis ventilé par lot, mais aucun écran ne permettait d'y
  répondre, et le dossier restait bloqué en « devis envoyé ». Composant
  **autonome** (il charge son propre `GET /construction-requests/mine`, devis
  inclus en un seul appel) et **placé hors du `@switch`** de la page : un
  incident sur les projets diaspora ne doit pas empêcher de répondre à un devis.
  Trois partis pris d'interface : **confirmation en deux temps** (accepter engage
  des millions de francs, un clic malheureux sur un téléphone ne doit pas
  suffire), **détail des lots replié** par défaut (le total d'abord, la preuve
  ensuite) et **validité signalée sans bloquer le bouton** — c'est le serveur qui
  tranche ce qui est acceptable, pas l'horloge du téléphone. ⚠️ Rattachement par
  **client**, pas par projet (`diaspora_projects` n'a pas de clé vers
  `construction_requests`). 👉 Détail :
  [`src/app/features/account/README.md`](src/app/features/account/README.md).
- 🖼️ **Identité visuelle du compte (F8.0), commune aux quatre espaces** : la page
  **« Mon profil »** étant montée dans chacun d'eux, un seul bloc suffit — le
  client, le propriétaire et le prestataire y déposent leur **photo**,
  l'entreprise son **LOGO**. L'interface ne le devine pas depuis le rôle : le
  backend le dit (`profile.avatar_kind`), et les libellés, le cadrage
  (portrait rond `object-fit: cover` / logo encadré `contain` sur fond blanc) et
  le texte d'aide suivent. L'image remplace l'initiale dans l'**en-tête de
  l'espace** (`layouts/space-layout/space-header`) et s'y met à jour **sans
  rechargement** : le dépôt renvoie l'utilisateur complet, que la page pousse
  dans `AuthService.setCurrentUser()` — source unique du compte connecté, plutôt
  que deux états locaux à recoller. Sans image, on retombe sur l'initiale et
  **jamais sur une silhouette générique**, qui laisserait croire à tort qu'une
  photo a été déposée.
- ✅ **L'espace propriétaire (F4, terminé)** : sous `/espace-proprietaire`,
  réservé au rôle « propriétaire ». Il réutilise **le même habillage** que
  l'espace client (menu latéral sombre + en-tête épuré), désormais **généralisé
  en un shell partagé** (`layouts/space-layout/`, paramétré par espace) pour
  servir aussi les futurs espaces pro. Écrans livrés : le **tableau de bord de
  gestion locative** (F4.1 — mandats actifs, loyers encaissés / impayés,
  dépenses, reversements, incidents ouverts) et **« Mes biens »** (F4.2 — liste
  de tous ses biens **quel que soit leur statut** avec une pastille de **suivi de
  validation** — publié / en attente / rejeté — et une fiche détaillée), puis le
  **dépôt et l'édition d'un bien** (F4.3 — un même formulaire pour créer et
  modifier, avec le **mode de location** mensuelle / nuitées / mixte qui pilote
  les champs affichés et les appels d'enregistrement, et une localisation en
  cascade région → département → commune). Le propriétaire y **illustre ses
  biens** : dépôt de plusieurs photos, choix de l'image de couverture et retrait
  — ces photos alimentent sa fiche, les **cartes du catalogue** et la **galerie
  des fiches publiques** (bien et nuitées), un bien sans photo gardant la
  vignette dégradée de repli. Enfin la **gestion locative** (F4.4 — en lecture
  seule) : la liste de ses **mandats** puis la **fiche d'un mandat** avec un
  résumé financier, les loyers / reversements / incidents récents et un **rapport
  mensuel** recalculable par mois (loyers encaissés, commission Kaikun, **net à
  reverser**). Enfin les **Documents** (F4.5) : par bien, le propriétaire liste,
  **dépose** (titre foncier / bail / plan, PDF ou image ≤ 5 Mo), **télécharge**
  (lien signé temporaire) et **supprime** les pièces justificatives — la liste
  des biens affiche le nombre de documents de chacun. 👉 Détail :
  [`src/app/features/owner/README.md`](src/app/features/owner/README.md).
- 🎉 **L'espace prestataire (F5, terminé)** : sous `/espace-prestataire`,
  réservé au rôle « prestataire ». Il réutilise **le même shell partagé** que les
  espaces client et propriétaire (`layouts/space-layout/`). Écran livré : le
  **tableau de bord** (F5.1 — `GET /providers/mine`) qui affiche l'**état du
  dossier prestataire** — statut de validation (en attente / validé / refusé /
  suspendu), note moyenne, avis reçus, certifications (vérifiées ou en cours) et
  avertissements. **« Missions reçues »** (F5.2 —
  `GET /provider-missions/mine`) : la liste paginée des missions confiées, avec
  montant, commission Kaikun, **net** prestataire, date prévue et statut, plus des
  **actions** de transition (accepter / refuser une mission affectée, la démarrer,
  la marquer terminée). **« Revenus & commissions »** (F5.3 —
  `GET /provider-missions/earnings`) : la synthèse financière en deux blocs, le
  **réalisé** (missions terminées : chiffre d'affaires, commission Kaikun, net
  encaissé) et l'**à venir** (missions acceptées ou en cours). **« Disponibilités »**
  (F5.4 — `GET/PUT/POST/DELETE /providers/availability…`) : un **planning
  hebdomadaire récurrent** (7 jours, ouvert/fermé + horaires) et des **périodes
  d'indisponibilité** ponctuelles (congés) qui priment sur le planning.
  **« Mes services »** (`GET/PUT /providers/mine`,
  `POST/DELETE /providers/certifications…`) : édition du **descriptif du service**
  (raison sociale, catégorie, présentation) et gestion des **documents de
  certification** — enregistrer ne relance pas la validation, un document ajouté
  reste « En vérification ». Depuis **F8.0**, chaque certification peut porter
  son **justificatif** (PDF/JPG/PNG ≤ 5 Mo) : le champ fichier est facultatif,
  la liste affiche soit un lien de téléchargement (URL signée, 10 min) soit
  « Aucun justificatif joint », et l'aide dit franchement que **sans pièce,
  l'agent n'a rien à contrôler**. Envoi en `FormData` **seulement** quand un
  fichier est joint : `FormData` ne transporte que du texte, un organisme absent
  y arriverait en chaîne vide au lieu de `null`. **« Avis reçus »** (F5.5 — `GET /providers/reviews`) :
  les avis publiés qui concernent le prestataire, réunissant ceux laissés sur ses
  **ressources** (véhicules, expériences) et les **avis directs** déposés après une
  mission — une **synthèse de notation** (note moyenne, total, histogramme de
  répartition par étoiles) surmonte la **liste des avis** (auteur, source,
  commentaire, date). **« Mes offres »** (F5.6 — `GET/POST/PATCH /vehicles…`,
  `GET/POST /experiences` ; F8.23 — `GET/POST/PATCH/DELETE /mobility-services`) :
  le prestataire **dépose et suit ses prestations réservables** — véhicules (les
  8 catégories distinctes : voiture particulière, touristique, navette
  aéroportuaire, bus, minibus, 4x4, pirogue, chauffeur), **départs programmés**
  et **circuits touristiques** — chacune avec son **statut de validation** ; les
  champs de sécurité s'adaptent au type (assurance/chauffeur pour un motorisé,
  gilets/conformité météo pour une pirogue). C'est le geste central attendu par
  le cahier des charges (§5.2 / §15), désormais couvert. 👉 Détail :
  [`src/app/features/pro/README.md`](src/app/features/pro/README.md).
- 🎉 **L'espace entreprise (F6, terminé)** : sous `/espace-entreprise`, réservé au
  rôle « entreprise » (entreprises, ONG, écoles, institutions). Il réutilise **le
  même shell partagé** que les autres espaces. Écrans livrés : un **tableau de
  bord** d'accueil avec l'appel à l'action principal ; **« Nouvelle demande »**
  (`POST /team-building-requests`) — un formulaire reprenant les informations du
  cahier §9.4 (participants, ville, dates, budget, besoins hébergement /
  restauration / activités / transport / animation, descriptif) ; **« Mes
  demandes »** (`GET /team-building-requests/mine`, paginé) — l'historique des
  demandes de team building avec pastille de statut ; et le **détail d'une
  demande** (`GET /team-building-requests/{id}`) qui affiche les **devis composés**
  par Kaikun (lignes détaillées, sous-total, frais de coordination, total) et
  permet d'**accepter** un devis envoyé (`PATCH /team-building-quotes/{id}/accept`).
  L'espace inclut aussi la **messagerie** (cahier §5 « Messages = Tous ») : les
  écrans de messagerie génériques y sont montés, rendus **autonomes** par le jeton
  `SPACE_CONFIG` (plus aucun lien codé en dur vers `/mon-espace`), et une **notif
  in-app** prévient l'entreprise dès qu'un devis lui est envoyé. 👉 Détail :
  [`src/app/features/enterprise/README.md`](src/app/features/enterprise/README.md).
- 🎉 **Le back-office (F7.1, terminé)** : sous `/back-office`, réservé aux rôles
  **staff** (agent / admin / super_admin) par `roleGuard`. **Il NE réutilise PAS
  le shell des espaces** (`layouts/space-layout/`) : décision produit d'un **shell
  dédié et indépendant** (`layouts/backoffice-layout/`, rail graphite « Poste de
  commandement », identité « salle de contrôle »), pour un niveau de sécurité
  maximal. **Connexion à deux facteurs** : à la connexion d'un compte admin/
  super_admin, la page bascule sur la saisie d'un **code reçu par e-mail**
  (`auth.service` renvoie un `LoginOutcome` discriminé → `POST /auth/two-factor`),
  et les comptes staff atterrissent au back-office (`spaceHomeFor`). Écrans livrés
  (`features/backoffice/`, données via `core/api/admin.service.ts`) : **Vue
  d'ensemble** (KPIs), **Équipe** (annuaire + enrôlement + rôle/statut),
  **Permissions** (matrice de délégation « grant pur » par agent), **Pointeuse**
  (pointer entrée/sortie + feuille mensuelle d'équipe + export CSV).
- ▶ **F7.2 — les autres écrans du back-office** (frontend pur sur l'API Admin de
  B13). **F7.2.a Validation** (`features/backoffice/validation/`) : file
  d'approbation générique (`GET /admin/queue` + `PATCH /admin/validate/{type}/{id}`)
  avec **onglets par type** (biens / véhicules / expériences / prestataires) et
  compteurs, le **déposant identifié** sous chaque ligne (nom + e-mail/téléphone
  cliquables) et la décision **valider / refuser** (motif facultatif).
  **F7.2.b Catalogues** (`features/backoffice/catalogues/`) : navigateur de
  supervision (`GET /admin/properties|vehicles|experiences`) avec onglets
  Biens / Véhicules / Expériences, affichant **tous les statuts** (et pas
  seulement le publié comme le catalogue public), filtres statut + recherche,
  pagination. L'approbation reste dans l'écran Validation.
  **F7.3.g — les biens y deviennent modifiables** (dette CDC §15). Deux gestes
  par ligne, **onglet Biens uniquement** : **Corriger** (panneau intitulé / prix
  / description) et **Archiver** (motif facultatif) — ou **Sortir d'archive**
  pour une annonce archivée. Trois points à connaître : la **description laissée
  vide n'est pas envoyée** (la liste ne la transporte pas — l'envoyer vide
  effacerait un texte existant) ; la page est **rechargée** après écriture, car
  un changement de statut peut faire sortir la ligne du filtre courant ; et
  sortir de l'archive renvoie le bien **en file de validation**, ce que l'écran
  annonce. Les panneaux rappellent que le bien **reste à son propriétaire** et
  que localisation, photos et reste du dossier se modifient depuis son espace.
  **F7.2.c Nuitées** (`features/backoffice/stays/`) : exploitation hôtelière —
  calendrier des séjours (`GET /admin/stays/calendar`), filtre par période, et
  cycle **arrivée → départ → ménage** piloté par ligne (`PATCH
  /admin/stay-bookings/{id}/check-in|check-out|housekeeping`). Les boutons
  s'adaptent à l'étape du séjour (À venir / Sur place / Parti).
  **F7.3.f — la caution** : colonne dédiée (montant + pastille retenue /
  restituée / conservée) et, **une fois le départ enregistré**, deux gestes par
  ligne — **Restituer** (immédiat) et **Conserver**, qui déplie une saisie de
  **motif obligatoire**. Tant que le client est sur place, l'écran affiche
  « à trancher après le départ » plutôt que des boutons qui échoueraient en 422 :
  les règles sont tenues par le serveur, l'écran ne les duplique pas, il les
  rend lisibles. Le motif part au journal d'audit avec le montant — il fait foi
  en cas de contestation, et l'écran le dit sous le champ.
  **F7.2.d Paiements** (`features/backoffice/payments/`) : supervision
  (`GET /admin/payments`, filtres statut + référence) + deux actions sensibles —
  **confirmer** un règlement manuel Wave/OM (`POST …/confirm`, panneau avec preuve
  de transaction) et **rembourser** total/partiel un paiement encaissé
  (`POST …/refund`, panneau avec montant). Les actions disponibles dépendent du
  mode/statut (manuel non encaissé → confirmable ; `complete` → remboursable).
  **F7.3.h — acomptes & soldes** : deux colonnes de plus, la **nature** du
  règlement (acompte en évidence, solde, intégral — déduite du montant côté
  serveur) et le **reste dû** sur la réservation, montant restant sur le total.
  Sans elles, un versement de 50 000 F sur une réservation de 180 000 F était
  indistinguable d'une erreur de saisie.
  **F8.16.a Reversements** (`features/backoffice/payouts/`) : l'autre sens du
  flux — ce que Kaikun **doit** aux partenaires, et non ce qu'elle encaisse.
  Rubrique à part et non un onglet de Paiements : ce ne sont ni les mêmes objets
  (une dette n'est pas un règlement) ni le même moment du métier. Deux onglets,
  parce qu'il y a deux questions distinctes — « **À payer** » répond à *qui
  dois-je payer et combien*, avec **une ligne par partenaire** (`GET
  /admin/partner-dues/beneficiaries`, agrégé côté serveur : on ne vire pas à une
  réservation, on vire à quelqu'un), et « **Versements** » est l'archive de ce
  qui est parti. ⚠️ **Deux montants séparés sur chaque ligne, jamais
  additionnés** : le *payable aujourd'hui* et l'*acquis encore sous délai*. Les
  confondre ferait virer de l'argent avant que le délai d'annulation du client
  soit écoulé, et un reversement parti trop tôt se réclame au partenaire.
  ⚠️ **`is_payable` vient du serveur** (miroir du scope `payables()`) : l'écran
  ne rejoue pas la règle « exigible ET sans lot » — une règle d'argent ne vit
  qu'à un endroit. ⚠️ **La sélection ne survit jamais au changement de
  partenaire** : un lot ne concerne qu'un bénéficiaire (le serveur le refuse), et
  des cases restées cochées d'un autre partenaire produiraient un 422
  incompréhensible. ⚠️ Le **justificatif est exigé avant l'appel**, pas seulement
  par le serveur : laisser revenir un 422 sur un formulaire vidé ferait
  ressaisir le canal et la référence. ⚠️ L'URL du justificatif est **signée 10
  minutes** et posée en `[href]` — jamais appelée par `HttpClient`, la signature
  valant pour une requête de navigateur et non pour un appel authentifié.
  **F8.18 Photos des annonces** (`shared/components/photo-manager/`) : le bloc de
  dépôt de photos n'existait que dans le formulaire de bien du propriétaire —
  c'était **le seul appelant de `POST /media/upload` de tout le frontend**. Un
  loueur de véhicule ou un organisateur de circuit ne pouvait illustrer son
  annonce par aucun moyen, et trois univers du catalogue sur cinq
  (`transport`, `tourisme`, `mobilite`) codaient `image: null` **en dur** dans
  `catalog.config.ts`, tandis que les fiches détail montaient une galerie sans
  jamais lui passer d'images. ⚠️ **Extrait en composant partagé plutôt que
  recopié** : la même centaine de lignes dans trois formulaires aurait divergé au
  premier correctif. ⚠️ **Les photos existantes arrivent par une ENTRÉE, jamais
  poussées par le parent** : le bloc vit dans une branche conditionnelle du
  gabarit (compte non vérifié, annonce en cours de chargement) et un
  `viewChild.required` y viserait un composant pas encore monté. ⚠️ **Le dépôt
  est différé** : en création l'annonce n'a pas d'id, envoyer plus tôt exigerait
  des médias temporaires à nettoyer si le partenaire abandonne. ⚠️ **La première
  photo devient la couverture** — c'est elle, et elle seule, qui illustre la
  carte du catalogue. L'écran back-office, lui, n'a rien demandé : il affichait
  déjà les galeries depuis F8.1, il n'était que privé de contenu.
  **F8.22 « Mon compte » au back-office** (`features/backoffice/account/`, route
  `mon-compte`) : le profil (F3.2) n'est monté que dans les espaces client,
  propriétaire, prestataire et entreprise. Un **super administrateur**, qui n'a
  aucun de ces espaces, ne pouvait donc changer ni son mot de passe ni son
  adresse **depuis nulle part** — le compte le plus puissant de la plateforme
  était le seul à ne pas pouvoir entretenir ses identifiants. ⚠️ **Deux
  formulaires séparés, jamais un seul** : changer d'adresse déplace la serrure
  (la récupération de compte partira ailleurs), changer de mot de passe ferme les
  autres sessions. Les fondre ferait faire les deux à qui n'en voulait qu'un.
  ⚠️ **Hors de la liste des rubriques** du rail (qui reflète les 14 modules du
  CDC §6 et le §7) : entretenir ses identifiants n'est pas un module métier. Il
  vit au pied du rail et sous l'identité de l'en-tête, là où on le cherche.
  ⚠️ **L'écran profil client a suivi** : il envoie l'e-mail à chaque
  enregistrement et aurait buté sur un 422 depuis que le serveur exige le mot de
  passe actuel pour un changement d'adresse. Le champ **n'apparaît que si
  l'adresse est réellement modifiée** — le réclamer pour corriger un numéro de
  téléphone serait une friction gratuite.
  **F8.21 Fiche d'un message de contact** (`features/backoffice/messages/detail/`,
  route `messages/contact/:id`) : la liste portait le **message entier** dans une
  colonne — choix de F8.15.c, au motif qu'il n'y avait « aucune fiche à ouvrir
  derrière ». À l'usage, un tableau de cinq colonnes dont une contient un
  paragraphe **déborde de l'écran** (il fallait défiler horizontalement pour
  atteindre le bouton d'action) et tronque quand même les messages longs. La
  liste redescend à **trois colonnes** — qui écrit, quand, où ça en est — et le
  courrier se lit sur sa fiche. ⚠️ **La route est déclarée AVANT `messages/:id`**,
  sinon « contact » serait pris pour l'identifiant d'un fil. ⚠️ **Ce n'est
  toujours pas une conversation** et l'écran le dit : l'auteur n'a le plus
  souvent pas de compte, la réponse part par e-mail — d'où un bouton qui
  **préremplit l'objet** avec le sujet du message, et « Marquer traité » qui
  n'envoie rien mais évite que deux agents rappellent le même prospect.
  **F8.19 Corriger et retirer une offre** (`features/pro/offers/`) : l'écran
  « Mes offres » ne proposait de **modifier** que les véhicules — un circuit
  déposé était définitif, donc **impossible à illustrer après coup**, ce qui
  privait F8.18 de tout effet sur les circuits existants. Et **aucune offre ne
  pouvait être retirée**, des deux côtés. Le formulaire de circuit sert désormais
  les deux modes (la présence d'un `:id` bascule en édition, comme celui des
  véhicules), et chaque ligne porte « Modifier » et « Retirer ». ⚠️ **Le serveur
  décide, l'écran annonce** : il répond `deleted` + `reason`, parce que
  « supprimé » et « retiré mais conservé pour l'historique de vos clients » ne se
  disent pas de la même façon à quelqu'un qui vient de cliquer. ⚠️ **Confirmation
  en deux temps dans la ligne**, jamais `window.confirm` : la boîte native
  n'existe pas au rendu serveur et ne peut pas expliquer une conséquence qui
  varie d'une offre à l'autre. ⚠️ La liste affiche la **vignette de couverture**
  et une mention « Sans photo » : le prestataire repère d'un coup d'œil les
  annonces qui ne se loueront jamais.
  **F8.23 Programmer un départ** (`features/pro/offers/provider-departure-form-page`,
  routes `offres/depart/nouveau` et `offres/depart/:id/modifier`) : troisième
  bloc de « Mes offres », et le dernier geste **structurellement** manquant du
  produit. ⚠️ **`mobility_services` était en lecture seule côté serveur depuis
  B7.2** : le catalogue public `/mobilite` ne pouvait être alimenté que par le
  seeder — aucune navette AIBD, aucune liaison interurbaine n'était mettable en
  vente. ⚠️ **Écran distinct de celui du véhicule, pas des champs en plus** : un
  même minibus assure une navette le lundi et une liaison le mardi ; ce qui se
  vend est le **trajet daté**. ⚠️ **Aucun bloc photo, délibérément** — un départ
  hérite des photos de son véhicule (F8.18), et l'écran le **dit** plutôt que de
  laisser le prestataire chercher un téléversement qui n'existe pas. ⚠️ Le
  sélecteur de véhicule ne propose que **mes** véhicules, avec leur capacité
  affichée : le serveur refuse celui d'un concurrent et plafonne les places
  vendues — autant rendre le cas fautif **impossible à saisir** plutôt que de le
  traduire en message d'erreur. ⚠️ La liste signale « ce départ a déjà eu lieu »
  quand l'heure est passée : **aucun statut ne le dit**, un départ périmé reste
  « Publié » et n'est pourtant plus réservable. ⚠️ `datetime-local` lu et écrit
  en heure **locale**, jamais via `toISOString()` (qui afficherait un bus de
  06:00 à 05:00 GMT). La feuille de style du formulaire véhicule est devenue
  `offers/offer-form.scss`, **partagée** — ne pas en recréer une par formulaire.
  **F7.2.e Dossiers → F7.3.c : deux rubriques distinctes.** L'écran unique à
  onglets (`features/backoffice/dossiers/`) a été **scindé** en
  `features/backoffice/construction/` (route `construction`) et
  `features/backoffice/rental/` (route `gestion-locative`), chacun avec sa
  rubrique au rail et sa fiche. Motif : deux métiers qui n'ont ni le même
  cycle ni les mêmes gestes, réunis sous un même écran devenu illisible à
  mesure que chacun gagnait sa fiche de pilotage. Contenu initial (F7.2.e) :
  supervision transverse
  des suivis longs, en **lecture seule**, avec deux onglets chargés à la demande —
  **Construction** (`GET /admin/construction-requests`) : objectif, ville, surface,
  budget / coût estimé, niveau de finition, avancement (rapports / jalons) et
  statut, filtres statut + ville ; **Gestion locative** (`GET /admin/mandates`) :
  bien géré + propriétaire, commission, période, agrégats financiers (loyers payés /
  **impayés en alerte**, dépenses, reversements, incidents ouverts) et statut,
  filtre statut. Petit enrichissement backend au passage (`milestones_count` et les
  compteurs bruts `rents/incidents/expenses/payouts` surfacés dans les Resources,
  déjà comptés côté contrôleur admin).

  **F7.3.a Fiche mandat PILOTABLE** (`features/backoffice/rental/detail/`,
  route `gestion-locative/:id` depuis F7.3.c) : une ligne de la liste est
  désormais **cliquable** et ouvre la fiche de pilotage — l'onglet reste une
  supervision, la fiche porte les actions, comme pour les comptes (F7.2.f) ou le
  team building (F7.2.h). Les six fonctions de la ligne CDC §6 y sont : **le
  contrat** (clauses, commission, période), les **loyers** (ajouter une échéance,
  l'encaisser), les **incidents** (signaler, **clore**), les **dépenses**
  (enregistrer, rattachables à un incident ouvert), les **reversements**
  (préparer, marquer effectué) et le **rapport mensuel** recalculable par mois.
  ⚠️ Ces routes sont sous `/manage`, **pas** `/admin` : elles vivent dans le
  module métier et exigent `gerer:gestion-locative` en écriture (403 explicite).
  ⚠️ Le serveur borne chaque liste aux **12 dernières lignes** — l'écran le dit,
  pour qu'on ne la prenne pas pour l'historique complet ; les totaux du rapport,
  eux, portent sur tout le mois. Après chaque écriture la fiche est **rechargée
  entièrement** : une écriture déplace les agrégats ET le rapport, recoller la
  réponse partielle laisserait des totaux faux à l'écran.

  **F7.3.b Fiche demande de construction**
  (`features/backoffice/construction/detail/`, route `construction/:id` depuis
  F7.3.c) : la liste Construction n'affichait qu'un tableau,
  illisible pour un dossier de chantier dont l'essentiel — qui a demandé quoi, où
  en est le chantier, ce qui a été constaté sur place — ne tient pas dans une
  ligne. La fiche restitue **le demandeur** (nom + contact cliquable), **le
  projet** (avec l'**écart budget / estimation** mis en évidence : un projet
  sous-budgété part mal), **l'avancement** (jalons dans l'ordre + jauge) et les
  **comptes rendus** photo/vidéo, avec publication d'un nouveau compte rendu.
  **F7.3.e1 — les jalons deviennent PILOTABLES.** Ils étaient semés au dépôt
  puis figés, faute d'endpoint (trou backend comblé dans le module Build). La
  timeline porte désormais deux gestes distincts, parce que ce sont deux
  métiers : *faire avancer* (**Démarrer** / **Achever** / **Rouvrir** selon
  l'état) et *replanifier* (**Modifier** nom + dates, **↑ ↓** pour déplacer,
  **Retirer**, et un formulaire **+ Ajouter un jalon** qui part en fin de
  planning). Deux points à connaître : la **cohérence statut ↔ date réelle est
  tenue par le serveur** (achevé sans date = daté du jour, réouverture = date
  effacée) et l'écran ne la refait pas ; le **réordonnancement envoie la liste
  ordonnée complète** plutôt qu'une position par jalon, car échanger deux
  positions en deux requêtes créerait un doublon transitoire. La fiche n'est
  **pas rechargée** après une écriture sur un jalon — à la différence de la
  fiche mandat (F7.3.a) où les agrégats bougeaient : le serveur renvoie le jalon
  à jour et la jauge d'avancement est un `computed` local. ⚠️ Restent hors
  périmètre à ce stade, livré juste après : l'affectation de prestataires BTP
  (F7.3.e3, ci-dessous).

  **F7.3.e2 — les devis de chantier.** Nouvelle carte *Devis* sur la fiche. Un
  **composeur** ajoute des lignes ventilées par **lot** (12 corps d'état) avec
  désignation, unité (liste suggérée, saisie libre), quantité décimale et prix
  unitaire ; le montant de chaque ligne et le sous-total s'affichent à la saisie
  (aperçu : c'est le serveur qui recalcule et fait foi). Chaque devis se déplie en
  tableau lot par lot avec sous-total / marge / total, et un brouillon
  s'**envoie au client** en un clic. Trois points à connaître : le champ **marge
  laissé vide** signifie « taux du back-office » et l'aperçu n'affiche alors PAS de
  total — `GET /admin/settings` exige `gerer:parametres`, qu'un agent chantier n'a
  pas, donc afficher un taux deviné serait un chiffre faux ; les lignes du
  composeur sont remplacées et non mutées, sinon le sous-total (`computed`) ne se
  recalculerait pas ; et **la fiche EST rechargée** après un chiffrage ou un envoi
  (contrairement aux jalons) parce que le statut du dossier change dans l'en-tête.
  ⚠️ **Écart signalé** : accepter / refuser un devis appartient au **client**
  (policy `respond`) — ce n'est donc pas exposé dans le back-office, et l'espace
  client n'a **pas encore** d'écran de suivi de ses demandes de construction pour
  le faire.

  **F7.3.e3 — les prestataires BTP.** Dernière carte de la fiche : la liste des
  intervenants (lot, prestataire, date d'intervention, montant + commission,
  statut de mission) et un formulaire d'affectation **par corps d'état**. Le
  sélecteur ne propose que des prestataires **validés** (`GET /admin/providers?status=valide`)
  — ⚠️ cet endpoint exige `valider:prestataire` : un compte qui ne l'a pas voit un
  message explicite au lieu d'un sélecteur vide inexplicable. Affecter crée une
  **mission Pro** rattachée au chantier, avec son cycle et sa commission figée ;
  l'écran le dit sous le formulaire, pour qu'on ne prenne pas l'affectation pour
  une simple étiquette. ⚠️ Le type `ProviderMissionItem.category` est devenu
  l'**union** brique de pack | lot BTP — la colonne est partagée côté serveur ; la
  fiche team building a été ajustée en conséquence (le compilateur l'a signalé).

  **F7.3.d Export comptable** (onglet ajouté à `features/backoffice/payments/`) :
  `GET /admin/reports/export` était livré et testé depuis B13.5 mais **aucun
  écran ne l'appelait**. Il est branché ici, et non dans une rubrique de plus,
  parce que l'export est une fonction du module *Paiements* du CDC §6. L'écran
  passe donc à deux onglets — **Règlements** (l'existant) et **Export
  comptable** : période libre (deux dates) + quatre raccourcis (mois en cours,
  mois dernier, année, tout l'historique), **totaux de la période** en tuiles,
  puis le **grand livre des réservations** et les **reversements effectués** en
  tableaux, enfin le **téléchargement CSV** sur la même période (blob + lien
  synthétique, comme l'export de la pointeuse en F7.1.h). Trois points à
  connaître : le rapport est **affiché avant d'être téléchargé** (un bouton nu
  obligerait à ouvrir le fichier pour savoir ce qu'il contient) ; il n'est
  calculé qu'à la **première** ouverture de l'onglet puis conservé, l'agrégation
  balayant toutes les réservations ; les dates sont formatées en **local** et non
  via l'UTC, sinon le 1er du mois glisse d'un jour selon le fuseau. ⚠️ Le CSV
  serveur ne contient **que les réservations** — l'écran l'écrit sous le bouton
  au lieu de laisser croire à un export complet. ⚠️ L'écart entre « volume
  encaissable » et « lignes au grand livre » est **voulu** : les montants
  n'agrègent que les réservations non annulées.
  **F7.2.f Comptes & documents** (`features/backoffice/accounts/`) : couvre les
  modules CDC §6 *Utilisateurs* et *Documents*. Onglet **Comptes** : annuaire de
  tous les comptes (`GET /admin/users`, filtres rôle / statut / recherche) — chaque
  ligne est **cliquable** et ouvre la **fiche détaillée**
  (`accounts/detail/`, route `comptes/:id`, `GET /admin/users/{id}`) : identité,
  contact, localisation, profil / vérification, **pilotage** du compte (statut
  activer / suspendre / désactiver, **rôle**, **demande de pièce**
  `POST …/request-document` — le tout avec les garde-fous serveur reflétés), la
  liste des **pièces déposées** (KYC) et l'**historique** du compte (timeline du
  journal d'audit Spatie — exigence CDC « historique »). Depuis **F8.0**, la
  **photo du compte** (ou son logo) s'affiche en **vignette dans l'annuaire** et
  en grand dans l'en-tête de la fiche — reconnaître une personne d'un coup d'œil
  dans une liste de plusieurs centaines de lignes, et voir son visage au moment
  de contrôler sa pièce d'identité. Aucun changement backend n'a été nécessaire :
  les deux endpoints chargeaient déjà `profile`, et `ProfileResource` expose
  `avatar_url`. Repli sur l'initiale, **jamais sur une silhouette générique** :
  l'agent doit pouvoir distinguer « pas de photo » de « photo déposée ». Onglet **Documents** :
  vue transverse (`GET /admin/documents`) — compteurs par famille (KYC, documents
  de biens, certifications, preuves de reversement) puis liste normalisée paginée,
  en lecture seule.
  **F7.2.g Avis & qualité** (`features/backoffice/quality/`) : couvre le module
  CDC §6 *Avis et qualité*. Onglet **Avis à modérer** : file `GET /admin/reviews`
  (défaut `en_attente`, filtres statut + recherche) avec **publier / rejeter**
  (`PATCH /reviews/{id}/moderate`) — un avis modéré sort de la file en direct.
  Onglet **Prestataires** : liste `GET /admin/providers` (note agrégée, compteur
  d'avertissements, statut) avec panneau de **sanction** par ligne — **avertir**
  (`PATCH …/warn`, au-delà du seuil = suspension d'office) et **suspendre**
  (`PATCH …/suspend`), motif obligatoire. Les **incidents** ne sont pas dupliqués
  ici : renvoi vers l'écran **Gestion locative**, où ils se résolvent depuis la
  fiche du mandat (F7.3.a).

  **F7.2.h Team building** (`features/backoffice/team-building/`) : couvre le
  module CDC §6 *Team building*. **Liste** : file `GET /team-building-requests`
  (triée nouveau → annulé, filtres statut + recherche par référence/ville/
  entreprise) ; un clic ouvre la **fiche** `/back-office/team-building/:id`
  (`GET /team-building-requests/{id}`). La fiche a trois zones : la **demande**
  (participants, ville, période, budget, besoins, entreprise) ; le **devis pack**
  (composition ligne par ligne — brique/label/qté/PU + marge, aperçu du total en
  direct — via `POST …/quotes`, puis **envoi** à l'entreprise `PATCH
  /team-building-quotes/{id}/send`) ; et l'**affectation des prestataires**
  (exigence CDC « affectation prestataires ») : on affecte un prestataire
  **validé** (`GET /admin/providers?status=valide`) à une brique du pack via
  `POST …/assignments` → cela crée une **mission Pro** rattachée (montant,
  commission figée, cycle de mission), listée avec son statut. Les actions de
  composition/envoi/affectation sont masquées aux profils sans le rôle admin
  (garde serveur `policy manage`).

  **F7.2.i Diaspora** (`features/backoffice/diaspora/`) : couvre le module CDC §6
  *Diaspora*. **Liste** : file **priorisée** `GET /diaspora-projects` (dossiers à
  forte valeur en tête, filtres statut / priorité / recherche par référence·pays·
  client) ; un clic ouvre la **fiche** `/back-office/diaspora/:id`
  (`GET /diaspora-projects/{id}`). La fiche pilote le dossier de bout en bout : le
  **dossier** (client, type, pays de résidence, budget, agent) ; le **pilotage** —
  **priorité** en un clic (`PATCH …` sans effet de bord), **agent dédié**
  (`PATCH …/assign`, explicite via la liste des agents `GET /admin/team?role=agent_kaikun`
  ou automatique = le moins chargé), **statut** (en cours / terminé / annulé) ; et
  les **rapports de suivi** (`GET/POST …/reports`) — vérification terrain,
  avancement chantier, reporting (photo/vidéo/mixte + commentaire + lien vidéo).
  Le pilotage est réservé à l'admin ou à l'agent affecté (garde serveur `update` /
  `assign`).

  **F7.2.j Mobilité** (`features/backoffice/mobility/`, route `/back-office/mobilite`) :
  couvre le module CDC §6 *Mobilité*. Le cahier des charges range sous un même
  module deux réalités qui ne se pilotent pas pareil — d'où **deux onglets**
  plutôt qu'un tableau unique.
  - **Flotte** (`GET /admin/vehicles`, tous statuts) : les moyens de transport au
    catalogue (voitures, bus, 4x4, pirogues, mise à disposition de chauffeur).
    La colonne **Conformité** est la vraie valeur ajoutée : assurance manquante,
    chauffeur non déclaré ou pirogue sans gilets sautent aux yeux, le détail des
    manquements s'affichant en infobulle. Deux grilles distinctes, calquées sur
    le `VehicleComplianceChecker` du backend — **motorisé** (assurance + identité
    du chauffeur) vs **pirogue** (gilets + aptitude météo + agrément). Filtres :
    catégorie, statut, **avec / sans chauffeur**, recherche.
  - **Trajets programmés** (`GET /admin/mobility-services`, tous statuts) : les
    départs datés réservés à la place, avec le **remplissage** de chacun — jauge
    places prises / restantes et mention « Complet » (les « disponibilités » du
    cahier) — plus le véhicule affecté et le prestataire opérateur. Filtres :
    nature, statut, **période de départ**, recherche.

  Écran en **lecture seule**, comme Catalogues (F7.2.b) et Dossiers (F7.2.e) : la
  décision d'approbation reste concentrée dans l'écran **Validation** (F7.2.a),
  point unique de décision. Celui-ci sert à *repérer* les anomalies.

  **F7.2.k Tourisme** (`features/backoffice/tourism/`, route `/back-office/tourisme`) :
  couvre le module CDC §6 *Tourisme* (« circuits, destinations, programmes,
  guides, restaurants, capacités groupes »). Ces six éléments ne vivent pas au
  même endroit dans le modèle de données — d'où **trois onglets**.
  - **Circuits** (`GET /admin/experiences`, tous statuts) : la **capacité
    groupe** en jauge places prises / restantes, et le **programme** rendu par
    les *inclusions* du circuit (Restauration, Guide, Transport…) en étiquettes.
    ⚠️ Un circuit n'a **pas de date de départ** : la capacité est un total et le
    remplissage cumule toutes ses réservations — à ne pas confondre avec le
    remplissage d'un départ daté de l'écran Mobilité.
  - **Destinations** (`GET /admin/tourism/destinations`) : vue **agrégée** —
    nombre de circuits, publiés vs en attente, capacité cumulée, fourchette de
    prix. Répond à la question de couverture : quelles destinations sont
    servies, lesquelles n'ont que des circuits en attente. Un bouton
    « Voir les circuits » bascule sur l'onglet Circuits filtré sur cette
    destination — l'onglet est actionnable, pas seulement informatif.
  - **Guides & restaurants** (`GET /admin/providers?category=guide,restauration`)
    : ⚠️ ce ne sont **pas** des entités du catalogue touristique. La plateforme
    ne les connaît que comme **catégories de prestataires** de la marketplace Pro
    et comme drapeaux d'inclusion d'un circuit ; **aucun guide nommé n'est
    rattaché à un circuit précis**. L'écart est signalé dans l'écran par un
    encart, pas masqué. Les sanctions restent dans **Avis & qualité** (F7.2.g).

  Lecture seule, comme les écrans précédents.

  **F7.2.l Paramètres & contenu** (`features/backoffice/settings/`, route
  `/back-office/parametres`) : couvre le module CDC §6 *Paramètres* (« villes,
  catégories, tarifs, commissions, pages, FAQ, notifications ») — **le dernier
  des 14 modules**. Quatre onglets, parce que ces sept fonctions ne se pilotent
  pas de la même façon.
  - **Réglages** (`GET`/`PATCH /admin/settings`) : commissions & marges,
    coordonnées publiques, et le **barème du simulateur de construction** (les
    « tarifs »). Ce barème est un objet **imbriqué** : il est aplati en chemins
    (`price_m2.extension`) et rendu champ par champ, sans que sa structure soit
    codée en dur — une rubrique ajoutée côté serveur apparaît toute seule.
    ⚠️ L'enregistrement n'envoie **que les clés modifiées** : tout envoyer
    transformerait chaque valeur par défaut en surcharge en base, et un futur
    ajustement du code n'aurait plus d'effet. Les réglages non surchargés portent
    une étiquette « défaut ». Y figurent aussi les **réseaux sociaux**, rendus
    par le **pied de page public** (`app-footer` lit `GET /contact-info`) : rien
    n'est codé en dur dans le frontend, un réseau laissé vide n'apparaît pas.
  - **Notifications** : deux interrupteurs de canal (SMS — facturé à l'envoi —,
    e-mail) et un interrupteur **par événement**, groupés par destinataire
    (clients & partenaires / équipe). Réellement branché : le serveur arbitre les
    `via()` des notifications. ⚠️ Un encart rappelle que les **codes de sécurité
    et la 2FA** ne sont pas concernés et partent toujours.
  - **Contenu** : CRUD complet des **pages** éditoriales (`/admin/pages`) et de
    la **FAQ** (`/admin/faqs`), publiées ou masquées. ⚠️ Les pages sont résolues
    **par slug** côté serveur : un renommage adresse toujours l'ancien slug.
  - **Référentiels** : les **villes** — arbre région → département, puis les
    communes du département choisi, avec création / renommage / suppression. La
    colonne *Rattachements* affiche l'usage réel (biens, comptes) et le bouton
    Supprimer est **grisé** quand la commune est utilisée ; côté serveur la
    suppression renvoie alors **409** avec le décompte. Et les **catégories**, en
    **lecture seule** : ce sont des enums qui portent la logique métier — un
    encart l'explique plutôt que de laisser croire à un écran incomplet.
  **F7.4.a Cloisonnement du rail par permission** (`features/backoffice/backoffice-permissions.ts`,
  `core/guards/permission.guard.ts`) : jusque-là un seul `roleGuard` « staff »
  gardait la racine `/back-office` et les **16 rubriques s'affichaient pour tout
  le monde** — c'est le serveur qui refusait à l'ouverture. Sûr, mais contraire
  au CDC §7 qui promet à l'agent un « accès financier limité » : il voyait
  Paiements, Export comptable et Permissions dans son menu pour s'y heurter à un
  403. Le rail (`visibleNav()`) ne montre plus que les portes qui s'ouvrent, et
  chaque route porte le `permissionGuard` correspondant.
  - **Une seule table de correspondance**, partagée par le rail ET les routes.
    C'est le point à ne pas casser : deux listes qui divergent produisent soit un
    lien invisible mais atteignable à l'URL, soit un lien cliquable qui rebondit.
  - **Les écrans de supervision en lecture seule restent ouverts à toute
    l'équipe** (Catalogues, Mobilité, Tourisme) : voir sans pouvoir agir est
    précisément le métier d'un agent qui prépare un dossier. **Diaspora** aussi,
    sa fiche étant gardée côté serveur par « l'agent AFFECTÉ ou un admin » —
    exiger une permission ici fermerait la porte à celui qui doit entrer.
  - Un refus **renvoie à la Vue d'ensemble avec un message** (`?acces=refuse`)
    plutôt qu'en silence : une URL en favori qui rebondit sans un mot se lit
    comme un bug, pas comme un droit manquant.
  - ⚠️ **Confort d'interface, PAS la sécurité** : les `can:` des routes
    `/admin/…` sont inchangés et restent la vérité. Le tableau `permissions` du
    compte connecté (nouveau dans `UserResource`) n'est renseigné que pour
    l'équipe et que sur son propre compte.
  **F7.4.d Ordre du rail aligné sur le CDC §6.** Le menu suivait l'ordre de
  **construction** des tranches (F7.2.a, .b, .c…), qui n'avait de sens que pour
  nous : l'équipe n'y retrouvait pas la liste du cahier des charges, et une
  recette module par module obligeait à sauter d'un bout à l'autre du menu. Il
  déroule désormais les **14 modules dans l'ordre du tableau « Module admin »**,
  puis les trois rubriques du **§7** (Équipe, Permissions, Pointeuse), qui
  relèvent du poste de commandement et non des modules métier. Deux
  correspondances ne sont pas une ligne pour une : *Utilisateurs* (2) et
  *Documents* (12) partagent la rubrique **Comptes** — les documents sont
  indexés par compte, les séparer obligerait à chercher deux fois — et *Biens
  immobiliers* (3) est servi par **Catalogues**, sa fonction « valider » étant
  portée par la file transverse **Validation**. Cette dernière reste **en tête**
  malgré son absence du §6 : c'est le premier écran ouvert chaque matin et la
  fonction « valider » de quatre modules à la fois. ⚠️ Toute nouvelle rubrique
  se range **à la place de son module**, pas en fin de liste : le client déroule
  ce menu en face du §6 de son cahier.
  **F8.1 Barre horizontale réellement fixe.** Le shell est calé sur la hauteur du
  viewport (`height: 100dvh` + `overflow: hidden` sur `.bo-app`) et **seule**
  `.bo-main` défile. Auparavant le `min-height: 100vh` laissait la colonne
  grandir avec le contenu : c'est le document entier qui défilait, emportant
  l'en-tête. Le piège à connaître si l'on retouche cette grille est le
  `min-height: auto` par défaut des enfants flex — sans `min-height: 0` sur
  `.bo-body`, la zone de contenu refuse de créer son propre ascenseur.
  **F8.1 Les listes deviennent des écrans de triage.** Gestion locative et
  Construction affichaient **10 colonnes**, Diaspora et Team-building 9, Comptes
  8 — dans 1160 px de large, avec un `min-width: 960px` sur la table : défilement
  horizontal permanent et libellés cassés sur trois lignes. Or **chaque ligne
  ouvre une fiche** : une liste sert à *reconnaître et prioriser*, pas à tout
  afficher. Ramenées à 4–5 colonnes (`table-layout: fixed` + largeurs en %), avec
  les données regroupées par sens dans des cellules empilées — la référence en
  mono au-dessus du libellé, la localité sous le nom d'une personne plutôt qu'en
  colonne à comparer — et un « Voir → » qui s'allume au survol.
  - ⚠️ **Vérifier la fiche avant de couper une colonne.** Chaque champ retiré
    (dépenses, reversements, coût estimé, vérification d'un compte…) a été
    contrôlé présent sur la fiche correspondante. Une colonne supprimée sans ce
    contrôle est une donnée devenue inatteignable.
  - Les styles `ds-` / `tb-` / `ac-` sont des **quasi-copies** d'un écran à
    l'autre : la même correction a dû être appliquée cinq fois. Un partiel SCSS
    commun éviterait la dérive (chantier non engagé).
  **F8.1 Revue des médias avant publication** (`features/backoffice/shared/media-review/`).
  Un agent validait une annonce **sans jamais voir ses photos**. Le composant
  `app-media-review` sert les trois écrans : bande de vignettes **compacte** dans
  la file, galerie complète **avec modération** sur le dossier
  (`/back-office/validation/:type/:id`), et lien depuis la colonne *Médias* du
  catalogue. Visionneuse plein écran au clavier (←/→, Échap).
  - Un média **masqué reste affiché**, grisé et étiqueté : le cacher aussi à
    l'agent l'empêcherait de le rétablir.
  - `canModerate` est à **faux par défaut** — l'API refuse sans la permission du
    type parent, autant ne pas proposer un geste qui rebondira.
  - La colonne *Médias* du catalogue signale **en rouge** une annonce publiée
    sans aucun visuel : c'est une anomalie vue par les clients, pas un détail.
  **F8.2 Les cinq derniers écrans reçoivent leurs fiches.** Nuitées, Mobilité,
  Tourisme, Paiements et Avis & qualité n'étaient que des listes : la décision se
  prenait sur une ligne de tableau, qui ne peut porter ni contexte, ni preuve, ni
  liste de personnes. Sept pages `:id` s'ajoutent — séjour, véhicule, départ
  programmé, circuit, prestataire, règlement, avis — et les listes retombent à
  4–5 colonnes (Nuitées 7→4, Mobilité 8→4 par onglet, Circuits 8→4, Paiements
  9→5, Avis 7→5), avec la même règle qu'en F8.1 : **rien n'est coupé sans avoir
  été vérifié présent sur la fiche**.
  - **Une seule feuille de style pour toutes les fiches**
    ([`shared/dossier.scss`](src/app/features/backoffice/shared/dossier.scss),
    préfixe `bo-`). C'est la dette signalée en F8.1 (« les styles `ds-`/`tb-`/`ac-`
    sont des quasi-copies ») traitée avant qu'elle ne se reproduise : deux
    feuilles jumelles étaient déjà nées (mobilité, tourisme) et une troisième
    s'annonçait. Une fiche en appelle une autre — d'un paiement vers sa
    réservation, d'un avis vers son prestataire — elles doivent se ressembler,
    sinon l'agent croit avoir changé d'application.
  - **Ce qui se décide où.** Le pilotage fin quitte les listes pour les fiches,
    avec leur contexte : le ménage et la caution d'un séjour, le **remboursement**
    d'un règlement. Restent en liste les gestes du quotidien qui ne demandent
    aucune information supplémentaire : arrivée/départ d'un séjour, confirmation
    d'un règlement Wave/OM. Un remboursement est irréversible : il ne se décide
    pas depuis un tableau sans voir la réservation.
  - **Le serveur décide de ce qui est possible.** La fiche paiement affiche ses
    boutons d'après `can_confirm` / `can_refund` renvoyés par l'API, au lieu de
    redéclarer les règles du module de paiement (mode manuel requis, statut
    `complete` requis) et de finir par en diverger.
  - **Les règles métier partagées entre liste et fiche sont extraites**, sans quoi
    les deux écrans divergent : la grille de conformité d'un véhicule
    ([`mobility/vehicle-compliance.ts`](src/app/features/backoffice/mobility/vehicle-compliance.ts),
    pirogue vs motorisé) et la lecture du programme d'un circuit
    ([`tourism/circuit-programme.ts`](src/app/features/backoffice/tourism/circuit-programme.ts)).
    ⚠️ Cette dernière a évité un bug réel : `inclusions` est un **objet
    `{clé: booléen}`**, pas un tableau — une fiche qui le lisait comme un tableau
    aurait affiché « programme non renseigné » sur tous les circuits.
  - **La fiche prestataire n'appartient à aucun écran** : ouverte depuis Tourisme
    (« Guides & restaurants ») *et* depuis Avis & qualité, elle vit dans
    [`providers/`](src/app/features/backoffice/providers/) sous la route de premier
    niveau `/back-office/prestataire/:id`, et son retour passe par
    `app-back-link` (historique) — un lien en dur renverrait la moitié des agents
    sur le mauvais écran.
  - ⚠️ **La garde de route porte la permission qu'exige le SERVEUR**, pas celle de
    l'écran d'où l'on vient : la fiche prestataire est gardée par `qualite`
    (l'API impose `valider:prestataire`), pas par `tourisme`. Un agent qui voit la
    liste sans pouvoir ouvrir les dossiers lit un message qui le lui dit, au lieu
    de croire à une donnée disparue.
  **F8.3 Les fiches anciennes cessent d'être des piles.** F8.1 et F8.2 ont traité
  les écrans **trop pauvres** (une ligne de tableau pour décider). Restait le
  défaut inverse, sur les cinq fiches livrées avant : **chantier** (585 l. de
  gabarit), **mandat**, **compte**, **team-building**, **diaspora**. Elles avaient
  toute l'information — empilée à plat, cinq à sept cartes de même poids visuel,
  dont des tableaux de douze lignes. Sur un mandat, un incident critique ouvert et
  un reversement jamais exécuté se lisaient au même niveau que les clauses du
  contrat, six écrans plus bas. L'agent avait tout sous les yeux et ne voyait rien.
  Trois briques, et **aucune donnée ajoutée** — uniquement remontée :
  - **Le bandeau « ce qui appelle une décision »**
    ([`shared/fiche-signals/`](src/app/features/backoffice/shared/fiche-signals/)) :
    les signaux déjà présents dans la page, mais dispersés, remontés en tête avec
    un bouton qui **conduit à la section qui les explique** (et déplie le volet au
    passage). `alerte` = bloqué ou part de travers, `vigilance` = à suivre.
    Chaque fiche calcule ses propres signaux ; le composant ne fait que présenter.
    - ⚠️ **Sans signal, aucun bandeau.** Un encadré « rien à signaler » sur un
      dossier sain deviendrait du bruit qu'on apprend à ignorer — et avec lui, les
      vrais signaux. C'est aussi pourquoi aucun signal n'est allumé en permanence :
      le statut d'une pièce KYC ne bougeant jamais côté serveur, la fiche compte
      alerte sur *pièces déposées + profil non vérifié*, pas sur « pièce en attente ».
  - **La bande de chiffres clés** (`.bo-keys`) : ces montants ne se lisent jamais
    seuls, c'est leur **rapport** qui décide. Un chantier vendu 12 M et engagé à
    13 M perd de l'argent — information qu'aucune des deux cartes distantes ne
    donnait, chacune ayant raison de son côté.
  - **Les volets repliables** (`.bo-fold`, `<details>` natif — ouverture au
    clavier, zéro JavaScript) pour l'archive : devis, prestataires, comptes rendus,
    pièces, historique. **Replier n'est pas cacher** : le résumé du volet porte ce
    qu'on venait y chercher (combien, pour quel montant, depuis quand), et un volet
    se déplie **d'office quand un geste y est en attente** (devis en brouillon,
    impayé échu, brique de pack sans prestataire, dossier diaspora sans rapport).
  - **L'ordre de lecture suit l'ordre de décision** : sur les fiches compte et
    diaspora, le bloc de pilotage passe **devant** l'identité du client — on
    n'ouvre pas ces fiches pour relire une adresse.
  - Les deux SCSS communs vivent dans
    [`shared/fiche-blocks.scss`](src/app/features/backoffice/shared/fiche-blocks.scss),
    ajouté aux `styleUrls` des cinq fiches (Angular l'encapsule pour chacune).
    Le bandeau, lui, est un vrai composant : il porte du comportement.
    ⚠️ Cette factorisation a été faite **après la deuxième fiche**, pas après la
    cinquième — la dette de quasi-copies signalée en F8.1 s'était déjà rouverte.
  - Au passage, deux mensonges d'écran corrigés : la fiche chantier annonçait
    l'affectation de prestataires BTP comme « reste à livrer » alors que la section
    la livrait juste en dessous ; la fiche compte affichait le code brut de l'enum
    de vérification (« non_verifie »), lisible par un développeur, pas par un agent.

  **F8.3 Le contenu éditorial s'écrit sans taper de balises.**
  Le corps d'une page (`/pages/:slug`) est stocké en HTML et rendu via
  `[innerHTML]` ; le back-office n'offrait qu'un `<textarea>`. Pour écrire les
  mentions légales, un agent devait taper `<h2>`, `<p>`, `<ul><li>` à la main —
  ou tout obtenir en un seul bloc sur le site public.
  [`shared/components/rich-text-editor/`](src/app/shared/components/rich-text-editor/)
  remplace la saisie de balises par des boutons, **sans changer le format
  stocké** : les pages déjà en base s'ouvrent sans conversion et le rendu public
  n'a pas bougé.
  - **Aucune dépendance** : `contenteditable` + `execCommand` (vieux mais
    universels) plutôt que ~150 Ko au premier chargement du site pour six boutons.
    Implémente `ControlValueAccessor` → s'utilise avec `[(ngModel)]` comme un
    champ ordinaire.
  - **Assainissement à la source, par liste blanche**
    ([`rich-text.sanitizer.ts`](src/app/shared/components/rich-text-editor/rich-text.sanitizer.ts),
    15 tests) : le collé depuis Word est nettoyé **à l'entrée**, pas seulement au
    rendu. Ce qui n'est pas dans la liste est **déballé** (on garde le texte, on
    jette la balise) — on ne perd jamais la rédaction de l'agent. L'assainisseur
    est **idempotent** : une page rouverte dix fois reste identique.
  - La vue « code HTML » **reste accessible** : elle ne sert plus à écrire, mais
    la retirer aurait été une régression pour qui sait s'en servir.
  - ⚠️ **La FAQ garde son `<textarea>`**, volontairement : la page publique rend
    la réponse en `{{ answer }}`, pas en HTML. Y mettre l'éditeur riche ferait
    apparaître les balises en clair sur le site. (Les retours à la ligne saisis
    sont désormais conservés, via `white-space: pre-line`.)

  **F8.9 La file de traitement des demandes, qui n'existait pas.**
  ([`features/backoffice/requests/`](src/app/features/backoffice/requests/),
  rubrique **Demandes**, permission `traiter:demandes`.)
  Depuis B11.2, toute demande déposée depuis le site (« Demander une
  réservation », formulaires métier) déclenchait une alerte interne — *Nouvelle
  demande à traiter*, avec un bouton **« Ouvrir la file de traitement »**. Cette
  file n'existait nulle part : le bouton menait à la Vue d'ensemble, où le
  dossier était **compté** et jamais listé. L'équipe recevait donc l'e-mail d'une
  demande qu'elle n'avait aucun moyen de retrouver, alors que le CDC §7 confie
  explicitement le « traitement demandes » à l'agent Kaikun. Écart repéré à la
  vérification physique, pas à l'audit F7 — qui balayait les 14 modules du
  CDC §6, où les demandes génériques ne forment pas un module.
  - **La liste** : urgences d'abord puis les plus anciennes (tri **serveur** —
    dans une file, le dossier qui attend depuis le plus longtemps coûte plus
    cher que le dernier arrivé), le **demandeur joignable dès la ligne**
    (`tel:` / `mailto:` cliquables, sans ouvrir la fiche), l'ancienneté en clair
    (« il y a 6 jours ») plutôt qu'une date à comparer mentalement, et
    l'extrait du message. Filtres service / statut / priorité + recherche
    (référence, ville, nom, e-mail, téléphone).
  - **La fiche** (`demandes/:id`) : bandeau de signaux (reçue depuis N jours et
    jamais prise en charge, stade devis sans aucun devis, compte du demandeur
    supprimé), le demandeur, le message **cité tel quel**, les devis, et
    l'historique des décisions (journal d'audit).
  - **Les boutons d'étape viennent du serveur** (`allowed_transitions`) et
    l'action passe par la route historique `PATCH /requests/{id}/status` :
    rejouer la machine à états côté client la ferait diverger au premier statut
    ajouté, et proposer une étape refusée en 422 serait un faux espoir.
  - Le compteur « Demandes reçues » de la Vue d'ensemble **mène enfin à la
    file**, et le bouton de l'e-mail interne pointe vers `/back-office/demandes`.
  - **Les référentiels de filtrage sont servis par l'API** (les enums PHP), pas
    recopiés dans le composant : c'est ce qui les garde d'accord.

  **F8.10 Le client peut enfin réserver — les quatre univers.**
  Le geste « réserver » ne produisait **aucune réservation** : les quatre fiches
  publiques (nuitée, véhicule, circuit, trajet) envoyaient toutes un
  `POST /requests`, c'est-à-dire un **prospect**. Le visiteur croyait avoir
  réservé, puis « Mes réservations » lui répondait *« Aucune réservation :
  parcourez nos univers pour réserver »* — en le renvoyant vers le bouton qui
  venait de lui créer une demande. Les quatre endpoints de réservation
  existaient depuis B3.3/B6/B7 et **n'avaient jamais eu d'appelant** ; toutes
  les réservations de la base venaient du seeder.
  - **Chaque univers a sa forme**, ce n'est pas un écran copié quatre fois :
    une nuitée se réserve sur une **période** (départ exclu, c'est ce qui fait
    les nuits) ; un véhicule sur des **journées** (bornes incluses, un seul jour
    est permis) ; un circuit sur une **date de départ** seule — il n'a pas de
    date de fin, sa durée lui appartient ; un trajet, déjà daté, ne se réserve
    qu'en **nombre de places**.
  - **Le devis se compose sous les yeux du client** pendant qu'il choisit, et la
    **caution est annoncée à part, en retrait** : c'est un dépôt rendu, pas un
    prix, et l'additionner mentalement au séjour ferait fuir.
  - **Les règles sont dites avant le clic** (séjour minimum, capacité, places
    restantes) plutôt que renvoyées en 422 après. Le serveur reste seul juge —
    deux clients peuvent viser la dernière place au même instant — et ses
    messages, déjà écrits pour un client (« Il ne reste que 3 place(s)
    disponible(s). »), sont affichés tels quels.
  - **Le succès n'affiche pas un message : il emmène payer**
    (`/mon-espace/reservations/:id/paiement`). Une réservation en attente de
    règlement laissée sans indication ne se solderait jamais.
  - **La fiche d'un trajet a dû être créée de toutes pièces**
    ([`features/mobility/trip-detail/`](src/app/features/mobility/trip-detail/),
    route `/mobilite/:id`) : l'univers Mobilité s'arrêtait au catalogue, ses
    cartes ne menaient nulle part, et le code assumait que « la réservation d'un
    trajet se fait via un conseiller ». Il a fallu **créer aussi l'endpoint
    serveur** `GET /mobility-services/{id}`, qui n'existait pas.
  - Les commentaires de tête des trois autres fiches annonçaient encore que
    « la réservation ferme relève des phases ultérieures ». Ces phases n'étaient
    jamais venues ; les commentaires disent maintenant ce que le code fait.

  **F8.11 Le devis sur-mesure va enfin jusqu'au paiement.**
  Le pendant de F8.10 pour ce qui ne se réserve pas au catalogue (construction,
  gestion locative, diaspora, team building). Trois maillons manquaient, chacun
  invisible depuis les deux autres :
  - **L'agent ne pouvait pas chiffrer.** `POST /requests/{id}/quotes` existait
    depuis B11.3 **sans aucun appelant** : la file de traitement (F8.9) savait
    lister les demandes et les faire avancer jusqu'au stade « devis », mais rien
    n'en produisait. Le client, lui, savait déjà répondre — il pouvait accepter
    ce que personne ne pouvait émettre, et tous les devis venaient du seeder.
    Le formulaire vit maintenant dans le volet « Devis proposés » de
    [`features/backoffice/requests/detail/`](src/app/features/backoffice/requests/detail/),
    replié derrière un bouton : chiffrer part **immédiatement au client**, ce
    n'est pas un champ qu'on remplit en passant. Les postes se saisissent
    **ligne à ligne, jamais en JSON** — le client les lit tels quels, c'est ce
    qui rend un montant discutable plutôt qu'à prendre ou à laisser.
  - **Accepter ne menait nulle part.** L'écran annonçait « un conseiller
    poursuit votre dossier (réservation et paiement) » : personne ne le
    faisait, et aucune réservation n'existait pour être réglée. `/devis/:id`
    propose désormais **« Régler ma prestation »** vers la page de paiement de
    F8.6, y compris au rechargement (`booking_id` est servi par l'API).
  - **Le devis n'avait pas de visage.** ⚠️ **C'est une décision produit, pas un
    ornement** : sur du sur-mesure, le client n'achète pas un article de
    catalogue, il accorde sa confiance à quelqu'un. L'agent qui a chiffré est
    affiché **en tête, avant la zone de décision** (nom, téléphone cliquable,
    « une ligne vous semble discutable ? appelez avant d'accepter »), et il
    reparaît après l'accord à côté du bouton de règlement. Le paiement est
    **proposé, jamais imposé** — aucune redirection automatique vers PayTech :
    pousser un client vers un formulaire de carte dans la seconde qui suit son
    accord est le meilleur moyen de le faire reculer.

  **F8.12 La messagerie interne cesse d'être un décor.**
  Le socle des conversations date de F3.7 : liste des fils, bulles, composeur,
  non-lus, notifications. Il **savait tout faire sauf commencer** —
  `startConversation()` était écrit dans
  [`core/api/message.service.ts`](src/app/core/api/message.service.ts) et
  **aucun écran ne l'appelait**. Tous les fils visibles venaient du seeder, et
  l'état vide de « Mes messages » promettait un bouton (« une conversation
  s'ouvre lorsque vous contactez le support depuis une annonce ou une demande »)
  qui n'existait nulle part.
  - **Le geste, enfin** :
    [`shared/components/contact-support/`](src/app/shared/components/contact-support/),
    posé sur « Mes messages » (état vide + barre d'actions), sur la **fiche
    d'une demande** et sur la **fiche d'une réservation**. Replié c'est un
    bouton ; déplié, un vrai espace d'écriture. ⚠️ **Aucun destinataire n'est
    demandé** : le client n'écrit jamais directement au propriétaire ou au
    prestataire, le serveur lui assigne un agent — l'architecture « support
    pivot ». Sur une fiche, le **dossier est joint au message** (slug + id),
    ce qui évite à l'agent de commencer par « de quoi parlez-vous ? ».
  - **Un interlocuteur nommé** : le fil affiche « Avec Awa Diop, support
    Kaikun » et non un « support » anonyme — même arbitrage produit qu'en F8.11
    sur le devis. Un fil clôturé par l'équipe reste ouvert à l'écriture, et le
    bandeau dit que **écrire le rouvre**.
  - **Côté équipe**, une rubrique **Messages** au rail
    ([`features/backoffice/messages/`](src/app/features/backoffice/messages/)) :
    sans elle, le client écrivait à personne. La file s'ouvre sur **mes fils
    ouverts** (une boîte partagée où tout le monde regarde tout est une boîte
    que personne ne traite), signale **« en attente de réponse »** plutôt que
    « non lu » — un fil dont le dernier message vient du client n'est pas lu, il
    est **dû** —, rend le client joignable dès la ligne (`tel:`/`mailto:`, comme
    la file des demandes) et garde « Non assignées » à un clic. Sur la fiche, le
    composeur annonce les deux effets de bord du serveur : répondre à un fil
    sans responsable **le prend en charge**, répondre à un fil clos **le rouvre**.
  - **F8.15.c — le courrier de la page Contact y a rejoint les conversations.**
    `GET`/`PATCH /admin/contact-messages` existaient depuis F2.8.1 et n'avaient
    **aucun appelant** : la page Contact — l'un des canaux de conversion
    prioritaires du cahier des charges — écrivait en base et **personne ne lisait
    jamais**. Un prospect pouvait attendre indéfiniment. C'est un **onglet** du
    même écran, et non une quatrième portée de la file : un message de contact
    n'est pas une conversation (auteur le plus souvent **sans compte**, pas de
    fil, pas de réponse dans l'application), les fondre ferait chercher un bouton
    « Répondre » qui ne peut pas exister. La vue s'ouvre sur **« à traiter »**, le
    message est affiché **en entier** (aucune fiche à ouvrir derrière), l'e-mail
    est cliquable, et « Marquer traité » enregistre **qui** a traité — sans quoi
    deux agents rappellent le même prospect. Le compteur de l'onglet est servi
    **hors filtre** (`meta.pending`) : regarder les messages traités ne doit pas
    faire disparaître la charge restante.
  - **F8.12.a — les écrans se tiennent à jour seuls** (défaut relevé à la
    vérification : il fallait recharger pour voir la réponse d'en face).
    [`core/state/poll-while-visible.ts`](src/app/core/state/poll-while-visible.ts)
    fait battre les deux fils toutes les **10 s** et les deux listes toutes les
    **30 s**, en ne redemandant que les messages postérieurs au dernier affiché
    (`?after=`). ⚠️ **Pas de WebSocket, arbitrage assumé** : un démon permanent
    (Reverb/Pusher) à surveiller et exposer coûterait plus cher que le service
    rendu sur un canal où l'on écrit une phrase toutes les deux minutes ; les
    écrans rechargent, peu importe qui les réveille, donc rien à réécrire le jour
    où un vrai canal poussé arrive. La relève est **silencieuse** (pas d'état de
    chargement, pas d'écran d'erreur, pas de défilement forcé — une coupure
    passagère ne doit pas remplacer une conversation lisible), **ne tourne pas en
    SSR** ni **onglet caché**, et **bat immédiatement au retour sur l'onglet**.
  - **F8.12.b — l'écran Permissions dit ce qu'il ne montre pas** : `repondre:messages`
    est portée par le rôle (comme l'accès au back-office) et n'apparaît donc plus
    dans la matrice. Sans la phrase ajoutée en tête d'écran, un administrateur
    chercherait en vain la case « messages » et croirait ses agents privés de la
    messagerie.
  - **F8.12.c — le tiers entre dans le fil.** Sur la fiche back-office, un
    panneau « + Ajouter au fil » propose **la personne du dossier** en un clic,
    puis une recherche limitée aux propriétaires et prestataires ; les
    participants sont affichés en pastilles, chacun retirable. ⚠️ L'écran
    **avertit avant le clic** que le tiers verra tout l'historique — c'est la
    seule chose que l'agent doit peser. Côté client, le fil nomme désormais
    chaque participant **par son rôle** (« Ousmane Ba, Propriétaire ») et
    explique en une phrase pourquoi un numéro tapé dans un message ressort en
    « ••• » : sans cette phrase, on croirait à un bug et on recommencerait.
  - ⚠️ **Ce qui reste hors périmètre** : le tiers n'a pas d'écran dédié — il lit
    et répond depuis la messagerie de SON espace (propriétaire ou prestataire),
    qui monte déjà le même composant de fil. Aucun tableau de bord « mes
    conversations de professionnel » n'est prévu tant que le volume ne le
    justifie pas.

  **F8.13 L'état transverse : deux pastilles qui n'existaient pas, et un
  formulaire qu'on cachait.**
  Le cahier des charges demandait un état partagé pour quatre choses. Deux
  étaient déjà en place et il aurait été absurde de les refaire : **l'utilisateur
  connecté** (`AuthService`, plus `favorite-store.ts` pour les cœurs) et **les
  filtres de recherche**, branchés sur l'URL au correctif `34fbe37` — l'URL *est*
  leur état partagé, et c'est le bon endroit : un filtre qu'on ne peut pas
  envoyer par lien n'est pas partagé. Les deux autres manquaient vraiment.
  - **Les compteurs de non-lus**
    ([`core/state/unread-store.ts`](src/app/core/state/unread-store.ts)) étaient
    dans deux états opposés, tous deux mauvais. Celui des **notifications** était
    compté dans l'en-tête lui-même et ne bougeait qu'à la navigation : une
    notification arrivée pendant qu'on lit une page ne se signalait qu'au clic
    suivant, et l'écran « Notifications » — celui qui vide la liste — ne pouvait
    pas éteindre la pastille qu'il venait de vider. Celui des **messages**
    n'existait **nulle part** : `MessageService.unreadCount()` était écrit depuis
    F3.7 sans un seul appelant, et la rubrique « Messages » du rail des quatre
    espaces restait muette. ⚠️ **C'est le motif exact qui avait fait rater la
    messagerie à l'inventaire des orphelins** (une méthode de service que personne
    n'appelle est invisible d'une comparaison route↔URL) : la seconde passe
    promise a été faite **au niveau des méthodes**, et n'a trouvé que deux cas —
    celui-ci et `AdminService.teamBuildingAssignments()`. Une source unique
    réveillée par la session, la navigation et une relève d'une minute alimente
    maintenant la cloche **et** les rails, **back-office compris** : l'agent voit
    arriver un message client sans ouvrir sa boîte. Les écrans qui font *baisser*
    un compteur le poussent (`setNotifications` / `setMessages`) plutôt que
    d'attendre le réveil suivant, les endpoints renvoyant déjà le nouveau total ;
    ouvrir un fil, dont l'endpoint ne le renvoie pas, redemande le total. ⚠️ Une
    coupure réseau **ne remet jamais un compteur à zéro** : ce serait faire
    disparaître un non-lu réel.
  - **Le panier de réservation en cours**
    ([`core/state/booking-intent-store.ts`](src/app/core/state/booking-intent-store.ts)).
    Les quatre fiches réservables **masquaient leur formulaire** au visiteur non
    connecté et affichaient à la place un bouton « Se connecter » : il fallait
    créer un compte pour découvrir un prix — le mur arrivait avant l'envie. Le
    formulaire est désormais ouvert à tous ; c'est le bouton « Réserver » qui
    conduit à la connexion (« Se connecter pour réserver »), et la saisie attend
    le retour. ⚠️ **`sessionStorage`, pas un signal** : la connexion Google (F8.7)
    fait **sortir de l'application** et tout l'état en mémoire disparaît ; et à la
    différence de `localStorage`, la session meurt avec l'onglet — la saisie d'un
    visiteur ne traîne pas derrière lui sur une machine partagée. Le panier se
    rend **à la seule fiche concernée** (la nuitée 12 n'est pas le véhicule 12),
    **une seule fois** (il se consomme : revenir des semaines plus tard ne doit
    pas ressusciter des dates oubliées) et **périme au bout d'une heure**. Le
    store ne connaît pas la forme des univers — période pour une nuitée, journées
    pour un véhicule, date de départ seule pour un circuit, places seules pour un
    trajet : il transporte, la fiche interprète.
  - ⚠️ **Ce que la reprise ne fait PAS** : elle ne réserve pas toute seule au
    retour de connexion. Le formulaire est rempli, le client relit et clique —
    arbitrage explicite : on ne l'engage pas dans un paiement pendant qu'il
    regardait ailleurs.
  - **Ménage** : `MessageService.startConversation()` est **retirée** (aucun
    appelant ; depuis F8.12 la route est réservée à l'équipe, qui répond aux fils
    et y fait entrer un tiers mais n'en ouvre pas). La route serveur existe
    toujours — la rebrancher est le seul travail à refaire le jour où un agent
    devra écrire le premier.

  **F8.14 L'entreprise n'avait nulle part où payer (et le chantier non plus).**
  Question de vérification : « pour PayTech, tout est couvert pour le client et
  pour l'entreprise ? » Non — et le blocage était triple. Le premier est côté
  serveur (trois familles de devis, une seule reliée au paiement : voir le README
  du backend). Les deux autres sont ici.
  - **L'écran de règlement n'existait que dans l'espace client.**
    `/mon-espace/reservations/:id/paiement` est gardé par `roleGuard` avec
    `roles: ['client']` ; un compte entreprise ne porte que le rôle `entreprise`.
    Même si une réservation avait existé, le lien l'aurait menée à un mur. Les
    **trois** écrans de réservation (liste, détail, règlement) n'écrivent plus
    `/mon-espace` en dur : ils lisent `SPACE_CONFIG`, comme les messages, les
    notifications et le profil depuis F4. Ils sont montés tels quels dans
    `/espace-entreprise`, avec une rubrique **Réservations** au rail.
  - ⚠️ **Un état vide n'est pas transposable d'un espace à l'autre.** « Aucune
    réservation → parcourez le catalogue » n'a aucun sens pour une entreprise :
    son séminaire ne s'achète pas sur étagère, il se demande puis se chiffre.
    D'où le champ `bookingsEmpty` de `SpaceConfig` (`catalogue` | `devis`) : le
    composant reste unique, l'issue proposée suit l'espace.
  - **Le geste principal est sur la demande, pas dans une liste.** Sur la fiche
    d'une demande d'entreprise, le devis accepté affichait « ✓ Devis accepté —
    Kaikun coordonne l'organisation de votre événement » et rien d'autre : une
    phrase rassurante au bout d'un parcours qui s'arrêtait là. Il montre
    maintenant ce qui reste dû et deux boutons (« Régler », « Voir la
    réservation »). Même correction sur le bloc client des devis de chantier
    (`shared/components/construction-quotes/`, F3.9), dont le texte promettait
    que « notre équipe lance le chantier ».
  - ⚠️ **Le montant exigible devait survivre à un rechargement** : les deux
    ressources de devis exposent désormais leur `booking`. Sans cela, le bouton
    « Régler » n'aurait existé qu'au retour immédiat du clic d'acceptation.
  - **Proposé, jamais imposé** — même arbitrage qu'en F8.11 : aucune redirection
    automatique vers le paiement après l'acceptation. On ne pousse pas quelqu'un
    vers un formulaire de carte dans la seconde qui suit son accord.
  - ⚠️ **Rattrapage de l'existant (`php artisan devis:rattraper-reservations`).**
    Défaut trouvé à la vérification navigateur : la conversion se déclenche **au
    moment de l'acceptation**, donc tous les devis acceptés AVANT le déploiement
    restaient orphelins — statut « accepté », aucun montant exigible, et à
    l'écran un client qui a dit oui sans jamais voir de bouton. Corriger le futur
    en laissant le passé cassé n'était pas une correction : le passé, ici, ce
    sont de vraies ventes. La commande balaie les trois familles, se rejoue sans
    risque (conversion idempotente) et **n'envoie aucune notification** —
    réclamer aujourd'hui le règlement d'un accord vieux de plusieurs semaines
    serait déroutant ; la reprise de contact est un geste commercial.
  - **F8.14.a — un seul chemin de règlement.** L'écran offrait trois choix
    (en ligne / transfert Wave-OM, puis intégral / acompte). Décision produit :
    **paiement en ligne, montant intégral**, et rien d'autre. Le transfert manuel
    n'est confirmé qu'après le passage d'un agent — le client croit avoir payé
    alors que sa réservation attend — et l'acompte est réservé à de futures
    **dérogations** accordées au cas par cas à des clients fidèles ; l'ouvrir à
    tous reviendrait à l'accorder à tout le monde. ⚠️ **Masqués, pas supprimés** :
    deux booléens documentés dans le composant, le serveur et ses tests continuant
    de couvrir les deux modes. Et quand une seule possibilité subsiste, ce sont
    les **sections entières** qui disparaissent — un groupe de boutons radio à
    une seule option fait hésiter sans rien offrir.

  > ⚠️ **Rendu SSR** : `/back-office` est déclaré `RenderMode.Client` dans
  > [`app.routes.server.ts`](src/app/app.routes.server.ts), comme les autres
  > espaces privés. Sans cela, le guard tournerait côté serveur (sans accès au
  > `sessionStorage`) et **déconnecterait à chaque rafraîchissement**. Tout nouvel
  > espace privé doit être ajouté à cette liste.
  > **Lancer en local** : `php artisan serve` (API `:8000`) + un **worker de file**
  > (`php artisan queue:work`, pour l'envoi des codes 2FA) + `npm start` (Angular
  > `:4200`, proxy `/api`→`:8000` via `proxy.conf.json`). Comptes de démo :
  > `php artisan db:seed --class=Database\Seeders\BackOfficeDemoSeeder` (super_admin
  > + agent ; e-mails réglés par `BACKOFFICE_DEMO_SUPER_EMAIL` et
  > `BACKOFFICE_DEMO_AGENT_EMAIL`). ⚠️ **Les pointer vers de vraies boîtes** :
  > les alertes internes partent vers *tous* les comptes de l'équipe, et un
  > compte de démo resté sur un domaine inexistant fait rebondir chacune d'elles
  > dans la boîte d'expédition.

---

## Comment le site est organisé

Le code vit dans `src/app/`, rangé par responsabilité. Voici la carte des lieux,
en clair :

| Dossier | Rôle, en clair |
| --- | --- |
| [`features/`](src/app/features/README.md) | Les **écrans** regroupés par grande fonctionnalité (connexion, accueil, plus tard les catalogues…). |
| [`layouts/`](src/app/layouts/) | Les **cadres** qui entourent les pages (en-tête + pied de page du site, ou l'écran dédié à la connexion). |
| [`shared/`](src/app/shared/) | Les **briques d'interface réutilisables** (cartes de bien, badges « vérifié », galerie photo…) utilisées un peu partout. |
| [`core/`](src/app/core/) | La **plomberie invisible** : la gestion de la session (qui est connecté), la communication sécurisée avec le moteur, le contrôle des accès. |
| [`models/`](src/app/models/) | La **description des données** échangées avec le moteur (à quoi ressemble un « bien », une « réservation »…), pour éviter les erreurs. |

Chaque dossier a son propre `README.md` détaillé. Le principe : une fonctionnalité
peut s'appuyer sur `core` et `shared`, mais **ne dépend jamais d'une autre
fonctionnalité** — ainsi on peut faire évoluer une partie sans casser les autres.

### Un choix de sécurité à connaître

La « clé d'accès » d'un utilisateur connecté (le jeton) est gardée dans le
**`sessionStorage`** : elle **survit à un rafraîchissement de page** (on reste
dans son espace) mais est **effacée à la fermeture de l'onglet/navigateur** —
jamais dans le `localStorage`, donc rien n'est conservé sur le disque entre deux
sessions (utile sur un poste partagé). Au démarrage, la session est réhydratée
puis **revalidée** auprès du serveur (`GET /users/me`) : un jeton révoqué ferme
la session.

### Les e-mails renvoient vers ces routes — à ne pas casser

Les e-mails transactionnels (bienvenue, confirmation de réservation, pièce
manquante…) contiennent des **liens qui pointent directement dans ce site**. Ils
sont construits côté backend par `app/Support/Mail/SpaceLink.php`, qui connaît
les **quatre espaces connectés** :

| Profil | Espace |
| --- | --- |
| Client, Diaspora | `/mon-espace` |
| Propriétaire | `/espace-proprietaire` |
| Prestataire | `/espace-prestataire` |
| Entreprise | `/espace-entreprise` |

Sont également visés : `/back-office…` (alertes internes),
`/pages/politique-confidentialite` et `/pages/mentions-legales` (pied de page).

> ⚠️ **Renommer une de ces routes casse des liens déjà partis dans des boîtes de
> réception**, que l'on ne peut plus corriger. En cas de changement : mettre à
> jour `SpaceLink` **et** prévoir une redirection depuis l'ancienne adresse.
> Les liens s'appuient sur `FRONTEND_URL` (`.env` du backend).

---

## Détails techniques

- **Angular 22**, composants **standalone** (sans NgModules), **TypeScript strict**.
- Réactivité par **signals** ; composants en `ChangeDetectionStrategy.OnPush`.
- Chargement **à la demande** (lazy loading) des fonctionnalités pour un premier
  affichage rapide.
- Le point d'entrée `App` est réduit à un `<router-outlet>` ; chaque route choisit
  son **layout** (cadre).
- Design system maison : jetons de style dans
  [`src/styles/_tokens.scss`](src/styles/_tokens.scss), primitives (boutons,
  champs de formulaire, cartes…) dans [`src/styles/_base.scss`](src/styles/_base.scss).
  ⚠️ **Une classe portée par plus d'un composant est un style GLOBAL.** Les
  composants Angular sont sous encapsulation **émulée** : leur feuille de style
  ne touche que les éléments écrits dans *leur* gabarit. Une règle laissée dans
  la feuille d'un composant, mais dont la classe est réécrite ailleurs, n'y
  produit **rien** — et rien ne le signale, ni erreur, ni avertissement de
  compilation ; le défaut ne se voit qu'à l'écran. Cela s'est produit deux fois :
  `.uni-hero` (contenu **projeté** dans `page-hero`) et `.lead-form`
  (classes reprises par `construction-request-form`, carte affichée **sans
  marges**). Les deux sont maintenant globales, dans `_universe.scss` et
  `_conversion.scss`. Le symptôme jumeau à connaître : une classe **jamais
  définie** (`.k-hint`, employée dans deux gabarits) se comporte exactement
  pareil — sans bruit.
- Adresse de l'API du moteur configurée dans `src/environments/`.
- **Politique de défilement maison**
  ([`core/scroll/scroll-behavior.ts`](src/app/core/scroll/scroll-behavior.ts), F8.20)
  à la place de `withInMemoryScrolling`. La politique intégrée d'Angular remonte
  en haut de page à **chaque** navigation — or **filtrer un catalogue EST une
  navigation**, les filtres vivant dans les paramètres d'URL. Le visiteur réglait
  un prix maximum, validait, et se retrouvait devant la bannière du haut, à
  redéfiler jusqu'aux résultats — à chaque essai, alors qu'on affine une
  recherche cinq ou six fois de suite. La règle est désormais : position
  mémorisée (retour navigateur) > ancre demandée > haut de page si le **chemin**
  change > **on ne touche à rien** si seuls les paramètres changent (filtres, tri,
  pagination). ⚠️ La règle est isolée dans une fonction pure `deciderDefilement`,
  couverte par 6 tests : c'est un comportement invisible, qu'une régression ne
  ferait pas remarquer avant longtemps.

### Rendu côté serveur — SSR (F2.9)

Le site est rendu **côté serveur** (`@angular/ssr`, `outputMode: server`) : à
chaque visite, un petit serveur **Node/Express** ([`src/server.ts`](src/server.ts))
assemble le HTML complet de la page **avant** de l'envoyer au navigateur, puis
Angular « hydrate » ce HTML (le rend interactif sans le reconstruire). Concrètement :

- **Toutes les pages publiques** sont en `RenderMode.Server` (rendu à la demande,
  voir [`src/app/app.routes.server.ts`](src/app/app.routes.server.ts)) — choix
  adapté à des pages dynamiques (`/immobilier/:id`, `/pages/:slug`, `/recherche`)
  et alimentées par le backend.
- Les **espaces privés** (`mon-espace`, `espace-proprietaire`, `espace-prestataire`,
  `espace-entreprise`)
  sont au contraire en `RenderMode.Client` : le serveur ne connaissant pas la
  session, les rendre au serveur y ferait tourner les guards de rôle sans jeton
  et **redirigerait vers la connexion à chaque rafraîchissement**. Rendus côté
  client, ils laissent d'abord la session se restaurer (sessionStorage) avant
  d'exécuter les guards. Ces pages n'ont de toute façon aucun intérêt SEO.
- Les données lues pendant le rendu serveur sont **transférées au client**
  (transfer-cache HTTP, actif via `provideClientHydration`) : le navigateur ne
  refait pas les mêmes appels API. `withFetch()` est activé pour le HttpClient.
- Sur les pages publiques (SSR), le serveur ignorant la session rend toujours la
  vue « visiteur non connecté » — exactement ce qu'un moteur d'indexation doit voir.

> ⚠️ **Le serveur de rendu a besoin d'une adresse d'API ABSOLUE** — variable
> d'environnement **`API_ORIGIN`** (défaut : `http://localhost:8000`). En
> production `environment.apiUrl` vaut `/api/v1`, une adresse relative qui ne se
> résout que dans un navigateur : le processus Node l'adressait à sa propre
> origine, qui répond du HTML, et **chaque fiche du catalogue répondait
> « introuvable » au rendu serveur**. Invisible à l'écran — le navigateur refait
> l'appel correctement à l'hydratation — mais **c'est exactement ce que lisent
> Google et l'aperçu WhatsApp**. Corrigé en F9.1 par un intercepteur actif au
> seul rendu serveur ; voir « Référencement » plus bas et
> [`core/README.md`](src/app/core/README.md).

```bash
# 1. Construire (produit dist/kaikun360/{browser,server})
npx ng build

# 2. Lancer le serveur SSR → http://localhost:4000/  (port réglable via PORT)
npm run serve:ssr:kaikun360
```

> **Alternative conteneurisée** : `docker/frontend/Dockerfile` construit puis
> sert exactement ce même `server.mjs` — jamais un `dist/` statique, qui
> perdrait le SSR. Voir `docker/README.md` à la racine du dépôt.

> ⚠️ **Sécurité (déploiement)** : le serveur SSR refuse en `400 Bad Request`
> tout en-tête `Host` non explicitement autorisé (protection anti-SSRF
> intégrée à `@angular/ssr`). `angular.json → build.options.security.allowedHosts`
> ne contient que `localhost` (figé au build) — **ne pas le modifier pour
> chaque environnement**, ça imposerait de reconstruire l'image à chaque
> changement d'adresse publique. La variable d'environnement
> **`NG_ALLOWED_HOSTS`** (lue au démarrage, prioritaire sur `angular.json`)
> est le bon endroit : un domaine par valeur, séparés par une virgule, réglée
> dans `.env.docker` sans jamais toucher au build. Piège réellement rencontré
> au premier déploiement VPS (2026-08-15), voir `docker/README.md`.

**En-têtes de sécurité HTTP** (revue de sécurité, 2026-08-12) : `server.ts`
pose désormais une `Content-Security-Policy` (liste blanche des seules
origines tierces réellement utilisées : `accounts.google.com` pour la
connexion Google, `fonts.googleapis.com`/`fonts.gstatic.com` pour la
typographie, `maps.google.com` pour la carte de la page Contact), plus
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` et
`Permissions-Policy`. Seconde ligne de défense contre une XSS résiduelle — pas
un substitut à l'assainissement des entrées (`rich-text.sanitizer.ts`), qui
reste la première. **Si une nouvelle intégration tierce est ajoutée (script,
police, iframe), penser à l'ajouter à cette liste blanche.**

### Application installable — PWA (F9.0)

Réponse au **CDC §5 (« application mobile »)**, par la voie décidée avec le
client : une **PWA installable** plutôt qu'un projet React Native/Expo. L'API,
les écrans et les quatre espaces existaient déjà ; ce qui manquait, c'était
l'icône sur l'écran d'accueil et la tenue sur une connexion faible.

| Pièce | Rôle |
|---|---|
| [`public/manifest.webmanifest`](public/manifest.webmanifest) | nom, couleurs, icônes, `display: standalone`, 3 raccourcis (Rechercher, Mes demandes, Mes réservations) |
| [`public/icons/`](public/icons/) | 192/512 px classiques **et** `maskable`, plus l'icône iOS |
| [`public/favicon.ico`](public/favicon.ico) | icône d'onglet (F9.1). ⚠️ Elle est restée celle d'**Angular** (le « A » violet) de F0.1 à F9.1 — un onglet Kaikun était indiscernable d'un projet de démonstration. ⚠️ Ce n'est **pas** une réduction de `icon-512.png` : les détails d'une icône de 512 px ne survivent pas à 16 px. Le fichier embarque **trois dessins distincts** (16/32/48 px), le plus petit **sans le liseré doré** et à lettre plus étroite — seule façon de rester lisible. |
| [`ngsw-config.json`](ngsw-config.json) | ce que le service worker précharge, met en cache, et **ce qu'il ne met surtout pas** |
| [`src/app/core/pwa/`](src/app/core/pwa/) | proposer l'installation, signaler une nouvelle version |
| `app-pwa-banner` | le bandeau, monté une fois dans `app.html` |

**⚠️ Aucune donnée personnelle n'est mise en cache, et c'est une règle de
sécurité — pas un réglage de performance.** Un cache de service worker vit **par
origine, pas par utilisateur** : y laisser entrer `/users/me`, `/bookings/my` ou
`/messages` rendrait les données d'un compte lisibles par le **suivant** sur un
téléphone partagé. `ngsw-config.json` énumère donc une **liste blanche** de
routes publiques (catalogues, pages éditoriales, référentiel géographique) ;
tout ce qui n'y figure pas n'est jamais mis en cache. Vérifié dans un vrai
Chrome : après des appels authentifiés à `/users/me`, `/bookings/my` et
`/favorites` **répondant 200**, le cache ne contenait que les trois routes
publiques visitées.

**⚠️ Le préchargement ne prend QUE la coquille — 81 Ko, pas 2,06 Mo.** Un
`assetGroup` unique en `prefetch` capturait `/*.js`, donc les **166 chunks
paresseux** (back-office compris) : 2,06 Mo téléchargés dès la première visite,
exactement le contraire du but recherché sur une connexion sénégalaise moyenne.
Les groupes sont donc séparés — `coquille` (prefetch : `index.csr.html`, le CSS,
`main-*.js`) et `chunks` (lazy : téléchargés à la première visite de l'écran qui
les demande). Contrepartie assumée : **hors ligne, un écran jamais ouvert n'est
pas disponible**.

**⚠️ `index` pointe sur `/index.csr.html`**, pas `/index.html` : avec
`outputMode: server`, la construction ne produit pas d'`index.html` — le service
worker servirait un fichier inexistant.

**⚠️ `@angular/service-worker` est épinglé à la version EXACTE** (`22.0.6`, sans
accourt `^`) : les paquets Angular se réclament mutuellement à la patch près, et
`^22.0.0` résout vers une version plus récente que `@angular/core` — `npm install`
échoue alors sur un conflit de pairs. À faire évoluer **avec** le reste d'Angular.

**⚠️ Le service worker est désactivé en développement** (`enabled: !isDevMode()`) :
actif, il sert des bundles en cache et fait « mentir » le rechargement à chaud —
on croit corriger un fichier que le navigateur ne relit jamais.

Éprouvé dans un Chrome réel (protocole DevTools) : service worker **actif** sur
la portée `/`, caches créés, et **rechargement hors ligne rendant la page**
(titre correct, `app-root` présent, contenu affiché).

```bash
# La PWA n'existe QUE dans un build de production servi en HTTPS (ou localhost).
npx ng build && npm run serve:ssr:kaikun360   # → http://localhost:4000/
# Chrome → DevTools → Application → Service Workers / Manifest
```

### Référencement — balises, données structurées, plan du site (F9.1 / F9.2)

Le rendu serveur était en place depuis F2.9 et **122 titres de route** étaient
posés, mais il n'existait dans tout le frontend **aucune description, aucune URL
canonique, aucune balise OpenGraph, aucune donnée structurée**, ni `robots.txt`
ni `sitemap.xml`. Deux conséquences très concrètes : un lien collé sur
**WhatsApp** — le canal de partage principal du projet — s'affichait nu, sans
titre ni image ; et Google, faute de description, en fabriquait une à partir du
premier texte rencontré, souvent le menu de navigation.

**Où ça vit** : [`src/app/core/seo/`](src/app/core/seo/) — le service de balises,
les constructeurs schema.org, et la stratégie de titre du routeur.

**Comment une page est décrite**, en deux temps qui ne se confondent pas :

1. **la route** déclare un repli dans `data: { seo: { description, index?, type? } }`
   ([`app.routes.ts`](src/app/app.routes.ts)). Il est appliqué **dès la
   navigation**, avant tout appel réseau ;
2. **la fiche** l'affine une fois ses données reçues (`SeoService.apply()`), avec
   le vrai titre, la vraie ville et la vraie photo — plus son JSON-LD
   (`Product` + `Offer`, `BreadcrumbList`).

> ⚠️ **Une route SANS `data.seo` est mise hors index** (`noindex, follow`).
> C'est délibéré, et **à ne pas inverser** : les quatre espaces connectés et le
> back-office représentent bien plus de routes que les pages publiques. Avec la
> règle contraire, le prochain écran privé ajouté partirait dans l'index de
> Google par simple oubli. Ici, l'oubli fait perdre du référencement à une page
> publique — visible et réparable.
>
> ⚠️ `noindex` **n'est pas un contrôle d'accès** : il demande à un robot poli de
> ne pas publier la page. Ce qui protège reste `authGuard` / `roleGuard` côté
> routes et les policies côté API.

> ⚠️ **`index.html` porte un `noindex` de repli, ce n'est pas une erreur.** Une
> URL d'espace connecté demandée sans session fait annuler la navigation par la
> garde : le serveur répond alors une page **vide en HTTP 200**, où rien n'a pu
> écrire de balise. Sans ce repli, Google recevrait une page blanche indexable —
> ce qu'il compte comme « soft 404 » au passif de tout le domaine. Le service le
> remplace par `index, follow` sur chaque page publique.

> ⚠️ **Le `Router` est résolu à l'usage dans `SeoService`, jamais au
> constructeur.** Le routeur exige sa stratégie de titre pour se construire ; si
> le service qu'elle utilise réclame le routeur en retour, la boucle est fermée
> et le **build échoue en `NG0200`** — sous le message trompeur « An error
> occurred while extracting routes », sans rapport apparent avec le SEO.

**`siteUrl`** (dans `src/environments/`) est l'adresse publique du site, **le
seul endroit à changer au déploiement**. Elle doit valoir exactement la même
chose que `FRONTEND_URL` côté backend (le réglage qui sert déjà les liens des
e-mails depuis F8.8). Une URL absolue est obligatoire : `canonical`, `og:url` et
`og:image` sont lues par des robots sans contexte de page — et elle ne peut pas
être déduite de `window.location`, puisqu'au rendu serveur il n'y a pas de
`window`.

**`robots.txt`** est un fichier statique de [`public/`](public/robots.txt) :
il n'est lu qu'à la racine du domaine visité, donc il doit être servi par le
site, pas par l'API.

**`sitemap.xml`** est en revanche **produit par Laravel** (`App\Support\Seo\SitemapBuilder`) :
lui seul connaît les fiches publiées, et la suite de tests backend peut le
vérifier. Le serveur de rendu ([`src/server.ts`](src/server.ts)) le **relaie**
sous le domaine du site — un moteur n'accepte un plan que servi par le domaine
qu'il décrit, et l'API peut vivre ailleurs. Ce relais lit l'origine de l'API
dans la variable d'environnement **`API_ORIGIN`** (défaut : `http://localhost:8000`),
et **pas** `environment.apiUrl`, qui vaut `/api/v1` en production — une adresse
relative n'a de sens que dans un navigateur.

**Vérifier en local** (les balises ne sont complètes que dans un rendu serveur) :

```bash
npx ng build --configuration development     # apiUrl absolue → le SSR peut appeler l'API
node dist/kaikun360/server/server.mjs        # → http://localhost:4000/

curl -s http://localhost:4000/immobilier/98 | grep -E 'og:|canonical|robots'
curl -s http://localhost:4000/mon-espace/profil | grep robots   # doit dire noindex
curl -s http://localhost:4000/sitemap.xml | head
```

### Assistant Kaikun — le panneau (F10.1)

Le socle serveur (F10.0) était complet mais **invisible** : un endpoint sans écran.
Cette tranche livre l'écran, et rien d'autre — aucun outil neuf, aucune règle métier.

**Trois fichiers, trois rôles** :

| Fichier | Rôle |
|---|---|
| `core/api/assistant.service.ts` | le **contrat** : `POST /assistant/messages` et ses types |
| `core/state/assistant-store.ts` | la **mémoire** : conversation, ouverture, attente, gestes |
| `shared/components/assistant/` | l'**écran** : le lanceur d'en-tête + le tiroir |

#### Du coin bas-droite au tiroir (F10.5)

L'assistant s'ouvrait par une **bulle flottante** en bas à droite, et le panneau
déployé faisait 380 px de large sur une hauteur ajustée à son contenu. Trois
défauts, tous constatés à l'écran :

- la bulle **recouvrait le contenu** de chaque page sans qu'on le lui demande, et
  disputait ce coin à `app-scroll-top` et `app-pwa-banner` ;
- le panneau **s'ouvrait grand comme un post-it** sur le seul message d'accueil,
  puis grandissait par à-coups à chaque réponse ;
- 380 px ne laissaient à une réponse de cinq annonces que la place de les empiler,
  ce qui poussait la conversation hors de vue.

Livré : `assistant-launcher` (le bouton, posé dans les **trois** en-têtes — public,
espaces connectés, back-office) et le panneau devenu **tiroir** — 480 px, toute la
hauteur, voile de fond cliquable, angles arrondis et retrait des bords.
⚠️ **Les deux ne se connaissent pas** : ils partagent `estOuvert` sur le
`AssistantStore`. C'est ce qui permet de poser le bouton dans trois chromes
différents sans qu'aucun ne monte le tiroir, ni ne le monte deux fois.
⚠️ **`animate.leave` n'est pas décoratif** : sans lui Angular arrache l'élément du
DOM et le tiroir **disparaît d'un coup** après être arrivé en glissant — c'est
cette asymétrie qui rendait le geste désagréable. Entrée et sortie ont deux
courbes différentes, volontairement : on se pose lentement, on part vite.

Dans le fil, **les résultats défilent en rangée** (carrousel) dès qu'il y en a
plusieurs — empilées, cinq annonces occupaient toute la hauteur. Le défilement est
celui du **navigateur** (`overflow-x` + `scroll-snap`) : glissement au doigt,
molette inclinée et tabulation marchent sans une ligne de JS. Le TypeScript ne fait
que deux choses que CSS ignore — mesurer si quelque chose dépasse (les flèches
n'apparaissent qu'alors) et avancer d'une carte au clic — et son écoute du
défilement est posée **hors zone Angular**, pour qu'un glissement ne déclenche
aucun cycle de détection. ⚠️ **La FAQ reste empilée** : une question et sa réponse
se lisent, des cartes de 190 px les rendraient illisibles.

⚠️ **Le panneau est monté dans les *layouts*, pas dans la racine applicative**
(`main-layout`, `space-layout`, et `backoffice-layout` depuis F10.3). C'est ce qui
le tient hors du **parcours d'authentification** — on n'interrompt pas une saisie
de mot de passe. Le monter dans `app.html`, comme `app-scroll-top`, l'aurait mis
partout, y compris là.

#### La variante « back-office » (F10.3)

Le panneau prend une entrée `variante` (`'public'` par défaut, `'back-office'`
dans le shell de l'équipe) qui change **trois textes** : le sous-titre, l'exemple
de question et la mention de pied — laquelle dit franchement à l'équipe que
l'assistant est en **lecture seule**, là où elle dit au public qu'il n'engage pas
Kaikun 360.

⚠️ **Ce réglage ne change RIEN à ce que l'assistant sait faire.** La trousse est
composée côté serveur à partir du **jeton**, pas de la page : un administrateur
obtient ses outils de back-office depuis le site public, et un visiteur n'en
obtiendrait aucun même en ouvrant cette variante. Le faire porter par le layout —
plutôt que le déduire du rôle connecté — est délibéré : le rôle dit ce qu'on
*peut* faire, la page dit ce qu'on est *en train* de faire, et c'est la seconde
qui règle une invite. Sans cela, un agent à qui l'on propose « une villa à Saly »
ne devine pas que la bulle connaît sa file de validation.

⚠️ **Il est monté HORS de `.bo-app`**, qui est en `100dvh` + `overflow: hidden`
depuis F8.1 : la bulle est en `position: fixed` donc rattachée à la fenêtre, mais
la placer dans ce conteneur ferait rogner le panneau déployé, plus haut qu'elle.

⚠️ **La conversation vit dans un store `root`, pas dans le composant.** Le panneau
propose un lien, l'utilisateur clique, arrive dans son espace : le composant est
**détruit** au passage. Sans store, le fil disparaîtrait au moment précis où
l'assistant vient d'être utile. En revanche **rien n'est écrit dans le
navigateur** (ni `localStorage` ni `sessionStorage`, contrairement au comparateur
et au panier de réservation) : une conversation porte ce que la personne a tapé.

⚠️ **`SKIP_ERROR_REDIRECT` n'est pas un détail.** `errorInterceptor` renvoie vers
la page d'erreur dès qu'un appel répond 0 ou 5xx — or l'interrupteur d'urgence de
l'assistant répond **503**. Sans ce marqueur, couper l'assistant aurait éjecté de
sa page quiconque lui écrit : un panneau facultatif faisant tomber la navigation
de tout le site.

⚠️ **Le moteur du serveur est invisible d'ici, et c'est voulu (F10.4).** Le panneau
consomme `AssistantReply` — `text`, `items`, `actions` — sans jamais savoir lequel
des deux cerveaux l'a produite. Le driver `claude` ne demande donc **aucun** travail
frontend, et son repli sur le déterministe (clé absente, panne du fournisseur) reste
**indiscernable à l'écran** : c'est exactement le comportement recherché, mais cela
signifie qu'en vérification navigateur, **une réponse correcte ne prouve pas que le
moteur intelligent est actif**. Le signe qui ne trompe pas est le suivi du fil : le
déterministe traite chaque message isolément, donc « et moins cher ? » y retombe sur
le support.

⚠️ **Le coin bas-droite est partagé** entre trois éléments fixes : `app-scroll-top`
(z-index 900), `app-pwa-banner` (950) et l'assistant. La bulle s'empile **au-dessus**
du bouton « retour en haut » ; le panneau déployé passe devant tout (960), et
devient une **feuille pleine largeur** sous 640 px.

**Vérifier en local** : `npx ng serve`, puis la bulle en bas à droite de n'importe
quelle page publique. Essais utiles — « une villa à Saly », « un circuit en
Casamance », « comment fonctionne le paiement ? », « je veux parler à un
conseiller » (connecté : le bouton ouvre un vrai fil de support et conduit à la
messagerie de **son** espace). Assistant coupé (`ASSISTANT_ENABLED=false` côté
backend) : la bulle disparaît après le premier message, et **la page ne bouge pas**.

### Pages de secours — `/erreur` et le 404 (F10.1.a)

⚠️ **Elles n'existaient pas.** `errorInterceptor` renvoyait vers `/erreur` depuis
F0, et aucune route n'attrapait les adresses inconnues : au rendu serveur chaque
cas levait `NG04002`, au navigateur la navigation était **annulée** — l'utilisateur
restait sur sa page sans explication. Un lien périmé partagé sur WhatsApp tombait
dans le vide.

Un composant (`features/content/error-page/`), deux routes (`erreur` et `**`).
Détail et pièges : [`src/app/features/content/README.md`](src/app/features/content/README.md).

**Vérifier** (le rendu serveur, pas seulement l'écran) :

```bash
npx ng build && node dist/kaikun360/server/server.mjs

curl -s 'http://localhost:4000/erreur?depuis=/immobilier/98' | grep -o 'Réessayer'
curl -s http://localhost:4000/une-page-qui-nexiste-pas | grep -o '>404<'
```

⚠️ **La route `**` doit rester la dernière** de `app.routes.ts` : elle accepte
tout, et rendrait inatteignable n'importe quelle route déclarée après elle.

### Cadres de navigation — rails repliables et surfaces arrondies (F11.1)

Trois cadres entourent la totalité des écrans, et ils sont **indépendants** :

| Cadre | Fichier | Forme |
| --- | --- | --- |
| Poste de commandement | `layouts/backoffice-layout/` | deux **cartes** (rail + contenu) sur fond graphite |
| Les 4 espaces connectés | `layouts/space-layout/` | rail fixe **décollé** + barre supérieure en carte |
| Site public | `shared/components/header/` | bandeau **collé** en haut, coins **bas** arrondis |

**Le pli.** Une poignée ronde sur le bord droit du rail le fait passer de 260px
à 76px. Le choix est écrit en `localStorage` — clés **distinctes** par shell
(`k360.bo.rail-replie`, `k360.espace.rail-replie`) : replier son espace client
ne doit pas replier le poste de commandement. La lecture est **SSR-safe**
(`typeof window === 'undefined'`) et enveloppée d'un `try` : le stockage lève
en navigation privée.

⚠️ **Trois pièges, si l'on retouche cette mécanique :**

- **`display: none` sur les libellés, jamais une opacité.** Une opacité les
  laisserait occuper la place et imposer une largeur minimale au lien, qui
  refuserait de descendre à 76px.
- **Dans `space-layout`, la classe du pli est sur `.account-app`, pas sur le
  rail.** Le rail y est en `position: fixed` : c'est la marge de
  `.account-body` qui lui fait sa place. Les deux doivent bouger ensemble,
  sinon un trou de 200px s'ouvre entre le menu replié et le contenu.
- **Le pli est neutralisé sous le seuil mobile** (900px au back-office, 860px
  dans les espaces). Là, le rail est un tiroir : on l'ouvre pour lire.

⚠️ **Aucun `overflow: hidden` sur un rail ni sur une barre.** La tentation est
forte pour « nettoyer » les angles ; elle casse deux choses : la poignée de pli
déborde volontairement du bord droit, et le **menu utilisateur** comme le
**panneau de notifications** sont en position absolue *dans* la barre des
espaces — ils retombent sous son bord bas et seraient tranchés. Le défilement
interne du rail (qui garde son pied atteignable sur écran bas) vit désormais sur
`.account-nav` / `.bo-nav`, pas sur le rail entier.

⚠️ **Opaque ou translucide, ce n'est pas un goût :**

- la **barre des espaces** est collante et opaque, et porte sa gouttière sur son
  hôte avec un fond couleur page — sans quoi on verrait le contenu remonter dans
  les 12px au-dessus d'elle ;
- l'**en-tête public** reste translucide avec son flou : le contenu défile
  visiblement derrière lui, c'est ce qui le rend vivant.

⚠️ **Le faux « angle droit dans le coin arrondi ».** Symptôme observé sur la
barre des espaces, cause ailleurs : l'ombre du rail déborde sur la gouttière, et
la barre opaque repeint son rectangle par-dessus, **tranchant cette ombre net
sur son bord gauche, qui est droit**. Réglé en ramenant l'ombre du rail au même
niveau que celle de la barre. **Si l'on rappuie cette ombre, la bande revient.**

⚠️ **La poignée est calée à `top: 82px` dans les deux shells** : 69px de bandeau
de marque + une respiration — hauteur qui est aussi celle de la barre
supérieure, si bien qu'elle passe sous le coin arrondi de celle-ci *et*
s'aligne sur la couture du bandeau. La déplacer suppose de vérifier les deux
hauteurs (`.bo-brand` / `.account-brand` et `.bo-topbar` / `.acc-bar`).

### Les cartes de l'accueil — la pastille qui déborde (F11.2)

Les trois grilles de la page d'accueil (**Univers** ×9, **Protocole** ×3,
**Services** ×4) portent une icône qui **sort du cadre par le haut**. La carte
reste un rectangle lisible ; c'est la grille qui cesse d'être plate.

**La règle de couleur** — la pastille s'oppose toujours à son fond :

| Section | Fond | Pastille au repos | Au survol |
| --- | --- | --- | --- |
| Univers | crème | bleu nuit / glyphe or | bleu de marque |
| Protocole | **navy** | **or / glyphe navy** | or éclairci |
| Services | crème | bleu nuit / glyphe or | vert |

⚠️ **L'inversion sur Protocole est obligatoire, pas décorative** : le fond de
section y est navy et la carte n'est qu'un voile blanc à 4 % — une pastille
sombre serait invisible sur les deux.

⚠️ **Quatre pièges, si l'on retouche ces grilles :**

- **Pas d'`overflow: hidden` sur `.univers-card`.** Il y était, pour contenir le
  reflet diagonal du survol, et il **tranchait l'icône**. Le rognage vit
  désormais sur `.univers-shine`, un calque qui épouse la carte (`inset: 0` +
  `border-radius: inherit`) et ne contient que le reflet.
- **Ne pas remettre `.univers-icon` dans le groupe `position: relative`** qui
  suit dans le fichier. À spécificité égale, cette règle écrite plus bas
  l'emporte : la pastille retombe dans le flux et cesse de déborder, **sans
  aucune erreur**.
- **Les gouttières sont dissymétriques** (`gap: 48px 20px`) : la pastille
  occupe ~36px hors de la carte (22px de décalage + ~15px d'ombre au-dessus).
  Dans les 20px d'origine elle mordait la rangée supérieure. Seules les
  gouttières **horizontales** sont concernées.
- **`scroll-margin-top` des cartes Services : 116px, pas 90.** Ce sont des
  cibles d'ancre (méga-menus) ; à 90px l'arrivée coupait la pastille.

Sous `prefers-reduced-motion`, les trois grilles ne basculent plus — seule la
couleur répond au survol.

### Corbeille des espaces — ranger sans détruire (F11.4)

Ce qu'on retire d'une liste (« Mes biens », « Mes offres »…) ne disparaît plus :
ça part à la **corbeille**, récupérable 30 jours.

- **Écran** : [`features/trash/`](src/app/features/trash/) — un **seul** écran
  pour les cinq types d'annonces.
- **Service** : [`core/api/trash.service.ts`](src/app/core/api/trash.service.ts).
- **Monté dans les espaces propriétaire et prestataire seulement** : un client
  ou une entreprise ne possède aucune annonce, sa corbeille serait vide à vie.

⚠️ **Aucune suppression depuis cet écran, et c'est délibéré.** Ranger une
annonce reste le geste de son propre écran, qui connaît ses règles et sait
refuser (bien sous mandat actif, offre déjà réservée). La corbeille ne sait que
**regarder** et **restaurer**.

⚠️ **Un élément restauré revient hors ligne**, et l'écran le dit **avant** le
clic. Sans cette phrase, on le cherche au catalogue et on croit à un bug.

⚠️ **`days_left: 0` ne veut pas dire « déjà supprimé »** : la purge est une
tâche planifiée qui passe une fois par nuit. L'écran dit donc « aujourd'hui »,
seule formulation honnête.

⚠️ La durée de conservation vient du **serveur** (`retention_days`), jamais
écrite en dur côté écran : une seule source, pas deux vérités qui divergent.

### La corbeille de l'espace CLIENT (F11.5)

Le même écran est désormais monté dans `/mon-espace`, mais il y montre une
**seconde famille** d'éléments, qui n'obéit pas aux mêmes règles.

| | `kind: 'listing'` (F11.4) | `kind: 'record'` (F11.5) |
| --- | --- | --- |
| Quoi | les 5 annonces | demande, réservation, discussion, notification |
| Supprimé ? | oui, au bout de 30 jours | **jamais** |
| `days_left` | un nombre | **`null`** |
| Au retour | **éteint** (hors ligne) | **tel quel**, statut compris |

⚠️ **`days_left: null` n'est pas une valeur manquante, c'est l'information.**
L'écran écrit alors « Conservé — rien ne sera supprimé ». Lui coller un compte à
rebours de 30 jours serait faux dans les deux sens : ça inquiéterait pour rien,
et ça ferait croire à un ménage automatique qui n'aura jamais lieu.

⚠️ **Le sous-titre et la note de bas de liste sont CONDITIONNELS**
(`hasListings()`). Dans l'espace client, qui n'a aucune annonce, parler de purge
ou de republication n'aurait aucun sens.

⚠️ **`TrashItem.id` est une CHAÎNE**, pas un nombre : l'identifiant d'une
notification est un **UUID**. Le typer `number` le ramènerait à `NaN` et rendrait
toutes les notifications indiscernables.

⚠️ **La liste est plafonnée à 200 côté serveur** (`truncated`, `total`) : les
dossiers masqués n'étant jamais purgés, la réponse serait autrement sans borne.
L'écran le **dit** au lieu de s'arrêter en silence.

**Le geste de rangement**, lui, reste sur l'écran d'origine — quatre écrans, un
seul bouton partagé
([`shared/components/hide-button/`](src/app/shared/components/hide-button/)) :

| Écran | Service | Condition (décidée par le serveur) |
| --- | --- | --- |
| Mes demandes | `RequestService.hide` | demande **clôturée** |
| Mes réservations | `BookingService.hide` | réservation **terminée ou annulée** |
| Messages | `MessageService.hide` | fil **entièrement lu** |
| Notifications | `NotificationService.hide` | notification **déjà lue** |

⚠️ **Le bouton n'apparaît que si `hideable` le dit** — jamais sur une règle
rejouée côté écran. Un bouton qui échoue en 422 est pire que pas de bouton.

⚠️ **Le mot est « Ranger », jamais « Supprimer ».** Ce n'est pas de la
politesse : rien n'est supprimé. Un client qui lit « Supprimer » sur sa
réservation croirait effacer un contrat.

⚠️ **Le bouton est posé HORS du lien / du bouton de la carte** sur les quatre
écrans. Imbriqué, il ouvrirait l'élément qu'on est en train de faire
disparaître — et un `<button>` dans un `<a>` est du HTML invalide.

⚠️ **Piège corrigé sur les notifications** : « Tout marquer comme lu » (et le
clic sur une carte) mettent `hideable: true` en même temps que `read: true` sur
la liste locale. Sans cela, les boutons « Ranger » n'apparaîtraient qu'au
prochain chargement de page.

### Les bandeaux d'en-tête, pilotés depuis le back-office (F12)

Chaque grande page publique s'ouvre sur un **bandeau** : un surtitre, un titre,
une accroche. Jusqu'ici ce bandeau était toujours posé sur le même dégradé bleu,
et ses mots vivaient dans le code : **changer une photo d'accueil ou une phrase
supposait un redéploiement**. La page de résultats `/recherche`, elle, n'avait
carrément aucun bandeau — un titre nu au-dessus des filtres.

Désormais l'équipe charge une image et retouche les mots depuis le back-office
(*Paramètres & contenu → onglet **Bandeaux***), et le site suit.

**Ce qu'il faut retenir, en clair :**

- **Une photo par univers suffit.** Les pages qui dépendent d'un univers
  reprennent automatiquement son image : charger la photo d'*Immobilier* habille
  aussi la page de résultats filtrée sur l'immobilier.
- **Les textes du site restent la valeur par défaut.** Un champ laissé vide au
  back-office ne vide rien : la page garde la phrase écrite dans son gabarit.
- **La page `/recherche` a cinq visages** : titre, accroche et image suivent
  l'onglet d'univers actif.

**Côté technique.** Composant unique
[`shared/components/page-hero/`](src/app/shared/components/page-hero/), alimenté
par `core/api/hero.service.ts` (`GET /heroes`, **un seul appel** partagé par
toutes les pages). Douze pages ont migré du bloc `.uni-hero` recopié vers ce
composant.

⚠️ **L'héritage d'image est résolu côté SERVEUR**, pas ici : le composant lit
l'entrée de sa clé (`immobilier`, `recherche.nuitees`…) et n'a aucune règle de
parenté à connaître. Faire remonter la chaîne dans le navigateur l'aurait obligé
à **dupliquer le catalogue** du serveur — et à le laisser diverger au premier
ajout de page.

⚠️ **Le texte n'hérite jamais, l'image si.** Un titre est écrit *pour* une page ;
faire descendre « Des biens vérifiés » sur une liste filtrée afficherait un
titre **faux**, ce qui est pire que pas de personnalisation.

⚠️ **Les styles de la variante `--image` sont dans le style GLOBAL**
(`styles/_universe.scss`), pas dans la feuille du composant. Le contenu propre à
chaque page (points forts, renvois, boutons) y est **projeté** : sous
encapsulation émulée, une feuille de composant ne touche pas les nœuds projetés,
la règle n'aurait donc rien atteint — **sans la moindre erreur pour le
signaler**.

⚠️ **La photo est posée sous un voile dégradé**, avec ombre portée sur le texte
et cartes de points forts assombries. Le bandeau écrit en **blanc** : sans ce
voile, une plage ou une façade au soleil rendrait le titre illisible — et on ne
peut pas faire confiance à la photo que l'équipe choisira.

⚠️ **Le voile est DIRECTIONNEL, et il vit dans le CSS — pas dans le style en
ligne.** Première version : un aplat de marque à 86 % d'opacité, empilé avec
l'image par `PageHeroComponent`. Résultat constaté à l'écran, le client l'a
signalé : *on ne voyait plus la photo, seulement un rectangle bleu*. Deux
défauts distincts, corrigés ensemble. (1) La teinte était le **bleu vif** de
marque, qui *teinte* la photo au lieu de l'assombrir — le voile est maintenant
un navy neutre. (2) L'opacité était **uniforme**, alors que le texte n'occupe
que la gauche du bandeau (titre borné à 18ch) : elle décroît désormais de 84 % à
gauche à 8 % à droite, où la photo se donne enfin à voir. Ce dégradé-là ne
pouvait pas rester en style en ligne : un style en ligne **ignore les requêtes
média**, et sur téléphone le texte occupe toute la largeur — il y faut un voile
vertical. Le voile est donc passé dans `.uni-hero--image::before`, horizontal
au-delà de 900 px, vertical en dessous ; le composant ne pose plus que
`url(...)`. ⚠️ Le pseudo-élément étant posé **après** le contenu dans l'ordre
d'empilement, `.uni-hero-inner` reprend `position: relative; z-index: 1` — sans
quoi le voile recouvrirait le texte qu'il est censé servir.

⚠️ **Une image de fond n'est pas une photo d'annonce, et le back-office le dit.**
Le bandeau est étiré sur **toute la largeur de l'écran** : une image de 1600 px
(la borne des photos d'annonce) y est *agrandie* par le navigateur sur un
moniteur courant, et un agrandissement ne peut que flouter. Côté serveur, la
borne des bandeaux est passée à **2560 px / JPEG 88** (`ImageProcessor::BACKGROUND_MAX_WIDTH`) ;
côté écran, la fiche annonce la taille attendue — car aucun réglage serveur ne
rattrape une photo déposée trop petite, `scaleDown` ne **réduisant** jamais que
ce qui dépasse.

⚠️ **L'image est MESURÉE dans le navigateur, avant tout envoi.** Le serveur
refuse déjà les fonds de moins de 1400 × 500 px, mais son refus arrivait au
mauvais endroit : le message d'erreur de l'écran Bandeaux est **unique pour la
page entière** et s'affiche en haut, alors que les fiches se comptent par
vingtaines et qu'on choisit son fichier après avoir beaucoup défilé. Constaté en
recette, et c'est exactement ce qu'a vécu le client : une image de 750 × 465 px
déposée, « Enregistrer » cliqué, **rien ne semble se passer**. Le refus est donc
prononcé au moment du **choix du fichier**, sous le bouton même, et il annonce
les dimensions trouvées — `URL.createObjectURL` + `naturalWidth`, aucun
aller-retour réseau. ⚠️ Le champ `<input type="file">` est **vidé après chaque
choix** : sans cela, rechoisir le même fichier corrigé ne redéclencherait aucun
`change` et l'écran resterait figé sur son erreur. ⚠️ Une mesure **impossible**
(format exotique, fichier illisible) laisse passer : c'est le serveur qui
tranche, refuser sur un doute écarterait une image valable.

⚠️ **L'invitation « Contactez-nous » de la FAQ a quitté l'accroche** pour devenir
une ligne à part : l'accroche est devenue une donnée réécrivable, et un texte
saisi au back-office ne peut pas contenir de lien interne — un `routerLink` s'y
afficherait tel quel.

⚠️ **Hydratation** : l'appel `GET /heroes` part pendant le rendu serveur et son
résultat est **rejoué depuis le transfer cache** côté client — le navigateur ne
le redemande pas, et le bandeau ne change pas d'aspect après l'hydratation.

⚠️ **« Une seule fois » voulait dire « une seule fois pour la session », et
c'était un piège.** `HeroService` partage sa liste entre les douze pages
publiques ; jusqu'ici l'appel ne repartait donc **jamais** de toute la vie de
l'onglet. Constaté en recette : l'équipe dépose dix photos au back-office, revient
sur le site *sans recharger le navigateur* — ce qu'une application d'une seule
page ne demande jamais — et n'en voit **qu'une**, celle qui existait déjà au
chargement de l'application. L'envoi paraît perdu alors que la base et l'API sont
justes. Le service expose maintenant `refresh()`, appelé par l'écran Bandeaux
après chaque enregistrement, retrait d'image et réinitialisation. ⚠️ Le
déclencheur est un **`Subject` + `startWith`**, et surtout pas un signal passé à
`toObservable` : ce dernier n'émet que depuis un **effet**, donc au premier cycle
de détection et non à la construction du service — la première requête cessait de
partir au montage (les tests l'ont pris sur le fait), ce qui retarde le bandeau et
fragilise le rendu serveur, lequel doit voir l'appel partir pour l'attendre.
⚠️ Le `catchError` est **à l'intérieur** du `switchMap` : au-dehors, il achèverait
le flux à la première panne de réseau et plus aucun rechargement ne serait
possible ensuite. Les trois comportements sont tenus par
`core/api/hero.service.spec.ts`.

### Les graphiques du business, au back-office (F13.1)

Une rubrique **Statistiques** (`/back-office/statistiques`) : six chiffres
d'en-tête comparés à la période précédente, puis les revenus dans le temps,
l'origine de l'activité par univers métier, le tunnel commercial, la
répartition par statut et le palmarès des annonces. Le détail — les règles de
dessin, les couleurs mesurées, les pièges — vit dans
[`features/backoffice/statistics/README.md`](src/app/features/backoffice/statistics/README.md).

Ce qu'il faut retenir hors de ce dossier :

- **Aucune bibliothèque de graphiques.** Tout est du SVG écrit à la main, dans
  des composants autonomes. Une bibliothèque impose son allure et Chart.js
  dessine dans un `<canvas>` qui ne rend rien au **rendu serveur** — actif ici.
  Coût réel : **40 ko** pour toute la rubrique, 10 ko compressés.
- ⚠️ **Les couleurs de données ne s'inventent pas.** Elles vivent toutes dans
  `charts/chart-tokens.ts` et ont été **validées** (daltonisme, contraste, bande
  de clarté) sur le fond réel des cartes. L'**ordre** de la palette fait partie
  de ce qui est validé. N'écrivez jamais une teinte en dur dans un composant de
  graphique.
- ⚠️ **L'état masqué d'une animation d'entrée vit dans le `from` de la
  `@keyframes`, avec `backwards` — jamais sur la règle de base de l'élément.**
  Dans l'autre sens, l'élément reste invisible partout où l'animation ne se joue
  pas : rendu serveur, animations coupées par le navigateur. C'est ainsi que le
  graphique principal de l'écran s'est affiché **vide** en F13.1, sans la
  moindre erreur en console. *L'absence d'animation doit donner le graphique
  tracé, pas le graphique absent.*
- ⚠️ **Un graphique ne se juge pas au test.** Les trois défauts corrigés en
  F13.1 étaient tous invisibles aux tests et évidents sur une capture d'écran.
- ⚠️ **La dernière graduation d'un axe sert d'ÉCHELLE** : elle doit couvrir le
  maximum des données, pas s'arrêter juste en dessous. Sinon le tracé sort de
  son cadre — vu en recette, une courbe débordant par-dessus le bouton
  « Données ». Et une exigence de graduations entières s'applique au **pas**,
  jamais en filtrant les valeurs non entières après coup : ce filtrage jette
  aussi le sommet. Verrouillé par `charts/chart-tokens.spec.ts`.
- ⚠️ **`white-space: nowrap` se pose sur le fragment court, jamais sur son
  conteneur.** Posé sur un paragraphe, il rend insécable *tout* ce qui peut y
  passer — y compris un message de repli d'une phrase entière, qui impose alors
  sa largeur en minimum à toute la colonne de grille. Même famille de piège que
  `1fr` (qui vaut `minmax(auto, 1fr)`, donc un plancher à min-content) : dans
  une grille dont les pistes doivent rester égales, écrire `minmax(0, 1fr)`.

### La vitrine tournante de l'accueil (F13.5)

La section « Sélection du moment » passe les **cinq univers** en revue toutes les
7 secondes. Trois règles à ne pas défaire :

- **Un cache par univers + préchargement du suivant.** Une vitrine qui tourne
  indéfiniment rappellerait l'API tant que l'onglet reste ouvert, et chaque
  bascule montrerait un chargement. C'est ce cache qui fait la fluidité.
- **Le signal `universCourant` est posé AVANT l'affichage.** `montrer()` vérifie
  au retour de la requête que l'univers demandé est toujours celui qu'on
  regarde ; dans l'autre ordre, cette garde échoue à tous les coups et la
  vitrine reste bloquée sur le premier univers.
- **Le minuteur est réservé au navigateur** et arrêté dans `ngOnDestroy`.

⚠️ **Deux pièges de mise en pause, tous deux vécus.** (1) La pause au survol
posée sur toute la section gelait le tour dès que la souris traînait dans la
hauteur réservée à la grille : elle ne porte que sur les **pastilles et les
cartes**. (2) Suspendre sur tout `focusin` figeait la vitrine **pour de bon**
après un simple clic — un clic laisse le bouton focalisé, et `focusout` peut ne
jamais venir. Seul le focus **clavier** compte (`:focus-visible`), et un
garde-fou repart après une minute de pause.

⚠️ **Règle CSS générale, rencontrée deux fois le même jour** : `.x:hover` compte
**une classe de plus** que `.x--active` et l'emporte donc **quel que soit l'ordre
d'écriture**. Sans règle `.x--active:hover` explicite, l'état actif se délave au
survol — sur le moteur de recherche, le libellé blanc tombait à 1,14:1 de
contraste. Le cas s'est reproduit à l'identique sur les pastilles de la vitrine.

### Le bandeau illustré des fiches (F13.6)

`app-detail-layout` pose la **photo principale** de l'annonce en fond du bandeau
de titre — donc pour les cinq univers d'un coup. Sans photo, le dégradé de marque
d'origine reste.

⚠️ **Une balise `<img>`, jamais un `background-image`** : un fond CSS n'est
découvert qu'après la feuille de style et le calcul de style, or c'est le plus
grand élément peint de ces pages.

⚠️ **Les opacités du voile sont mesurées, pas choisies** : n'importe quel
prestataire dépose n'importe quelle image, le contraste doit tenir sur une photo
**blanche**. 0,70 en haut (fil d'Ariane à 4,53:1) → 0,88 en bas (titre à
12,34:1). Une première version à 0,45 donnait 2,32:1 au fil d'Ariane, qui est
justement l'élément le plus haut du bandeau.

### Liste d'attente et fermeture d'accès avant ouverture (F14, hors CDC)

Deux demandes du client (2026-08-14), en marge du cahier des charges.

**Liste d'attente** — `features/content/waitlist-page/`, route `/liste-attente`.
Détachée de la page statique que le client maintient lui-même sur le domaine
public (hors de ce dépôt) : 5 catégories (propriétaire, prestataire, client,
team building, diaspora) au lieu de 3, champs spécifiques par catégorie
(`WaitlistService`, `core/api/waitlist.service.ts`). ⚠️ **Volontairement pas
liée à la navigation** — accès direct par URL seulement, la bascule sur le
domaine public reste une décision du client.

**Fermeture d'accès** — `core/api/platform-gate.service.ts` +
`core/guards/platform-gate.guard.ts`. Tant que le réglage est actif au
back-office, `platformGateGuard` redirige vers `/liste-attente` quiconque n'a
pas d'accès anticipé — appliqué en `canActivateChild` sur les pages publiques
(`app.routes.ts`, exceptions via `data: { gateExempt: true }` sur liste
d'attente/contact/FAQ/pages légales) et en `canActivate` **avant** `roleGuard`
sur les 4 espaces connectés. ⚠️ **Pas de mise en cache** (à la différence de
`HeroService`) : la réponse dépend de la session courante, elle doit être
relue à chaque navigation. ⚠️ **Échec réseau = bloqué**, pas ouvert : mieux
vaut un excès de prudence qu'une plateforme fermée exposée sur un doute.
`error.interceptor.ts` gère aussi le code `423` en filet de sécurité (session
qui perd son accès en cours de route, sans nouvelle navigation pour le
révéler). `back-office` et `auth` ne portent aucun garde — l'équipe travaille
normalement quel que soit le réglage.

**Écran back-office de consultation** — `features/backoffice/liste-attente/`
(liste, `/back-office/liste-attente`) et son sous-dossier `detail/` (fiche,
`/back-office/liste-attente/:id`). Jusqu'ici une inscription n'était visible
nulle part au back-office — pas de compte, donc pas de fiche dans l'annuaire
des comptes. La liste ne montre que ce qui tient sur une ligne (nom,
catégorie, date, statut) ; la fiche restitue **tous** les champs saisis par le
prospect, y compris ceux propres à sa catégorie (`details`) et ses
précisions libres — demandé explicitement pour que l'équipe voie exactement
ce que le client a écrit. Filtrable par statut (à traiter/traitées/toutes) et
par catégorie ; bouton « Marquer traité »/« Rouvrir » présent aux deux
endroits, patron identique à l'onglet « Messages de contact » (nom cliquable
qui ouvre la fiche, agent + horodatage affichés une fois traité). Entrée
« Liste d'attente » ajoutée au rail juste après « Demandes », gardée par
`traiter:demandes` dans `backoffice-permissions.ts`. ⚠️ **Marquer une
inscription « traitée » envoie désormais une invitation par e-mail au
prospect** (côté backend, s'il a laissé une adresse) — l'écran n'a rien de
plus à faire, l'envoi est déclenché par le serveur au changement de statut.

### Actualités Kaikun & héros illustré de l'accueil (F15/F15.1, hors CDC)

Demande directe de l'utilisateur (2026-08-16), après l'audit du prototype
client (le client retenait la section « Actualités » avec vidéo de son propre
prototype). Tout se pilote depuis l'écran Paramètres du back-office
(`backoffice-settings-page.ts`) : l'onglet **Actualités**, et une section
dédiée en tête de l'onglet **Bandeaux**.

**Héros de l'accueil (F15.1)** — `core/api/home-hero.service.ts`,
**mécanisme dédié**, distinct de `HeroService` (F12, une image par page).
La demande initiale (F15) posait juste une clé `home` dans le catalogue F12 ;
l'utilisateur a demandé, en cours de session, de pouvoir charger **plusieurs**
photos ou une vidéo — changement de nature, pas un champ en plus. `home-page.ts`
lit `HomeHeroService.get()` (`heroMedia` signal) et pose soit un **diaporama**
(`heroSlideBackground` computed, un minuteur dédié `heroMinuteur` à 7 s — même
cadence que la vitrine du catalogue), soit une **vidéo** (`<video>` pour un
fichier déposé, `<iframe>` assainie pour un lien d'embed) qui **remplace
entièrement** le diaporama quand elle existe (`heroHasVideo` computed). Le
tout se pose en fond de la section `.hero` existante. ⚠️ **Le voile et la
colonne visuelle décrits ici ont depuis été retravaillés en F16** (retrait de
la signature orbitale, voile passé en navy) — voir la section F16 plus bas,
qui fait foi sur l'état actuel de `.hero`. **2 vitest neufs** sur la priorité
vidéo/diaporama (`home-page.spec.ts`).

**Section Actualités** — `core/api/news.service.ts` (lecture publique) +
`aDesActualites` (computed) dans `home-page.ts`, qui **décide seule** de ce
qui occupe la Section 2 de l'accueil : au moins un article publié → la grille
`.news-grid` s'affiche à sa place ; aucun → la grille `.univers-grid` reprend
sa place. Bascule **automatique**, pas un réglage à synchroniser — demande
explicite de l'utilisateur, avec un garde-fou produit assumé : les univers
restent de toute façon accessibles par le méga-menu de l'en-tête (F2.7),
donc rien n'est réellement perdu en navigation. ⚠️ **L'iframe d'un lien vidéo
est assainie via `DomSanitizer.bypassSecurityTrustResourceUrl`**, même modèle
de confiance que la carte Google Maps de la page Contact : la valeur vient
d'un agent `gerer:parametres`, jamais d'un visiteur.

**Back-office** — `admin.service.ts` (`news()`/`createNews()`/`updateNews()`/
`deleteNews()`, multipart avec fichiers image/vidéo) + un formulaire dans
`backoffice-settings-page.html` repris du patron « Pages » (liste + édition),
avec deux champs vidéo mutuellement informatifs : un fichier ou un lien, le
fichier l'emportant à l'affichage si les deux sont saisis. **4 vitest neufs**
sur la bascule Actualités/Univers et la photo de héros
(`home-page.spec.ts`).

### Vrai logo du client, et essai visuel du héros d'accueil (F16, hors CDC)

Le client a transmis ses fichiers logo (2026-08-16, JPEG WhatsApp, fond
blanc). Nettoyés en dehors du dépôt (fond transparent, recadrage, Pillow) et
posés en un seul asset réutilisé partout : `public/brand/logo-mark.png`.

**Marque** — chaque badge CSS (carré + lettre « K ») devient une balise
`<img src="/brand/logo-mark.png">` : `shared/components/header/`,
`shared/components/footer/`, `features/auth/auth-layout/`,
`layouts/space-layout/` (rail de l'espace client), `layouts/backoffice-layout/`
(rail du back-office — icône seule quand le rail est replié, texte « K360 »
gardé à côté quand il est ouvert), `shared/components/assistant/` (en-tête du
panneau IA). `object-fit: contain` partout, aucune déformation si une version
vectorielle remplace un jour ces PNG. Favicon (`favicon.ico`), icônes PWA
(`public/icons/`, variantes *maskable* à fond blanc) et image de partage
social (`og-image.png`) régénérées à partir du même symbole — voir
`backend/README.md`/racine pour la liste complète.

**Essai visuel du héros d'accueil — EN COURS, pas figé.** Demande du client,
relayée par l'utilisateur : « toute la plateforme doit être réactive ».
Plusieurs allers-retours en direct sur `home-page.ts/html/scss` :
- `app-orbit-hero` retiré de la colonne visuelle (`OrbitHeroComponent` sorti
  des imports du composant — le composant partagé, lui, n'est pas supprimé).
- Le voile de `.hero--image::before` est passé du lavis **crème** (F15.1) à
  un voile **navy** semi-transparent, resserré sur la moitié gauche
  (`rgba(3, 25, 63, …)`) — la photo se voit donc bien plus qu'avant, y
  compris sous le texte.
- ⚠️ **Conséquence directe** : tout `.hero-text` (titre, texte, chiffres de
  confiance) est repassé en **blanc**, et l'eyebrow (« Immobilier · Tourisme
  · Mobilité · Construction ») en **or** — un lavis clair sur photo n'aurait
  gardé aucun contraste avec le texte encre d'origine. Deux essais de couleur
  rejetés en direct par l'utilisateur avant d'arriver à l'or : d'abord un
  halo blanc en `text-shadow` (refusé, « écrit les petits textes en noir »),
  puis le bleu de marque sur l'eyebrow (refusé, manque de contraste sur navy).
- Coins arrondis essayés sur `.hero` (pour épouser ceux de `.topbar`, posés
  en F11.1) **puis retirés des deux** à la demande de l'utilisateur — les
  deux éléments sont revenus à des angles droits.
- 🔴 **Aucun test n'a été ajouté pour ce héros retravaillé** : c'est un essai
  visuel à valider avec le client avant d'être considéré comme terminé.
  `home-page.scss` reste sous le seuil bloquant (~14,8 ko / 16 ko), mais
  au-dessus du seuil d'avertissement (12 ko) — dette déjà connue, pas résolue
  ici.

### Commandes utiles

```bash
# Serveur de développement (rechargement à chaud) → http://localhost:4200/
npx ng serve

# Construire la version optimisée (résultat dans dist/)
npx ng build

# Lancer les tests
npx ng test

# Montrer le site à distance (moteur + site + adresse publique ngrok)
../scripts/demo.sh
```

> Node ≥ 22 requis. Le projet utilise `npx` (pas d'installation globale d'Angular
> CLI nécessaire).

#### La configuration `demo` (présentation à distance)

`angular.json` porte une troisième configuration, à côté de `development` et
`production` : **`demo`**. Elle ne diffère que par son fichier d'environnement,
`environment.demo.ts`, dans lequel **`apiUrl` est relative** (`/api/v1`).

C'est toute la difficulté d'une démonstration à distance : en développement,
`apiUrl` vaut `http://localhost:8000/api/v1`, une adresse qui ne veut rien dire
sur le téléphone du visiteur — le site s'ouvrirait sans jamais charger la
moindre donnée. Avec une adresse relative, tout passe par l'origine publique
unique, le serveur de développement relayant `/api` **et `/storage`** (les
photos) vers Laravel.

⚠️ `environment.demo.ts` est **généré à chaque lancement** par
[`scripts/demo.sh`](../scripts/README.md) — il porte l'adresse publique du jour —
et n'est donc pas versionné. Lancer `ng serve --configuration demo` à la main
échouera faute de ce fichier.
