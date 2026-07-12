# `shared/` — Composants d'interface réutilisables

Composants, pipes et directives **standalone** réutilisés dans plusieurs
fonctionnalités : cartes de bien/service, galerie photo, badges de vérification,
boutons d'appel à l'action (CTA), états de chargement, etc.

Règle : le `shared/` ne dépend **jamais** d'une fonctionnalité précise
([`../features`](../features)) ni de la logique métier de session ; il expose des
composants « présentiels » pilotés par leurs `@Input()` / `@Output()`.
