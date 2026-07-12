<?php

use App\Modules\Immo\Http\Controllers\FavoriteController;
use App\Modules\Immo\Http\Controllers\PropertyCatalogController;
use App\Modules\Immo\Http\Controllers\PropertyManagementController;
use App\Modules\Immo\Http\Controllers\PropertyValidationController;
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

// Comparaison de biens (phase B2.5) — public. Défini avant /properties/{id}
// (de toute façon "compare" n'est pas numérique).
Route::get('/properties/compare', [PropertyCatalogController::class, 'compare']);

// Favoris de l'utilisateur connecté (phase B2.5).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/properties/{property}/favorite', [FavoriteController::class, 'store'])
        ->whereNumber('property');
    Route::delete('/properties/{property}/favorite', [FavoriteController::class, 'destroy'])
        ->whereNumber('property');
});

// Gestion privée par le propriétaire (phase B2.3) — auth requise.
// NB : "/properties/mine" est défini ici ; il ne percute pas "/properties/{id}"
// car ce dernier est contraint aux identifiants numériques (whereNumber).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/properties/mine', [PropertyManagementController::class, 'mine']);
    Route::post('/properties', [PropertyManagementController::class, 'store'])->middleware('verified.account');
    Route::patch('/properties/{property}', [PropertyManagementController::class, 'update'])
        ->whereNumber('property');
    Route::post('/properties/{property}/documents', [PropertyManagementController::class, 'storeDocument'])
        ->whereNumber('property');
});

// Validation des biens par les agents (phase B2.4) — permission valider:bien.
Route::middleware(['auth:sanctum', 'can:valider:bien'])->group(function () {
    Route::patch('/properties/{property}/approve', [PropertyValidationController::class, 'approve'])
        ->whereNumber('property');
    Route::patch('/properties/{property}/reject', [PropertyValidationController::class, 'reject'])
        ->whereNumber('property');
});

// Détail public d'un bien publié (défini APRÈS /properties/mine).
Route::get('/properties/{id}', [PropertyCatalogController::class, 'show'])
    ->whereNumber('id');

// Téléchargement d'un document de bien : URL signée temporaire uniquement.
Route::get('/properties/{property}/documents/{document}/download', [PropertyManagementController::class, 'downloadDocument'])
    ->name('properties.documents.download')
    ->whereNumber(['property', 'document'])
    ->middleware('signed');
