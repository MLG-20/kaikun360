# `models/` — Modèles TypeScript miroir de l'API

Interfaces TypeScript reflétant les **API Resources** de Laravel (contrat défini
dans `backend/API.md`) : `Property`, `Stay`, `Vehicle`, `Experience`,
`ServiceRequest`, `Quote`, `Booking`, `Payment`, `User`, `Review`, `Media`, etc.

Ces types font foi pour le typage des réponses HTTP et garantissent la cohérence
entre le frontend et le backend. Toute évolution d'une Resource côté Laravel doit
être répercutée ici.
