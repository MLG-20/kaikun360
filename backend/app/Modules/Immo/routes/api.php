<?php

use App\Modules\Immo\Http\Controllers\PropertyCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Immo
|--------------------------------------------------------------------------
|
| Héritent du préfixe "api/v1" et du middleware "throttle:api".
|
*/

// Catalogue PUBLIC (phase B2.2) — aucune authentification requise.
// Seuls les biens publiés sont exposés (cf. PropertyCatalogController).
Route::get('/properties', [PropertyCatalogController::class, 'index']);
Route::get('/properties/{id}', [PropertyCatalogController::class, 'show'])
    ->whereNumber('id');
