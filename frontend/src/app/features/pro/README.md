# `pro/` — Kaikun Pro (F2.5)

> **En une phrase :** la page qui recrute les **prestataires et entreprises** —
> agences, artisans, chauffeurs, guides — pour rejoindre le réseau vérifié
> Kaikun 360.

---

## 1. Expliqué simplement

Une page (`/pro`) tournée vers les professionnels : les atouts de rejoindre
Kaikun 360 (demandes qualifiées, label de confiance, paiements sécurisés), à qui
elle s'adresse, et les étapes pour devenir prestataire vérifié. Contrairement
aux autres pages de conversion, l'appel à l'action n'est pas une demande de
service mais une **inscription** : les boutons mènent à
`/auth/inscription` (créer un compte pro) et `/auth/connexion`.

---

## 2. Détails techniques

- **`pro-page/`** — `ProPageComponent`, route `/pro`. Page **statique** de
  présentation (aucun appel API, aucun formulaire de demande) ; les CTA sont de
  simples `routerLink` vers l'authentification. La gestion des missions et des
  certifications vit dans l'espace connecté / back-office.
- Styles entièrement partagés : `.uni-hero` + sections `.conv-*` (grille
  d'audiences `.conv-features`, étapes `.conv-steps`, appel à l'action `.conv-cta`
  avec `.conv-cta-actions` pour les boutons).
