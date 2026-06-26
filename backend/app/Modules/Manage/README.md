# Module Manage — Gestion locative

Kaikun gère des biens pour le compte de leurs propriétaires : **mandats**,
**loyers**, **incidents**, **dépenses** et **reversements**.

---

## Mandats de gestion (phase B4.1)

### Table `management_mandates`

| Champ | Rôle |
|---|---|
| `reference` (unique) | identifiant lisible du mandat |
| `property_id` | bien géré |
| `owner_id` | propriétaire du bien |
| `commission_rate` | taux de commission Kaikun (%) |
| `start_date` / `end_date` | durée du mandat |
| `status` | cf. enum `MandateStatus` |
| `terms` | conditions particulières |

### Modèle `ManagementMandate` (`app/Modules/Manage/Models/`)

- `belongsTo` Property et `belongsTo` owner (User).
- Enum `MandateStatus` : `en_attente`, `actif`, `suspendu`, `termine`.

> 🔜 À venir : loyers (B4.2), incidents & dépenses (B4.3), reversements (B4.4),
> tableau de bord & rapport mensuel (B4.5), policy & tests d'isolation (B4.6).
