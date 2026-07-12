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

## À venir

- B14.2 : `POST /payments/initiate` (intention + redirection).
- B14.3 : `POST /payments/webhook` avec **vérification de signature HMAC-SHA256
  obligatoire**, mapping → Payment/Booking, commission, réconciliation de montant.
- B14.4 : remboursements (caution / annulation) + supervision admin
  `GET /admin/payments`.

> Le compte marchand PayTech et les tests sandbox réels requièrent les clés du
> client (action externe) ; le code est entièrement testé via `Http::fake`.
