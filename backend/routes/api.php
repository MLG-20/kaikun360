<?php

/*
|--------------------------------------------------------------------------
| Routes API — Kaikun 360
|--------------------------------------------------------------------------
|
| Ce fichier est le point d'entrée de toute l'API. Il est monté :
|   - sous le préfixe "api/v1"      (voir bootstrap/app.php → apiPrefix)
|   - avec le middleware "throttle:api" (limitation de débit globale)
|
| Il ne contient AUCUNE logique métier. Son seul rôle est de charger
| automatiquement les routes de chaque module présent dans app/Modules,
| pour garder une architecture modulaire et découplée.
|
| Convention : chaque module expose ses routes dans
|   app/Modules/<Module>/routes/api.php
|
*/

// Chargement automatique des routes de chaque module métier.
// glob() liste tous les fichiers de routes des modules ; on les inclut un par un.
// (Si un module n'a pas encore de routes, il n'apparaît tout simplement pas.)
foreach (glob(app_path('Modules/*/routes/api.php')) as $moduleRouteFile) {
    require $moduleRouteFile;
}

// Routes de la couche transversale (Requests, Quotes, Bookings — B11),
// qui ne relèvent d'aucun module métier.
require __DIR__.'/transversal.php';
