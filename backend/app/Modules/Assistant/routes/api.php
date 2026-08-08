<?php

use App\Modules\Assistant\Http\Controllers\AssistantController;
use App\Modules\Assistant\Http\Middleware\EnsureAssistantEnabled;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Assistant (phase F10.0)
|--------------------------------------------------------------------------
|
| Un endpoint unique, ouvert aux visiteurs comme aux comptes connectés. Le
| rôle de l'appelant ne change pas la route : il change la trousse à outils
| composée par le ToolRegistry.
|
| Route PUBLIQUE, donc doublement plafonnée :
|   - `throttle:api`       (60/min) s'applique déjà à toute l'API ;
|   - `throttle:assistant` (12/min) s'ajoute ici, car une conversation humaine
|     ne dépasse jamais quelques messages par minute. C'est la parade au « déni
|     de portefeuille » : à partir de F10.4, chaque message coûte de l'argent
|     réel, et un endpoint d'assistant non bridé est une facture ouverte à tout
|     Internet.
|
| L'authentification est FACULTATIVE (pas de middleware `auth:sanctum`) : le
| contrôleur résout l'utilisateur via le garde `sanctum` s'il porte un jeton
| valide, et le traite en visiteur sinon.
|
*/

Route::middleware([EnsureAssistantEnabled::class, 'throttle:assistant'])
    ->prefix('assistant')
    ->group(function () {
        Route::post('/messages', [AssistantController::class, 'message']);
    });
