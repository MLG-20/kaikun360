# `core/` — Socle applicatif (singletons)

Services et briques **transverses instanciés une seule fois** pour toute
l'application (approche standalone, sans NgModule) :

- **services/** — `AuthService` (session ; jeton en `sessionStorage`, réhydraté et
  revalidé au démarrage), services d'API.
- **api/** — accès HTTP typés au backend `/api/v1` :
  - `CatalogService` — catalogues publics (index paginés) **et** détail
    (`property`, `stay`, `stayAvailability` — F2.1/F2.3 ; `experience`,
    `experienceAvailability`, `vehicle` — F2.4).
  - `RequestService` — dépôt de demandes contextuelles `POST /requests`
    (demande de visite/réservation ; **auth requise**) — F2.3 ; **et suivi** des
    demandes du client connecté `GET /requests/my` (paginé) — F3.3.
  - `BookingService` — réservations du client `GET /bookings/my` (paginé) et
    **annulation** propre à l'univers `PATCH /vehicles|experiences/bookings/{id}/cancel`
    (véhicules et expériences uniquement) — F3.4.
  - `FavoriteService` — favoris du client `GET /favorites` (paginé) et
    ajout/retrait `POST`/`DELETE /properties/{id}/favorite` (biens immobiliers) — F3.5.
  - `ReviewService` — avis publiés `GET /reviews` d'une entité notée — F2.3.
  - `WhatsAppService` — lien WhatsApp contextuel `GET /whatsapp/link` (message
    prérempli + numéro de support paramétré côté back-office) — F2.6.
  - `QuoteService` — consultation & réponse à un devis `GET`/`PATCH /quotes/{id}`
    (accepter/refuser ; **auth requise**) — F2.7.
  - `ProviderService` — inscription prestataire `POST /providers` (**auth +
    compte vérifié**) et suivi `GET /providers/mine` (404 = pas encore
    inscrit) — F2.7.
  - `GeoService` — référentiel géographique `GET /regions|departments|communes`
    pour les sélecteurs en cascade (région → département → commune) — F2.7.
  - `PropertyManagementService` — dépôt de bien `POST /properties` (**auth +
    compte vérifié** ; le bien part en file de validation) — F2.7.
  - `ConstructionService` — simulateur de budget `POST /construction-requests/simulate`
    (**public**) : chiffrage complet (travaux, frais annexes, foncier, délai,
    jalons, rentabilité) calculé et barème géré côté backend — F2.5 (enrichi).
  - `ContentService` — contenu éditorial public : FAQ publiée `GET /faqs` et
    pages de contenu par slug `GET /pages/{slug}` (le backend enveloppe la page
    sous `data.page` ; le service l'aplatit) — F2.8.
  - `ContactService` — envoi d'un message de contact `POST /contact` (**public**,
    throttlé) traité par l'équipe, **et** coordonnées publiques du siège
    `GET /contact-info` (adresse + lat/long pour la carte, issues des réglages
    back-office) — F2.8.1.
  - `AccountService` — compte de l'utilisateur connecté (espace client, F3.2 /
    F3.2b) : profil frais `GET /users/me`, mise à jour `PATCH /users/me`
    (renvoie l'utilisateur **+ les canaux à re-vérifier** si e-mail/téléphone ont
    changé), **changement de mot de passe** `PATCH /users/me/password`,
    suppression (anonymisation RGPD) `DELETE /users/me`, et pièces justificatives
    `GET`/`POST /users/me/documents` (dépôt **multipart** PDF/JPG/PNG).
- **interceptors/** — `tokenInterceptor` (ajoute le Bearer), `errorInterceptor`
  (gestion centralisée : 401 → login, 422 → erreurs de formulaire, 500 → page
  d'erreur).
- **guards/** — `authGuard`, `roleGuard` (protection des routes).
- **state/** — état et cycles de vie transverses :
  - `favorite-store.ts` — les favoris du client, partagés entre écrans.
  - `poll-while-visible.ts` (F8.12.a) — **relève périodique** d'un écran ouvert :
    rappelle une fonction à intervalle **tant que l'onglet est visible**, avec un
    battement immédiat au retour sur l'onglet, et **rien du tout en SSR** (un
    intervalle empêcherait la réponse serveur de se terminer). Le nettoyage est
    branché sur le `DestroyRef` local — à appeler depuis un contexte d'injection,
    il n'y a rien à défaire à la main. ⚠️ Ce n'est **pas** du temps réel : c'est
    le choix assumé de ne pas exiger un démon WebSocket permanent pour un canal
    où l'on écrit une phrase toutes les deux minutes. Utilisé par les deux fils
    de discussion (10 s) et les deux listes de la messagerie (30 s) ; l'appelant
    doit ne redemander que le nouveau (`?after=`).

Fourni via `app.config.ts` (`provideHttpClient(withInterceptors(...))`, etc.).
Ne contient **aucun composant d'interface** (voir [`../shared`](../shared)).
