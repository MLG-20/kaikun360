# Couche Paiement (B14)

Abstraction du paiement, conçue pour que **aucun module métier ne dépende d'un
PSP concret**. PayTech est aujourd'hui la seule implémentation, mais tout passe
par `PaymentProviderInterface`.

## Composants (B14.1 — socle)

- **`PaymentProviderInterface`** — contrat : `initiate`, `confirm`, `refund`,
  `status`.
- **`PaymentIntent`** — DTO renvoyé par `initiate` (`providerReference`,
  `redirectUrl`).
- **`PaytechProvider`** — implémentation HTTP (moteur `engine-sandbox.pay.tech`
  en test, `engine.pay.tech` en prod ; en-tête Bearer avec la clé API boutique).
  Parsing défensif des réponses.
- **`App\Models\Payment`** — transaction (booking_id, provider, amount_xof,
  commission_xof, status `PaymentStatus`, mode, provider_reference,
  signature_verified, meta). Une réservation peut avoir plusieurs paiements
  (acompte, solde, remboursement).
- **`PaymentStatus::fromPaytech()`** — mappe AUTHORIZED/COMPLETED/DECLINED/
  CANCELLED/REFUNDED/PENDING → statut interne (null si inconnu → webhook rejeté).
- **Binding** — `PaymentProviderInterface` → `PaytechProvider` (singleton) dans
  `AppServiceProvider::register()`.

## Configuration (jamais en dur)

`config/services.php` → `paytech` : `base_url`, `api_key`, `signing_key`,
`webhook_url`, alimentés par l'environnement
(`PAYTECH_BASE_URL`, `PAYTECH_API_KEY`, `PAYTECH_SIGNING_KEY`, `PAYTECH_WEBHOOK_URL`).

## B14.2 — Initiation

**`POST /api/v1/payments/initiate`** (auth) — corps `{ booking_id }`.
`PaymentController` (dépend de l'interface, jamais de PayTech) : vérifie que
l'appelant est le **titulaire** de la réservation (sinon 403), qu'elle n'est ni
annulée ni déjà payée (sinon 422), crée une `Payment` (`initie`, montant +
commission figés depuis la réservation), demande l'intention au PSP puis passe à
`en_attente` avec la `provider_reference`. Renvoie `{ payment, redirect_url }`.
Panne du PSP → **502**. La confirmation n'arrive QUE par webhook vérifié (B14.3).
`Booking::payments()` / `Booking::estPayee()` ajoutés.

## B14.3 — Webhook (sécurité)

**`POST /api/v1/payments/webhook`** (public, signé). `PaytechWebhookVerifier`
recalcule le HMAC-SHA256 du **corps brut** avec la Signing Key et compare en
temps constant (`hash_equals`). Ordre strict dans `PaymentWebhookController` :

1. **signature d'abord** — invalide/absente → **401**, aucun effet ;
2. transaction retrouvée par `provider_reference`/`reference` (sinon 404) ;
   `signature_verified` passe à vrai ;
3. **idempotence** — déjà `complete` → 200 sans retraitement ;
4. mapping `PaymentStatus::fromPaytech` — statut inconnu → **422** ;
5. **réconciliation de montant** — si `COMPLETED` mais montant débité ≠ attendu :
   pas de confirmation, `meta.amount_mismatch`, **202** ;
6. application du statut ; `COMPLETE` → réservation `confirmee` (sauf annulée).
   La commission encaissée est celle figée sur la `Payment`.

## À venir

- B14.4 : remboursements (caution / annulation) + supervision admin
  `GET /admin/payments`.

> Le compte marchand PayTech et les tests sandbox réels requièrent les clés du
> client (action externe) ; le code est entièrement testé via `Http::fake`.
