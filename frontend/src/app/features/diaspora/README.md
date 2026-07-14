# `diaspora/` — Univers Diaspora (F2.5)

> **En une phrase :** la page qui rassure un Sénégalais de l'étranger sur le fait
> qu'il peut **piloter un projet au pays sans se faire arnaquer**.

---

## 1. Expliqué simplement

Une page (`/diaspora`) construite autour du **protocole de confiance** — le cœur
du positionnement anti-arnaque de Kaikun 360 : vérification documentée, tout est
filmé et daté, numéro de suivi unique. Elle explique le principe du **référent
unique** qui coordonne tout sur place, décline les bénéfices, puis propose un
**formulaire de contact** pour être accompagné depuis l'étranger.

---

## 2. Détails techniques

- **`diaspora-page/`** — `DiasporaPageComponent`, route `/diaspora`. Page de
  présentation + le formulaire partagé
  [`app-lead-form`](../../shared/components/lead-form) (`service_type = diaspora`,
  champ ville affiché).
- Le bandeau « protocole de confiance » sur fond navy a des styles **propres**
  (`diaspora-trust-*` dans `diaspora-page.scss`) ; le reste réutilise `.uni-hero`
  et les sections `.conv-*`.
