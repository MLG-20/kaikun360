# Kaikun 360 — État d'avancement du projet

> Document de synthèse à destination du client. Il présente, en langage clair,
> **ce qui est aujourd'hui réalisé et fonctionnel** et **ce qu'il reste à
> construire**. Le détail technique complet est tenu à jour dans le
> [`README.md`](README.md) (journal de bord) et la documentation de chaque module.
>
> _Dernière mise à jour : 30 juillet 2026._

---

## 1. En quelques mots

Kaikun 360 est la plateforme qui réunit **tous les projets d'un Sénégalais ou d'un
membre de la diaspora en un seul endroit** : immobilier, séjours, tourisme,
transport, construction, gestion locative, diaspora, cohésion d'équipe et
entreprises. Son positionnement : **la confiance par la preuve** — des biens
vérifiés, un suivi documenté (filmé et daté) et un interlocuteur unique.

Le projet se compose de deux briques :

- un **moteur central (API)** qui gère toutes les données et les règles métier ;
- un **site / application web** que voient et utilisent les visiteurs et les clients.

---

## 2. L'avancement en un coup d'œil

| Brique | Statut | Détail |
| --- | --- | --- |
| **Moteur central (API)** | ✅ **Terminé** | Tous les modules métier, la sécurité, les paiements et les notifications sont opérationnels. |
| **Site public (grand public)** | ✅ **Terminé** | Accueil, 9 univers de services, pages de conversion, catalogue et recherche. |
| **Espace client** | ✅ **Terminé** | Compte, demandes, réservations, favoris, messages, profil. |
| **Espace propriétaire** | ✅ **Terminé** | Biens, dépôt/édition, photos, gestion locative, documents. |
| **Espace prestataire** | ✅ **Terminé** | Profil, certifications, disponibilités, missions, avis, revenus **et dépôt d'offres réservables** (véhicules & circuits). |
| **Espace entreprise** | ✅ **Terminé** | Demandes groupées (team building, séminaires) et suivi des devis. |
| **Back-office d'administration** | ✅ **Terminé** | L'outil interne de pilotage : les 14 modules attendus au cahier des charges, avec délégation des droits employé par employé. |
| **Finitions (référencement, performance)** | ⏳ À venir | Optimisations finales avant mise en ligne publique. |

**En résumé :** toute la partie **visible du grand public**, les **quatre
espaces personnels** (client, propriétaire, prestataire et entreprise) et
l'**outil d'administration interne** sont livrés et fonctionnels, sur un moteur
central complet. Il ne reste que les **finitions** (référencement, performance,
accessibilité) avant la mise en ligne.

---

## 3. Ce qui est livré et fonctionnel

### 3.1 Le moteur central (API) — opérationnel

Toute la logique métier est en place et **automatiquement testée** (voir §4) :

- **Comptes & rôles** : inscription, connexion (dont Google), vérification par
  code, mot de passe oublié, profils, pièces justificatives, gestion des droits
  (client, propriétaire, prestataire, agent, administrateur).
- **Les 9 univers de services** : immobilier, nuitées, tourisme, transport,
  construction, gestion locative, diaspora, cohésion d'équipe, entreprises.
- **Demandes, devis et réservations** : le parcours commun à tous les univers
  (une demande → un devis → une réservation/un suivi).
- **Gestion locative** : mandats, loyers, reversements, incidents, dépenses et
  **rapport mensuel** (commission, net à reverser).
- **Paiements** : règlement **manuel** (Wave / Orange Money au numéro officiel)
  avec confirmation par l'administrateur — le socle pour le paiement en ligne
  automatique (PayTech) est prêt, il ne reste qu'à activer le compte marchand.
- **Photos & avis** : illustration des biens/services et notes des clients.
- **Sécurité & conformité** : traçabilité des actions, protection des données
  personnelles, documents privés accessibles par liens temporaires sécurisés.
- **Notifications & automatisation** : e-mails/SMS par type d'événement et
  connexions vers les outils d'automatisation.

### 3.2 Le site public — livré

