# `team-building/` — Univers Team building (F2.5)

> **En une phrase :** la page qui donne à une entreprise l'envie d'organiser son
> **séminaire ou sa journée de cohésion** clés en main avec Kaikun 360.

---

## 1. Expliqué simplement

Une page (`/team-building`) : la promesse (organisation clés en main partout au
Sénégal, prestataires vérifiés), un aperçu des **formules types** (journée
cohésion, séminaire résidentiel, incentive), les trois étapes, puis un
**formulaire de demande de devis** (effectif, dates, objectifs).

---

## 2. Détails techniques

- **`team-building-page/`** — `TeamBuildingPageComponent`, route
  `/team-building`. Page de présentation + le formulaire partagé
  [`app-lead-form`](../../shared/components/lead-form)
  (`service_type = team_building`, champ ville affiché).
- Styles entièrement partagés : `.uni-hero` + sections `.conv-*` (grille de
  formules `.conv-features`, étapes `.conv-steps`, appel à l'action `.conv-cta`).
