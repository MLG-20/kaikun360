# `models/` — Modèles TypeScript miroir de l'API

Interfaces TypeScript reflétant fidèlement les **API Resources** de Laravel
(contrat : `backend/API.md` + les classes `*Resource`). Elles typent les réponses
HTTP et garantissent la cohérence front/back.

## Modèles (F0.2 / F0.5)

| Modèle | Resource backend | Module |
| --- | --- | --- |
| `User`, `Profile` | `UserResource`, `ProfileResource` | Core |
| `Property` (+ `PropertyLocation`, `PropertyOwner`) | `PropertyResource` | Immo |
| `Stay` | `StayResource` (embarque `Property`) | Stay |
| `Vehicle` | `VehicleResource` | Mobility |
| `AdminVehicle` | `AdminVehicleResource` | Admin (back-office, F7.2.j) |
| `MobilityService` | `MobilityServiceResource` | Mobility |
| `AdminMobilityService` | `AdminMobilityServiceResource` | Admin (back-office, F7.2.j) |
| `Experience` | `ExperienceResource` | Explore |
| `AdminExperience` | `AdminExperienceResource` | Admin (back-office, F7.2.k) |
| `ServiceRequest` | `ServiceRequestResource` | transversal |
| `Quote` | `QuoteResource` | transversal |
| `Booking` | `BookingResource` | transversal |
| `Payment` | `PaymentResource` | Paiement |
| `Review` (+ `ReviewAuthor`) | `ReviewResource` | transversal |
| `Media` | `MediaResource` | transversal |
| `Provider` (+ `ProviderCertification`) | `ProviderResource` | Pro |
| `Faq` | `FaqResource` | Admin (contenu éditorial) |
| `ContentPage` | `PageResource` | Admin (contenu éditorial) |
| `UserDocument` (+ `DocumentTypeOption`) | `UserDocumentResource` | Core (espace client, F3.2) |

Import pratique via le barrel : `import { Property, Stay } from '../models';`
(voir `index.ts`).

## Conventions de typage

- **Montants** en FCFA = `number` (entiers).
- **Dates** = `string | null` (ISO 8601 / `YYYY-MM-DD` selon la Resource).
- **Coordonnées** (`latitude`/`longitude`) = `string | null` (cast `decimal`
  sérialisé en chaîne par Laravel).
- **Statuts / types / labels** = `string | null` (valeurs d'enums backend).
- **Relations chargées conditionnellement** (`whenLoaded`) = propriété
  **optionnelle** (`?`), ex. `Stay.property`, `Review.author`.
- **Vues back-office enrichies** = interface `Admin*` qui **étend** le modèle
  public (`AdminVehicle extends Vehicle`), jamais des champs ajoutés au modèle
  public. La distinction est volontaire : ces champs (n° d'assurance, identité
  du chauffeur…) sont des données de **contrôle**, servies derrière les gardes
  admin uniquement. Le type dit donc *où* la donnée est disponible.

> Toute évolution d'une Resource côté Laravel doit être répercutée ici.