- **Page d'accueil** soignée et animée : promesse, repères de confiance,
  **grille des 9 univers**, protocole de confiance, sélection de biens,
  bandeau diaspora, simulateur de construction, statistiques, appels à l'action.
- **Une page dédiée par univers** avec catalogue **filtrable, triable et
  partageable** (l'adresse de la recherche conserve les filtres).
- **Pages de conversion** (construction, diaspora, gestion locative,
  team building…) avec formulaires de contact intelligents.
- **Fiches détaillées** (biens, séjours, véhicules, expériences) avec galerie
  photo, avis et bouton de contact WhatsApp.
- **Recherche globale** par univers, ville et budget.

### 3.3 L'espace client — livré

Espace personnel sécurisé où chaque client retrouve : ses **demandes** et leur
suivi, ses **réservations**, ses **favoris**, sa **messagerie**, ses
**notifications** et son **profil** (coordonnées, adresse, mot de passe, pièces
justificatives).

Il inclut aussi ses **projets diaspora** : un membre de la diaspora peut
**lancer un dossier piloté à distance** (achat, construction, gestion locative)
et **suivre son avancement au fil des rapports** datés (photos, vidéo,
commentaires) publiés par son référent Kaikun — le cœur de la promesse
« confiance par la preuve ».

C'est également là que le client **répond à un devis de chantier** : il reçoit
une notification dès que l'équipe lui envoie un chiffrage, consulte le détail
**lot par lot** (fondations, gros œuvre, plomberie…) avec le montant total et la
date de validité, puis **accepte ou refuse** — l'acceptation étant confirmée en
deux temps, parce qu'elle engage financièrement. Un refus n'annule rien : le
projet reste ouvert et l'équipe propose un chiffrage révisé.

### 3.4 L'espace propriétaire — livré

Espace dédié aux propriétaires de biens :

- **Tableau de bord** de gestion locative (loyers encaissés/impayés, dépenses,
  reversements, incidents).
- **Mes biens** : liste de tous les biens avec leur **statut de validation**.
- **Dépôt et édition d'un bien** : un formulaire unique, avec choix du **mode de
  location** (mensuelle, nuitées ou mixte) et **photos** (choix de la couverture).
- **Gestion locative** : mandats et **rapport mensuel** recalculable par mois.
- **Documents** : dépôt, téléchargement sécurisé et suppression des pièces
  justificatives, bien par bien.

### 3.5 L'espace prestataire — livré

Espace dédié aux professionnels (loueurs, guides, transporteurs, artisans…) :

- **Tableau de bord** : statut de validation du dossier, note moyenne,
  certifications.
- **Mes services** : descriptif de l'activité et documents de certification.
- **Mes offres** : dépôt et suivi des **prestations réservables** — véhicules
  (voiture particulière, touristique, navette aéroportuaire, bus, minibus, 4x4,
  pirogue, chauffeur) et **circuits touristiques** — chacune avec son **statut de
  validation**. Les champs de sécurité s'adaptent (assurance et chauffeur pour un
  véhicule motorisé, gilets et conformité météo pour une pirogue).
- **Disponibilités** : planning hebdomadaire et périodes d'indisponibilité.
- **Missions reçues**, **Avis reçus**, **Revenus & commissions**.

### 3.6 L'espace entreprise — livré

Espace dédié aux entreprises, ONG, écoles et institutions : dépôt d'une
**demande groupée** (team building, séminaire) avec participants, lieu, dates et
budget, **suivi des demandes et des devis**, messagerie et profil.

### 3.7 Le back-office d'administration — livré

L'outil interne de l'équipe Kaikun, conçu comme une **salle de contrôle** à part
entière : son propre accès, sa propre identité visuelle, et un niveau de
sécurité renforcé — c'est là que tout passe.

- **Connexion en deux étapes** (mot de passe + code envoyé par e-mail) et
  **session courte** pour tous les comptes d'administration.
