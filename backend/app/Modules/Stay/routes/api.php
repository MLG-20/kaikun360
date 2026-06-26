<?php

use App\Modules\Stay\Http\Controllers\StayCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Stay (nuitées)
|--------------------------------------------------------------------------
|
| Héritent du préfixe "api/v1" et du middleware "throttle:api".
|
*/

// Catalogue PUBLIC des nuitées (phase B3.2). Seules les nuitées réservables
// (actives + bien publié) sont exposées.
Route::get('/stays', [StayCatalogController::class, 'index']);
Route::get('/stays/{id}', [StayCatalogController::class, 'show'])
    ->whereNumber('id');
