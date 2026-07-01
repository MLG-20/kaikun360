# Module Pro — Marketplace prestataires

Formalise le **prestataire marketplace** : inscription avec documents de
certification, validation par un agent, charte qualité (avertissements/sanctions),
missions affectées et commission. La validation d'un prestataire synchronise le
`verification_status` de son profil (Core), ce qui débloque la publication de
services publics (Explore B6, Mobility B7).

---

## Profil prestataire & certifications (phase B10.1)

### Table `providers` (1–1 avec `users`)

| Champ | Rôle |
|---|---|
| `user_id` (unique) | utilisateur prestataire |
| `business_name` | raison sociale |
| `category` | enum `ProviderCategory` |
| `bio` | présentation |
| `status` | enum `ProviderStatus` (`en_attente`/`valide`/`refuse`/`suspendu`) |
| `validated_at` / `validated_by` | traçabilité de validation |
| `warnings_count` / `sanction_note` | charte qualité |
| `rating_avg` / `rating_count` | note agrégée (remplie par Reviews, B12) |

### Table `provider_certifications`

`provider_id`, `name`, `issuer`, `file_path` (disque privé), `verified` — les
justificatifs fournis à l'inscription.

### Modèles

- `Provider` : `belongsTo` user, `hasMany` certifications + missions (B10.3),
  helper `isValidated()`, casts enums.
- `ProviderCertification` : `belongsTo` provider.

### Enums

- `ProviderStatus` : `en_attente` → `valide` (+ `refuse`, `suspendu`).
- `ProviderCategory` : restauration, animation, guide, transport, événementiel,
  artisanat, autre.

> 🔜 À venir : inscription + validation + charte qualité + policy (B10.2) ;
> missions & commission (B10.3). Notation → module Reviews (B12).
