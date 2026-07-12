# Notifications — canal SMS

Ce dossier fournit un **canal de notification `sms`** branché sur une abstraction
(`SmsProviderInterface`), pour que le code métier ne dépende jamais d'un
fournisseur concret (B16.1 / B18.2).

## Fournisseurs disponibles

Le fournisseur actif est choisi par la variable d'environnement `SMS_PROVIDER` :

| `SMS_PROVIDER` | Classe | Usage |
| --- | --- | --- |
| `log` *(défaut)* | `LogSmsProvider` | Dev : écrit le SMS dans les logs, n'envoie rien |
| `twilio` | `TwilioSmsProvider` | International (Basic Auth SID/token) |
| `orange` | `OrangeSmsProvider` | **Sénégal (Orange/Sonatel)** — recommandé |

Le binding se fait dans `AppServiceProvider` ; le canal `sms` est enregistré via
`Notification::resolved(...)->extend('sms', …)`.

## Activer Orange / Sonatel (B18.2)

1. Sur **developer.orange.com**, créer une application, puis **souscrire la
   « SMS API »** (bouton *Ajouter une API*). Cela active l'`client_id` /
   `client_secret`. Un **quota de test (sandbox)** est disponible pour valider
   l'intégration sans contrat.
2. Renseigner dans `.env` :

   ```env
   SMS_PROVIDER=orange
   ORANGE_SMS_CLIENT_ID=…
   ORANGE_SMS_CLIENT_SECRET=…
   ORANGE_SMS_SENDER_ADDRESS=+221XXXXXXXXX   # numéro expéditeur autorisé
   ORANGE_SMS_SENDER_NAME=KAIKUN360          # expéditeur alphanumérique (optionnel)
   # base_url / token_url ont des valeurs par défaut Orange, surchargeables si besoin
   ```

3. Vérifier un envoi réel : déclencher une notification SMS (ex. code de
   vérification) et contrôler la réception.

### Fonctionnement d'`OrangeSmsProvider`

- **OAuth2 `client_credentials`** : jeton d'accès obtenu par Basic Auth
  (`client_id:client_secret`) sur `token_url`, **mis en cache** jusqu'à peu avant
  son expiration (un seul appel d'auth pour plusieurs SMS). Un échec d'auth n'est
  jamais mis en cache.
- **Envoi** : POST au format Orange (GSMA OneAPI) sur
  `…/smsmessaging/v1/outbound/{senderAddress}/requests`, jeton `Bearer`.
- Renvoie `false` (et journalise) si non configuré ou en cas d'échec ; le canal
  reste non bloquant pour la requête HTTP (envoi asynchrone via la file B16).

> Testé via `Http::fake` (`tests/Feature/Notification/OrangeSmsProviderTest`) —
> aucune clé réelle nécessaire pour la suite de tests.
