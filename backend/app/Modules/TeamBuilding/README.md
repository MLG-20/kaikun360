# Module Team Building — Packs groupe entreprise

Une entreprise décrit un besoin de **team building** ; Kaikun compose un **devis
multi-prestataires** (lieu + hébergement + restauration + activité + mobilité +
animation) agrégeant plusieurs modules.

> ℹ️ Le devis est ici **scopé au module** (`TeamBuildingQuote`). La couche
> transversale **Quotes (B11)** généralisera la notion de devis (état, polymorphe).

---

## Demandes de team building (phase B9.1)

### Table `team_building_requests`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`TBR-…`) |
| `company_id` | entreprise à l'origine |
| `participants` | nombre de participants |
| `city` | ville de l'événement |
| `start_date` / `end_date` | dates |
| `budget_xof` | budget indicatif (facultatif) |
| `needs` (json) | besoins structurés (hébergement, restauration, activité, mobilité, animation) |
| `description` | descriptif libre |
| `status` | enum `TeamBuildingRequestStatus` (`nouveau` → `en_etude` → `devis_envoye` → `accepte` / `annule`) |

### Modèle `TeamBuildingRequest`

- `belongsTo` company (User), `hasMany` `quotes` (TeamBuildingQuote, B9.2).
- Casts : `needs` array, dates, `participants`/`budget_xof` entiers, `status` enum.

---

## Devis composés multi-prestataires (phase B9.2)

### Table `team_building_quotes`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible (`TBQ-…`) |
| `request_id` | demande rattachée |
| `lines` (json) | lignes (catégorie, libellé, module, quantité, prix unitaire, montant) |
| `subtotal_xof` / `margin_rate` / `margin_xof` / `total_xof` | totaux figés |
| `status` | enum `TeamBuildingQuoteStatus` (`brouillon` → `envoye` → `accepte`/`refuse`) |
| `sent_at` / `accepted_at` | horodatages métier |

### Service `TeamBuildingQuoteComposer`

- `buildLines(components)` : normalise chaque composant en ligne (montant =
  quantité × prix unitaire). Catégories via enum `QuoteLineCategory` (lieu,
  hébergement, restauration, activité, mobilité, animation) → agrège plusieurs
  modules (Stay/Manage, Explore, Mobility, animation).
- `totals(lines, marginRate)` : sous-total + **marge** (`DEFAULT_MARGIN_RATE`
  15 %) + total.
- `composeFor(request, components, marginRate?)` : persiste un devis `brouillon`.

---

## Endpoints, events & policy (phase B9.3)

| Méthode | URL | Accès |
|---|---|---|
| POST | `/api/v1/team-building-requests` | entreprise — dépôt (event `TeamBuildingRequestCreated`) |
| GET | `/api/v1/team-building-requests/mine` | entreprise — mes demandes |
| GET | `/api/v1/team-building-requests` | back-office (`can:consulter:dashboard-admin`) — file d'attente |
| GET | `/api/v1/team-building-requests/{id}` | entreprise propriétaire ou admin (policy `view`) |
| GET | `/api/v1/team-building-requests/{id}/quotes` | policy `view` |
| POST | `/api/v1/team-building-requests/{id}/quotes` | admin (policy `manage`) — compose un devis |
| PATCH | `/api/v1/team-building-quotes/{quote}/send` | admin — envoie (event `QuoteSent`) |
| PATCH | `/api/v1/team-building-quotes/{quote}/accept` | entreprise (policy `accept`) — accepte (event `QuoteAccepted`) |

### Events & listeners (enregistrés dans `AppServiceProvider`)

- `TeamBuildingRequestCreated` → `NotifyAdminsOfTeamBuildingRequest` (file
  d'attente admin : permission `consulter:dashboard-admin`).
- `QuoteSent` → `NotifyCompanyOfQuoteSent` (notifie l'entreprise). La
  `TeamBuildingQuoteSentNotification` émet sur **deux canaux** : `mail` (trace) et
  `database` (F6 — alimente la cloche + l'écran « Notifications » de l'espace
  entreprise ; `action_url` = `/espace-entreprise/demandes/{request_id}`).
- `QuoteAccepted` → `StartOperationalFollowUp` (amorce le suivi opérationnel
  multi-prestataires ; orchestration concrète via Bookings/Quotes B11).

### Policy `TeamBuildingRequestPolicy`

`view` = entreprise propriétaire ou admin ; `manage` (composer/envoyer) = admin ;
`accept` = entreprise propriétaire. Un devis n'est acceptable que s'il est `envoye`.
