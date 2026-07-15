# `core/` — Socle applicatif (singletons)

Services et briques **transverses instanciés une seule fois** pour toute
l'application (approche standalone, sans NgModule) :

- **services/** — `AuthService` (session, token en mémoire), services d'API.
- **api/** — accès HTTP typés au backend `/api/v1` :
  - `CatalogService` — catalogues publics (index paginés) **et** détail
    (`property`, `stay`, `stayAvailability` — F2.1/F2.3 ; `experience`,
    `experienceAvailability`, `vehicle` — F2.4).
  - `RequestService` — dépôt de demandes contextuelles `POST /requests`
    (demande de visite/réservation ; **auth requise**) — F2.3.
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
- **interceptors/** — `tokenInterceptor` (ajoute le Bearer), `errorInterceptor`
  (gestion centralisée : 401 → login, 422 → erreurs de formulaire, 500 → page
  d'erreur).
- **guards/** — `authGuard`, `roleGuard` (protection des routes).

Fourni via `app.config.ts` (`provideHttpClient(withInterceptors(...))`, etc.).
Ne contient **aucun composant d'interface** (voir [`../shared`](../shared)).
