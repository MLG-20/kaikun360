# `manage/` — Univers Gestion locative (F2.5)

> **En une phrase :** la page qui convainc un propriétaire de **confier la
> gestion de ses biens** à Kaikun 360 et de tout suivre à distance.

---

## 1. Expliqué simplement

Une page de présentation (`/gestion-locative`) : la promesse (locataires
vérifiés, loyers et quittances, reporting à distance), les trois étapes (mandat
→ gestion du quotidien → décaissements), les bénéfices concrets, puis un
**formulaire de mise en relation**. Un gestionnaire recontacte le propriétaire
pour établir un mandat. La gestion opérationnelle réelle (mandats, quittances,
incidents, reversements) vit ensuite dans l'espace connecté et le back-office.

---

## 2. Détails techniques

- **`manage-page/`** — `ManagePageComponent`, route `/gestion-locative`. Page
  statique de présentation + le formulaire partagé
  [`app-lead-form`](../../shared/components/lead-form) (`service_type = manage`,
  champ ville affiché).
- Styles entièrement partagés : `.uni-hero`
  ([`_universe.scss`](../../../styles/_universe.scss)) + sections `.conv-*`
  ([`_conversion.scss`](../../../styles/_conversion.scss)).
