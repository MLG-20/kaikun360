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
  se connecter. 👉 Détail : [`src/app/features/README.md`](src/app/features/README.md).
- ✅ **Le rendu côté serveur (SSR)** : les pages publiques sont d'abord
  **assemblées par un serveur** puis envoyées prêtes à afficher (bon pour le
  référencement Google et pour un premier affichage rapide). Voir « SSR » ci-dessous.
- ✅ **L'espace client (F3)** : l'espace personnel de la personne connectée, sous
  `/mon-espace` (menu latéral sombre, en-tête épuré). Ses écrans sont en
  place — tableau de bord, **profil** (photo, identité, coordonnées, sécurité, pièces),
  **mes demandes** (liste + détail cliquable), **réservations** (liste + détail cliquable), **favoris** (tous univers, avec le cœur du
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
  `GET/POST /experiences`) : le prestataire **dépose et suit ses prestations
  réservables** — véhicules (les 8 catégories distinctes : voiture particulière,
  touristique, navette aéroportuaire, bus, minibus, 4x4, pirogue, chauffeur) et
  **circuits touristiques** — chacune avec son **statut de validation** ; les
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

  > ⚠️ **Rendu SSR** : `/back-office` est déclaré `RenderMode.Client` dans
  > [`app.routes.server.ts`](src/app/app.routes.server.ts), comme les autres
  > espaces privés. Sans cela, le guard tournerait côté serveur (sans accès au
  > `sessionStorage`) et **déconnecterait à chaque rafraîchissement**. Tout nouvel
  > espace privé doit être ajouté à cette liste.
  > **Lancer en local** : `php artisan serve` (API `:8000`) + un **worker de file**
  > (`php artisan queue:work`, pour l'envoi des codes 2FA) + `npm start` (Angular
  > `:4200`, proxy `/api`→`:8000` via `proxy.conf.json`). Comptes de démo :
  > `php artisan db:seed --class=Database\Seeders\BackOfficeDemoSeeder` (super_admin
  > + agent ; e-mail du super_admin réglé par `BACKOFFICE_DEMO_SUPER_EMAIL`).

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
- Adresse de l'API du moteur configurée dans `src/environments/`.

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

```bash
# 1. Construire (produit dist/kaikun360/{browser,server})
npx ng build

# 2. Lancer le serveur SSR → http://localhost:4000/  (port réglable via PORT)
npm run serve:ssr:kaikun360
```

> ⚠️ **Sécurité (déploiement)** : `angular.json → build.options.security.allowedHosts`
> ne contient pour l'instant que `localhost`. **Ajouter le(s) domaine(s) de
> production** (ex. `kaikun360.sn`) dans cette liste avant la mise en ligne, sinon
> le serveur SSR renverra `400 Bad Request` (protection anti-SSRF sur l'en-tête Host).

### Commandes utiles

```bash
# Serveur de développement (rechargement à chaud) → http://localhost:4200/
npx ng serve

# Construire la version optimisée (résultat dans dist/)
npx ng build

# Lancer les tests
npx ng test
```

> Node ≥ 22 requis. Le projet utilise `npx` (pas d'installation globale d'Angular
> CLI nécessaire).
