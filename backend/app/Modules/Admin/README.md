# Module Admin — Back-office / pilotage (Phase B13)

Ce module regroupe les endpoints **transversaux de back-office** : pilotage,
supervision et paramétrage de la plateforme. Il ne porte pas de domaine métier
propre (ceux-ci vivent dans leurs modules respectifs) ; il **agrège** et
**orchestre** ce que les autres modules exposent.

Accès réservé aux profils back-office. Le socle d'autorisation est la permission
`consulter:dashboard-admin` (lecture), complétée par des permissions plus fines
pour les actions sensibles (`gerer:utilisateurs`, `gerer:parametres`,
`gerer:paiements`). Le rôle `super_admin` court-circuite tout via `Gate::before`.

## Périmètre par sous-phase

| Sous-phase | Contenu | État |
|---|---|---|
| B13.1 | Tableau de bord (`GET /admin/dashboard`) | ✅ |
| B13.2 | File de validation + validation générique par type | à venir |
| B13.3 | Gestion des utilisateurs (rôles, statut, désactivation) | à venir |
| B13.4 | Paramétrage (commissions, tarifs, FAQ, contenu, catégories) | à venir |
| B13.5 | Export comptable / reporting | à venir |
| B13.6 | Nuitées back-office + consolidation des policies | à venir |

## B13.1 — Tableau de bord

**`GET /api/v1/admin/dashboard`** (`can:consulter:dashboard-admin`) renvoie une
photographie agrégée, calculée par `Services\DashboardAggregator::snapshot()` :

```jsonc
{
  "data": {
    "queues": {                      // files de validation en attente
      "properties_pending":   0,     // biens en_attente_validation
      "vehicles_pending":     0,     // véhicules en_attente_validation
      "experiences_pending":  0,     // circuits en_attente_validation
      "providers_pending":    0      // prestataires en_attente
    },
    "today": {                       // activité du jour (date serveur)
      "requests":  0,                // demandes reçues aujourd'hui
      "bookings":  0                 // réservations créées aujourd'hui
    },
    "revenue": {                     // estimation (encaissement réel = PayTech B14)
      "gross_volume_xof": 0,         // volume des réservations non annulées
      "commission_xof":   0          // part plateforme des réservations non annulées
    },
    "alerts": {
      "reviews_to_moderate": 0,      // avis en_attente
      "open_incidents":      0       // incidents ouverts
    },
    "kpi": {
      "users_total":           0,
      "providers_validated":   0,
      "properties_published":  0,
      "bookings_total":        0
    }
  }
}
```

Chaque indicateur est une agrégation `COUNT`/`SUM` (aucune collection chargée) :
le dashboard reste léger à volume élevé. Les revenus **excluent** les
réservations annulées (statuts `BookingStatus::estAnnulee()`).
