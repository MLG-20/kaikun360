# Webhooks sortants → n8n

Contrat d'intégration entre le **backend Kaikun 360** et **n8n** (automatisation
WhatsApp, relances, alertes…). Ce document est la **source de vérité** pour
construire les scénarios n8n.

> **Principe de séparation** : le backend dit *ce qui s'est passé* (les
> événements) ; n8n décide *quoi en faire*. Aucune règle métier ne doit vivre
> dans n8n ; aucune orchestration (envoi WhatsApp, tableurs…) ne doit vivre dans
> le backend.

---

## 1. Fonctionnement

Quand un événement métier survient, le backend envoie un **POST HTTP** vers une URL
n8n unique. L'envoi est **asynchrone** (file d'attente) et **ré-essayé** (jusqu'à
5 tentatives, backoff 10s→2min) : une coupure momentanée de n8n ne perd aucun
événement.

```
Backend Laravel ──POST (signé HMAC)──► Webhook n8n ──► WhatsApp Business API / …
```

## 2. Configuration (côté backend, `.env`)

| Variable | Rôle |
| --- | --- |
| `N8N_WEBHOOK_ENABLED` | `true` pour activer l'émission automatique (défaut `false`) |
| `N8N_WEBHOOK_URL` | URL du webhook n8n (fournie par l'équipe n8n) |
| `N8N_WEBHOOK_SECRET` | Secret partagé pour la signature HMAC |

Tant que `N8N_WEBHOOK_ENABLED` est faux ou que l'URL est vide, **aucun envoi** n'a
lieu (sans erreur). Un worker de queue doit tourner en production
(`php artisan queue:work`).

## 3. Format de l'enveloppe (ce que n8n reçoit)

```json
{
  "id": "3f2a…-uuid",          // identifiant unique de livraison (déduplication)
  "event": "quote.received",   // nom de l'événement
  "occurred_at": "2026-07-12T18:00:00+00:00",
  "data": { … }                // charge utile propre à l'événement (voir §5)
}
```

En-têtes HTTP :

| En-tête | Contenu |
| --- | --- |
| `X-Kaikun-Event` | Nom de l'événement |
| `X-Kaikun-Delivery` | `id` de livraison |
| `X-Kaikun-Signature` | HMAC-SHA256 du **corps brut** avec `N8N_WEBHOOK_SECRET` |

## 4. Vérifier la signature (dans n8n)

n8n doit recalculer `HMAC-SHA256(corps_brut, secret)` et comparer à
`X-Kaikun-Signature`. Rejeter si différent. En Node (nœud *Function* / *Code*) :

```js
const crypto = require('crypto');
const raw = $binary ? $binary.data.toString() : JSON.stringify($json); // corps brut
const expected = crypto.createHmac('sha256', $env.KAIKUN_WEBHOOK_SECRET)
                       .update(raw).digest('hex');
if (expected !== $headers['x-kaikun-signature']) {
  throw new Error('Signature invalide');
}
```

> Utiliser **déduplication** sur `id` pour ignorer une éventuelle re-livraison.

## 5. Catalogue des événements

Tous les payloads incluent un bloc `user` `{ name, phone }` (destinataire) quand
il est pertinent. Le `phone` est au format international (`+221…`).

### `booking.confirmed`
Déclencheur : un paiement est confirmé et la réservation passe à *confirmée*.
```json
{
  "booking_reference": "BKG-XXXX",
  "bookable_type": "Stay",         // Stay | Vehicle | MobilityService | TourismExperience
  "amount_xof": 75000,
  "user": { "name": "Awa Diop", "phone": "+221770000000" }
}
```

### `quote.received`
Déclencheur : un agent propose un devis sur une demande.
```json
{
  "quote_reference": "QTE-XXXX",
  "request_reference": "REQ-XXXX",
  "amount_xof": 1250000,
  "user": { "name": "Awa Diop", "phone": "+221770000000" }
}
```

### `document.required`
Déclencheur : le back-office demande une pièce à un utilisateur.
```json
{
  "document_type": "cni",
  "note": "Merci de fournir une pièce d'identité lisible.",
  "user": { "name": "Awa Diop", "phone": "+221770000000" }
}
```

### `request.status_changed`
Déclencheur : le statut d'une demande de service évolue.
```json
{
  "request_reference": "REQ-XXXX",
  "from": "verification",
  "to": "visite",
  "user": { "name": "Awa Diop", "phone": "+221770000000" }
}
```

> D'autres événements pourront être ajoutés ici au fil des besoins (annulation,
> paiement échoué…). Toute évolution d'un payload existant doit rester
> **rétro-compatible** (on ajoute des champs, on n'en retire pas).

## 6. Tester sans vraie donnée

Une commande envoie une charge fictive (format réel) à l'URL n8n configurée :

```bash
php artisan webhook:test                 # liste les événements
php artisan webhook:test quote.received  # envoie un exemple à n8n
```

L'équipe n8n peut ainsi câbler et valider ses scénarios sans créer de vraies
réservations/devis.

## 7. Confidentialité (RGPD)

Les payloads contiennent des **données personnelles** (nom, téléphone). n8n doit
être hébergé de façon sécurisée et ne conserver que le strict nécessaire. Voir
[`CONFIDENTIALITE.md`](CONFIDENTIALITE.md) pour la politique de rétention.
