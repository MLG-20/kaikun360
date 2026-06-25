<?php

/*
|--------------------------------------------------------------------------
| Configuration CORS — Kaikun 360
|--------------------------------------------------------------------------
|
| Le CORS (Cross-Origin Resource Sharing) contrôle quels sites web (origines)
| ont le droit d'appeler notre API depuis un navigateur.
|
| Règle Kaikun : on n'autorise QUE les domaines officiels Kaikun, plus le
| front Angular en développement local. Les origines autorisées sont lues
| depuis la variable d'environnement CORS_ALLOWED_ORIGINS (liste séparée par
| des virgules) afin de ne JAMAIS coder les domaines en dur dans le code.
|
*/

return [

    // Chemins soumis au contrôle CORS : toute l'API + l'endpoint cookie Sanctum.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Méthodes HTTP autorisées (GET, POST, PATCH, DELETE...).
    'allowed_methods' => ['*'],

    // Origines autorisées : lues depuis l'env, avec des valeurs de dev par défaut
    // (le serveur de développement Angular tourne sur le port 4200).
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200,http://127.0.0.1:4200'))
    ))),

    // Pas de motif d'origine dynamique pour l'instant.
    'allowed_origins_patterns' => [],

    // En-têtes de requête autorisés.
    'allowed_headers' => ['*'],

    // En-têtes exposés au client JS (aucun de spécifique pour l'instant).
    'exposed_headers' => [],

    // Durée de mise en cache du pre-flight (0 = pas de cache).
    'max_age' => 0,

    // true : le front enverra des cookies (authentification Sanctum),
    // il faut donc autoriser les requêtes avec credentials.
    'supports_credentials' => true,

];