- **Poste de commandement de l'équipe** : annuaire des employés, enrôlement d'un
  nouvel agent par invitation, et **pointeuse** (entrée/sortie, feuille de
  présence mensuelle, export).
- **Délégation des droits dossier par dossier** : un agent ne reçoit *que* ce
  qu'on lui confie. Chacun ne voit dans son menu que les rubriques qui lui sont
  ouvertes — un agent chargé des séjours n'a ni les paiements ni les réglages
  sous les yeux.
- **Les 14 domaines du cahier des charges**, chacun avec son écran et ses
  actions : tableau de bord, comptes & documents, biens immobiliers, nuitées,
  gestion locative, construction, mobilité, tourisme, team building, diaspora,
  paiements, avis & qualité, paramètres & contenu.
- Quelques exemples concrets de ce que l'équipe fait depuis cet outil : valider
  ou refuser une annonce (et corriger une annonce mal saisie, ou l'archiver),
  enregistrer une arrivée et un départ de séjour puis trancher la caution,
  encaisser un loyer et préparer un reversement, chiffrer un chantier lot par
  lot et y affecter les artisans, confirmer un paiement Wave/Orange Money,
  modérer un avis, sortir l'**export comptable** de la période.

---

## 4. Qualité, confiance et expérience

- **Fiabilité** : le moteur central est couvert par **plus de 670 tests
  automatisés** qui vérifient à chaque évolution que rien n'est cassé.
- **Sécurité** : accès par rôle strictement cloisonné (chaque espace est
  indépendant), documents privés servis par liens temporaires, journal
  d'activité.
- **Design** : identité visuelle cohérente (charte Kaikun), interface moderne,
  **animations soignées** (apparitions au défilement, survols premium,
  signature « orbitale » du hero).
- **Confort de travail au quotidien** : dans le back-office comme dans les
  quatre espaces, le **menu latéral se replie d'un clic** en une colonne
  d'icônes et rend la largeur au contenu — utile sur un petit portable, face à
  des tableaux larges. Le choix est **mémorisé** : on ne le refait pas à chaque
  connexion. Les menus et barres ont par ailleurs été harmonisés en **surfaces
  arrondies**, pour une lecture plus douce des écrans de travail.
- **Responsive** : l'affichage s'adapte proprement au **téléphone, à la tablette
  et à l'ordinateur** (vérifié à plusieurs largeurs d'écran).
- **Accessibilité** : les animations se désactivent pour les personnes qui le
  demandent ; navigation clavier prise en compte.

---

## 5. Ce qu'il reste à construire (feuille de route)

1. **Finitions** — référencement (SEO), performance et accessibilité avant la
   mise en ligne publique. C'est la dernière étape de développement.

Quelques points volontairement laissés de côté, arbitrés en cours de route et
signalés directement dans les écrans concernés (aucun ne bloque l'exploitation) :
la création d'un bien *à la place* d'un propriétaire et son transfert à un autre
compte ; le rattachement d'un guide nommé à un circuit précis ; la modification
des catégories de services (elles portent des règles de calcul) ; la restitution
*partielle* d'une caution ; le dépôt d'un contrat de mandat scanné.

---

## 6. À préparer par le client (accès & prérequis externes)

Ces éléments ne sont **pas des développements manquants** : le code est déjà
prêt à les utiliser. Ce sont des **comptes, numéros et accès à obtenir** auprès
de services externes pour la mise en ligne. Il est utile de commencer à les
préparer dès maintenant, car certains (nom de sender SMS, compte marchand)
demandent des délais de validation.

### 6.1 Nom de domaine & hébergement

- **Nom de domaine** : réserver le domaine officiel (ex. `kaikun360.sn` et/ou
  `.com`). C'est l'adresse publique du site et la base des e-mails.
- **Hébergement** : un serveur capable de faire tourner l'application
  (environnement PHP/Laravel + base de données MySQL + Redis) et servir le site.
- **Certificat de sécurité (HTTPS)** : généralement inclus par l'hébergeur
  (indispensable pour les paiements et la confiance).

### 6.2 E-mails professionnels (envois automatiques)

La plateforme envoie des e-mails (vérification de compte, notifications,
confirmations…). Il faut donc :

- une **adresse d'expédition professionnelle** (ex. `bonjour@kaikun360.sn`,
  `no-reply@kaikun360.sn`) ;
