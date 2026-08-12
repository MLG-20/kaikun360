# Couche Paiement (B14)

Abstraction du paiement, conçue pour que **aucun module métier ne dépende d'un
PSP concret**. PayTech est aujourd'hui la seule implémentation, mais tout passe
par `PaymentProviderInterface`.

## Composants (B14.1 — socle)

- **`PaymentProviderInterface`** — contrat : `initiate`, `confirm`, `refund`,
  `status`.
- **`PaymentIntent`** — DTO renvoyé par `initiate` (`providerReference`,
  `redirectUrl`).
- **`PaytechProvider`** — implémentation HTTP, **réécrite en F8.5 sur l'API
  réelle** (voir l'encadré ci-dessous). Base unique `https://paytech.sn/api`,
  authentification par les en-têtes `API_KEY` + `API_SECRET`.
- **`App\Models\Payment`** — transaction (booking_id, provider, amount_xof,
  commission_xof, status `PaymentStatus`, mode, provider_reference,
  signature_verified, meta). Une réservation peut avoir plusieurs paiements
  (acompte, solde, remboursement).
- **`PaymentStatus::fromPaytech()`** — mappe les **`type_event`** PayTech
  (`sale_complete`, `sale_canceled`, `refund_complete`, `transfer_success`,
  `transfer_failed`) → statut interne. `null` si inconnu → l'IPN est **rejeté**,
  jamais interprété.
- **Binding** — `PaymentProviderInterface` → `PaytechProvider` (singleton) dans
  `AppServiceProvider::register()`.

## ⚠️ F8.5 — l'intégration précédente ne pouvait pas fonctionner

Écrite sur une API **supposée**, jamais confrontée au vrai PayTech. Rien ne
correspondait, et le premier appel réel aurait échoué :

| | Ancien code | PayTech réel |
|---|---|---|
| Base | `engine-sandbox.pay.tech` | `paytech.sn/api` (test **et** prod) |
| Initier | `POST /api/v1/payments` | `POST /payment/request-payment` |
| Auth | `Bearer <clé>` | en-têtes `API_KEY` + `API_SECRET` |
| Corps | `amount`, `reference`, `callback_url` | `item_name`, `item_price`, `ref_command`, `command_name`, `ipn_url`, `success_url`, `cancel_url`, `env` |
| Réponse | `id` | `token` |
| Statuts | `COMPLETED`, `DECLINED`… | `type_event` : `sale_complete`, `sale_canceled`… |
| Signature | HMAC du corps brut, en-tête `Signature` | champ `hmac_compute` **dans le corps** |
| Rembourser | `POST /api/v1/payments/{ref}/refund` | `POST /payment/refund-payment` |

⚠️ **La suite de tests était verte** : elle validait la cohérence du code avec
lui-même, pas avec le PSP. Un test qui simule le partenaire d'après le code
qu'il teste ne prouve rien — c'est le piège à retenir de cette tranche.

## Vocabulaire PayTech (à ne pas confondre avec le nôtre)

| PayTech | Chez nous |
|---|---|
| `ref_command` | `payments.reference` (NOTRE référence) |
| `token` | `payments.provider_reference` (référence PSP) |
| `item_price` | montant **demandé** |
| `final_item_price` | montant **réellement débité** (promotion, ou tirage aléatoire en test) |

## Configuration (jamais en dur)

`config/services.php` → `paytech` : `base_url`, `api_key`, `api_secret`, `env`,
`ipn_url`, `success_url`, `cancel_url`.

⚠️ **Il n'y a pas de « signing key » chez PayTech** : l'`API_SECRET` authentifie
les appels **et** signe les notifications entrantes. `PAYTECH_SIGNING_KEY` n'a
jamais existé côté PSP.

⚠️ **Sandbox et production partagent la même base** : c'est `PAYTECH_ENV`
(`test` / `prod`) qui décide.

⚠️ **En mode `test`, PayTech débite un montant ALÉATOIRE entre 100 et 150 FCFA**,
quel que soit le prix demandé. Toute réconciliation porte donc sur `item_price`,
jamais sur ce qui a été débité — sinon aucune réservation ne serait confirmée en
sandbox. Le montant débité est conservé dans `payments.meta.debited_amount_xof`
pour la trace comptable.

### Tester en local

PayTech exige une URL d'IPN **publique et en HTTPS** : `localhost` est
injoignable depuis ses serveurs. Ouvrir un tunnel, puis reporter l'URL **aux deux
endroits** — `PAYTECH_IPN_URL` dans le `.env` *et* le champ IPN du tableau de
bord PayTech :

```bash
ngrok http 8000
# → https://xxxx.ngrok-free.app/api/v1/payments/webhook
```

### Remboursement : total uniquement

⚠️ La route `refund-payment` ne prend qu'une **référence de commande**, sans
montant : **PayTech ne rembourse que la totalité**. Le back-office refuse donc
explicitement un remboursement partiel (422) au lieu d'afficher « remboursé »
pour une opération que le PSP n'exécutera jamais. Un remboursement partiel se
règle hors plateforme (Wave/OM) et se trace comme un paiement manuel.

## B14.2 — Initiation

**`POST /api/v1/payments/initiate`** (auth) — corps `{ booking_id }`.
`PaymentController` (dépend de l'interface, jamais de PayTech) : vérifie que
l'appelant est le **titulaire** de la réservation (sinon 403), qu'elle n'est ni
annulée ni déjà payée (sinon 422), crée une `Payment` (`initie`, montant +
commission figés depuis la réservation), demande l'intention au PSP puis passe à
`en_attente` avec la `provider_reference`. Renvoie `{ payment, redirect_url }`.
Panne du PSP → **502**. La confirmation n'arrive QUE par webhook vérifié (B14.3).
`Booking::payments()` / `Booking::estPayee()` ajoutés.

### Acomptes & soldes (F7.3.h)

Dernière ligne non couverte du module *Paiements* du CDC §6. La table acceptait
depuis B14 plusieurs règlements par réservation — sa migration cite « acompte,
solde » — mais **rien ne les distinguait** et aucun reste à payer n'était calculé :
devant un versement de 50 000 F sur une réservation de 180 000 F, impossible de
dire s'il s'agissait d'un acompte ou d'une erreur.

- **`payments.kind`** (enum `PaymentKind` : `integral` / `acompte` / `solde`),
  défaut `integral` — tous les règlements antérieurs étaient complets, la colonne
  est donc juste sur l'historique sans reprise de données.
- **`amount_xof` facultatif** sur l'initiation : omis, le client règle **tout ce
  qui reste dû** (comportement d'avant, préservé) ; fourni, c'est un versement
  partiel, **plafonné au reste à payer** (422 au-delà — encaisser plus créerait un
  trop-perçu à rembourser derrière).
- **La nature est DÉDUITE du montant**, jamais saisie (`Booking::natureDuReglement`) :
  ce qui laisse un reliquat est un acompte, ce qui solde après un premier versement
  est un solde, ce qui règle tout d'un coup est intégral. Un libellé choisi à la
  main finirait par mentir sur les chiffres.
- **`Booking::montantPaye()` / `resteAPayer()`** : seuls les paiements `complete`
  comptent (un règlement en attente de confirmation manuelle n'a rien apporté ; un
  remboursement sort du calcul par son statut). Le reste à payer n'est **jamais
  négatif** — un trop-perçu se règle par un remboursement, pas par une dette de la
  plateforme envers le client.
- ⚠️ **`Booking::estPayee()` a changé de sens** : il ne suffit plus d'un paiement
  encaissé, il faut que le total couvre le montant — sans quoi un acompte
  empêcherait le client de verser son solde.
- **La commission ne se prend qu'une fois**, sur le règlement qui solde : la
  répartir sur chaque acompte donnerait des arrondis qui ne retombent pas sur le
  total. Un acompte porte donc `commission_xof = 0`.
- `montantPaye()` utilise la relation **déjà chargée** quand elle l'est : sans
  cela, afficher le reste dû sur une page de 20 paiements ferait 20 requêtes de
  plus (`AdminPaymentController` pré-charge `booking.payments`).

Tests : `tests/Feature/Payment/PartialPaymentTest.php` (10 cas).

## B14.3 — Webhook (sécurité)

**`POST /api/v1/payments/webhook`** (public, signé). `PaytechWebhookVerifier`
recalcule le HMAC-SHA256 du champ `hmac_compute` — message
`{final_item_price}|{ref_command}|{api_key}`, clé `api_secret` — et compare en
temps constant (`hash_equals`). C'est la **seule** preuve d'authenticité
acceptée : elle est liée au CONTENU (montant + référence), contrairement aux
empreintes `api_key_sha256`/`api_secret_sha256` que PayTech envoie aussi.

> ⚠️ **Revue de sécurité (2026-08-12)** : une version antérieure acceptait ces
> empreintes en repli quand `hmac_compute` manquait. Ce sont des valeurs
> **constantes** d'une notification à l'autre — une seule notification
> authentique captée quelque part (log, outil de monitoring) suffisait à
> forger indéfiniment de fausses confirmations de paiement, sans jamais
> connaître l'`API_SECRET`. PoC réalisée en local : un paiement `en_attente`
> basculé en `complete` avec les seules empreintes. Le repli est supprimé —
> une notification sans `hmac_compute` valide est rejetée, point.

Ordre strict dans `PaymentWebhookController` :

1. **signature d'abord** — invalide/absente → **401**, aucun effet ;
2. transaction retrouvée par `provider_reference`/`reference` (sinon 404) ;
   `signature_verified` passe à vrai ;
3. **idempotence** — déjà `complete` → 200 sans retraitement ;
4. mapping `PaymentStatus::fromPaytech` — statut inconnu → **422** ;
5. **réconciliation de montant** — si `COMPLETED` mais montant débité ≠ attendu :
   pas de confirmation, `meta.amount_mismatch`, **202** ;
6. application du statut ; `COMPLETE` → réservation `confirmee` (sauf annulée).
   La commission encaissée est celle figée sur la `Payment`.

## B14.4 — Remboursement & supervision (back-office)

Permission `gerer:paiements`. `AdminPaymentController` (dépend de l'interface) :

- **`GET /admin/payments`** — liste paginée (booking chargé), filtres `status`,
  `booking_id`, `reference` (interne ou PSP).
- **`POST /admin/payments/{payment}/refund`** — corps `{ amount_xof? }` (total
  par défaut). Refuse un paiement non encaissé (**422**) et un montant supérieur
  au payé (**422**) ; délègue au PSP (`refund`), échec → **502** ; sinon statut
  `rembourse` + `meta.refunded_amount_xof`, tracé (Activitylog).

## B20 — Paiement manuel (Phase 1 du cahier des charges)

Avant l'obtention d'un compte marchand, la plateforme peut encaisser en **mode
manuel** : le client règle par Wave/Orange Money au numéro officiel, un admin
confirme la réception dans le back-office (cahier des charges §11, Phase 1).

- **`POST /payments/initiate`** accepte `mode` = `paytech` (défaut) ou `manuel`.
  En `manuel` : `Payment` créé (`provider=manuel`, `mode=manuel`, `en_attente`),
  **aucun appel PSP**, réponse `{ payment, instructions }` (méthode, numéro
  officiel lu depuis `Settings::get('support.phone')`, référence à mentionner).
- **`POST /admin/payments/{payment}/confirm`** (`can:gerer:paiements`) — confirme
  un paiement manuel. Garde-fous : `mode ≠ manuel` → 422, déjà `complete` → 422.
  Accepte `provider_reference?` (ID transaction Wave/OM, conservé dans `meta`).

**`PaymentConfirmationService::markCompleted(Payment, ?User $actor)`** est la
**source de vérité unique** du passage à `complete` : passe la réservation en
`confirmee` (sauf annulée), notifie le client (`BookingConfirmedNotification`),
émet l'événement n8n `booking.confirmed`, journalise « Validation de paiement ».
Appelé par le **webhook PayTech** (sans causer) ET par la **confirmation manuelle**
(avec le causer admin). Le webhook B14.3 a été refactoré pour l'utiliser — plus
de duplication de la logique de confirmation.

## Reste côté client (hors code)

- Créer le compte marchand PayTech (sandbox puis prod) et fournir les clés.
- Rejouer les tests en sandbox réel avant bascule production.

Tout le code est couvert par des tests via `Http::fake` ; **aucun module métier
ne référence PayTech**, uniquement `PaymentProviderInterface`.

> Le compte marchand PayTech et les tests sandbox réels requièrent les clés du
> client (action externe) ; le code est entièrement testé via `Http::fake`.