- un **service d'envoi d'e-mails** (SMTP) : soit celui de l'hébergeur, soit un
  service dédié (Brevo/Sendinblue, Mailgun, Amazon SES…) pour une bonne
  délivrabilité. → nous fournir l'adresse, l'identifiant et le mot de passe.

### 6.3 Numéros professionnels (contact & WhatsApp)

- **Numéro WhatsApp de support** : le numéro professionnel vers lequel pointe le
  bouton « Contacter sur WhatsApp » du site. → nous communiquer le numéro.
- **Adresse e-mail de support** affichée sur la page Contact.
- _(Ces deux informations se règlent ensuite dans l'administration, sans
  nouveau développement.)_

### 6.4 SMS (vérification & notifications) — Orange / Sonatel

Pour l'envoi de SMS (codes de vérification, alertes) :

- ouvrir un compte sur le **portail développeur Orange** (`developer.orange.com`,
  API SMS) et obtenir les identifiants d'accès ;
- faire **valider un nom d'expéditeur** (ex. `KAIKUN360`) — cette validation peut
  prendre quelques jours.
- _(Une alternative internationale, Twilio, est également prise en charge.)_

### 6.5 Paiement en ligne — PayTech

Le règlement **manuel** (Wave / Orange Money au numéro officiel, confirmé par
l'administrateur) fonctionne déjà. Pour le **paiement en ligne automatique** :

- ouvrir un **compte marchand PayTech** (d'abord en bac à sable/test, puis
  demande d'activation en production) ;
- nous transmettre les **clés d'accès** (clé d'API et clé de signature).
- → le module est prêt, il ne reste qu'à brancher ces clés et tester.

### 6.6 Automatisation & WhatsApp — n8n

La plateforme peut **déclencher des automatisations** à chaque événement
important (nouvelle demande, changement de statut…), notamment l'envoi de
messages WhatsApp, via l'outil **n8n** :

- disposer d'une **instance n8n** (hébergée par le client ou en service géré) ;
- nous fournir son **adresse (URL)** ; une clé secrète de sécurité est générée
  pour fiabiliser les échanges.
- l'**automatisation WhatsApp** (envoi de messages sortants) se met en place dans
  n8n, connectée à un compte WhatsApp Business.

### 6.7 Connexion avec Google (optionnel)

Pour proposer le bouton « Se connecter avec Google » :

- créer un **identifiant OAuth** dans la **Google Cloud Console** et nous le
  communiquer.
- _(Tant qu'il n'est pas fourni, le bouton reste simplement masqué ; la
  connexion classique par e-mail/téléphone fonctionne normalement.)_

### Récapitulatif — checklist des accès à réunir

| # | Élément | Où l'obtenir | Priorité |
| --- | --- | --- | --- |
| 1 | Nom de domaine | Registraire (.sn via NIC Sénégal, ou .com) | 🔴 Haute |
| 2 | Hébergement + HTTPS | Hébergeur (PHP/MySQL/Redis) | 🔴 Haute |
| 3 | Adresse e-mail pro + service d'envoi (SMTP) | Hébergeur ou Brevo/Mailgun/SES | 🔴 Haute |
| 4 | Numéro WhatsApp de support + e-mail de contact | Le client | 🟠 Moyenne |
| 5 | Accès SMS + nom d'expéditeur validé | developer.orange.com | 🟠 Moyenne |
| 6 | Compte marchand + clés PayTech | paytech.sn | 🟠 Moyenne |
| 7 | Instance n8n + compte WhatsApp Business | Client / service géré | 🟢 Selon besoin |
| 8 | Identifiant Google OAuth | Google Cloud Console | 🟢 Optionnel |

---

_Pour toute question sur ce document ou une démonstration, nous restons
disponibles._
