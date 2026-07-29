<?php

use App\Http\Controllers\ContactController;
use App\Modules\Admin\Http\Controllers\AdminCatalogController;
use App\Modules\Admin\Http\Controllers\AdminDashboardController;
use App\Modules\Admin\Http\Controllers\AdminDocumentController;
use App\Modules\Admin\Http\Controllers\AdminDossierController;
use App\Modules\Admin\Http\Controllers\AdminGeoController;
use App\Modules\Admin\Http\Controllers\AdminPaymentController;
use App\Modules\Admin\Http\Controllers\AdminProviderController;
use App\Modules\Admin\Http\Controllers\AdminReviewController;
use App\Modules\Admin\Http\Controllers\AdminSettingsController;
use App\Modules\Admin\Http\Controllers\AdminTeamController;
use App\Modules\Admin\Http\Controllers\AdminUserController;
use App\Modules\Admin\Http\Controllers\AttendanceController;
use App\Modules\Admin\Http\Controllers\FaqController;
use App\Modules\Admin\Http\Controllers\PageController;
use App\Modules\Admin\Http\Controllers\ReferenceController;
use App\Modules\Admin\Http\Controllers\ReportExportController;
use App\Modules\Admin\Http\Controllers\StayOperationsController;
use App\Modules\Admin\Http\Controllers\ValidationQueueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Admin (back-office)
|--------------------------------------------------------------------------
|
| Endpoints transversaux de pilotage réservés aux profils back-office
| (agents / administrateurs). Le préfixe commun est "admin". Chaque route
| exige au minimum la permission `consulter:dashboard-admin` ; les actions
| sensibles (gestion des utilisateurs, paramétrage) exigeront des permissions
| plus fines dans les sous-phases suivantes (B13.3+).
|
*/

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // B13.1 — Tableau de bord : indicateurs de pilotage agrégés.
    Route::get('/dashboard', [AdminDashboardController::class, 'show'])
        ->middleware('can:consulter:dashboard-admin');

    // B13.2 — File de validation générique + décision par type de ressource.
    // L'accès back-office est gardé par `consulter:dashboard-admin` ; la
    // permission fine (valider:bien, valider:vehicule…) est vérifiée dans le
    // contrôleur selon le {type} ciblé.
    Route::get('/queue', [ValidationQueueController::class, 'index'])
        ->middleware('can:consulter:dashboard-admin');

    Route::patch('/validate/{type}/{id}', [ValidationQueueController::class, 'decide'])
        ->where('type', '[a-z]+')
        ->whereNumber('id')
        ->middleware('can:consulter:dashboard-admin');

    // F7.1.a — Équipe back-office (« poste de commandement ») : annuaire des
    // employés (agents / admins), enrôlement et pilotage rôle/statut. Niveau
    // admin (`gerer:utilisateurs`) ; garde-fous de hiérarchie dans le contrôleur.
    Route::middleware('can:gerer:utilisateurs')->group(function () {
        Route::get('/team', [AdminTeamController::class, 'index']);
        Route::post('/team', [AdminTeamController::class, 'store']);
        Route::patch('/team/{member}', [AdminTeamController::class, 'update'])
            ->whereNumber('member');

        // F7.1.b — Délégation « grant pur par personne » : le super admin coche,
        // pour chaque agent, les dossiers qu'il a le droit de traiter (12
        // permissions délégables). Le garde-fou super_admin sur la gouvernance
        // est vérifié dans le contrôleur.
        Route::get('/team/{member}/permissions', [AdminTeamController::class, 'permissions'])
            ->whereNumber('member');
        Route::put('/team/{member}/permissions', [AdminTeamController::class, 'syncPermissions'])
            ->whereNumber('member');

        // F7.1.c — Feuille de présence de l'équipe (supervision, niveau admin).
        Route::get('/attendance', [AttendanceController::class, 'sheet']);
    });

    // F7.1.c — Pointeuse : périmètre PERSONNEL (tout membre de l'équipe pointe
    // pour lui-même). Garde = accès back-office ; aucune donnée d'un autre
    // employé n'est accessible ici (les actions visent le compte connecté).
    Route::middleware('can:consulter:dashboard-admin')->group(function () {
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/attendance/me', [AttendanceController::class, 'me']);
    });

    // B13.3 — Gestion des comptes (rôles, statut, désactivation). Niveau admin.
    Route::get('/users', [AdminUserController::class, 'index'])
        ->middleware('can:gerer:utilisateurs');

    Route::get('/users/{user}', [AdminUserController::class, 'show'])
        ->whereNumber('user')
        ->middleware('can:gerer:utilisateurs');

    Route::patch('/users/{user}', [AdminUserController::class, 'update'])
        ->whereNumber('user')
        ->middleware('can:gerer:utilisateurs');
    Route::post('/users/{user}/request-document', [AdminUserController::class, 'requestDocument'])
        ->whereNumber('user')
        ->middleware('can:gerer:utilisateurs');

    // F7.2.g — Avis & qualité : file de modération des avis + supervision des
    // prestataires (note + sanctions). Les actions (moderate / warn / suspend)
    // restent servies par leurs contrôleurs d'origine (transversal / module Pro).
    Route::get('/reviews', [AdminReviewController::class, 'index'])
        ->middleware('can:moderer:avis');
    Route::get('/providers', [AdminProviderController::class, 'index'])
        ->middleware('can:valider:prestataire');

    // B13.4 — Paramétrage global (commissions, tarifs, coordonnées).
    Route::get('/settings', [AdminSettingsController::class, 'index'])
        ->middleware('can:gerer:parametres');
    Route::patch('/settings', [AdminSettingsController::class, 'update'])
        ->middleware('can:gerer:parametres');

    // B13.4 — Nomenclatures de référence en lecture seule (catégories, régions).
    Route::get('/reference', [ReferenceController::class, 'index'])
        ->middleware('can:consulter:dashboard-admin');

    // B13.5 — Export comptable & reporting consolidé (financier).
    Route::get('/reports/export', [ReportExportController::class, 'export'])
        ->middleware('can:gerer:paiements');

    // B14.4 — Supervision & remboursement des paiements.
    Route::middleware('can:gerer:paiements')->group(function () {
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::post('/payments/{payment}/refund', [AdminPaymentController::class, 'refund'])
            ->whereNumber('payment')
            ->middleware('throttle:payment');
        // B20 — Confirmation manuelle d'un paiement Wave/OM (Phase 1 du CDC).
        Route::post('/payments/{payment}/confirm', [AdminPaymentController::class, 'confirm'])
            ->whereNumber('payment')
            ->middleware('throttle:payment');
    });

    // B13.7.3 — Gestion documentaire transverse (KYC, docs biens, certifs,
    // preuves). Sensible → niveau administrateur.
    Route::get('/documents', [AdminDocumentController::class, 'index'])
        ->middleware('can:gerer:utilisateurs');

    // B13.7.1 — Navigateur des catalogues (tous statuts, supervision).
    // B13.7.2 — Dossiers de suivi (construction, gestion locative).
    Route::middleware('can:consulter:dashboard-admin')->group(function () {
        Route::get('/properties', [AdminCatalogController::class, 'properties']);
        Route::get('/vehicles', [AdminCatalogController::class, 'vehicles']);
        Route::get('/experiences', [AdminCatalogController::class, 'experiences']);

        // F7.2.j — Écran Mobilité : trajets programmés (tous statuts) avec le
        // remplissage de chaque départ. La flotte réutilise /admin/vehicles.
        Route::get('/mobility-services', [AdminCatalogController::class, 'mobilityServices']);

        // F7.2.k — Écran Tourisme : couverture par destination (agrégat). Les
        // circuits eux-mêmes réutilisent /admin/experiences.
        Route::get('/tourism/destinations', [AdminCatalogController::class, 'tourismDestinations']);

        Route::get('/construction-requests', [AdminDossierController::class, 'constructionRequests']);
        Route::get('/mandates', [AdminDossierController::class, 'mandates']);
    });

    // B13.6 — Exploitation des nuitées : calendrier + check-in/out + ménage.
    Route::middleware('can:gerer:nuitees')->group(function () {
        Route::get('/stays/calendar', [StayOperationsController::class, 'calendar']);
        Route::patch('/stay-bookings/{booking}/check-in', [StayOperationsController::class, 'checkIn'])->whereNumber('booking');
        Route::patch('/stay-bookings/{booking}/check-out', [StayOperationsController::class, 'checkOut'])->whereNumber('booking');
        Route::patch('/stay-bookings/{booking}/housekeeping', [StayOperationsController::class, 'housekeeping'])->whereNumber('booking');
    });

    // B13.4 — Contenu éditorial : FAQ & pages (édition). Lecture publique
    // exposée dans routes/transversal.php.
    Route::middleware('can:gerer:parametres')->group(function () {
        Route::get('/faqs', [FaqController::class, 'index']);
        Route::post('/faqs', [FaqController::class, 'store']);
        Route::patch('/faqs/{faq}', [FaqController::class, 'update'])->whereNumber('faq');
        Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])->whereNumber('faq');

        Route::get('/pages', [PageController::class, 'index']);
        Route::post('/pages', [PageController::class, 'store']);
        Route::patch('/pages/{page}', [PageController::class, 'update']);
        Route::delete('/pages/{page}', [PageController::class, 'destroy']);

        // F7.2.l — Référentiel géographique éditable (« Villes » du CDC §6).
        // Les régions restent en lecture seule (nomenclature nationale) : seuls
        // les départements et les communes sont administrables.
        Route::get('/geography', [AdminGeoController::class, 'tree']);

        Route::get('/communes', [AdminGeoController::class, 'communes']);
        Route::post('/communes', [AdminGeoController::class, 'storeCommune']);
        Route::patch('/communes/{commune}', [AdminGeoController::class, 'updateCommune'])->whereNumber('commune');
        Route::delete('/communes/{commune}', [AdminGeoController::class, 'destroyCommune'])->whereNumber('commune');

        Route::post('/departments', [AdminGeoController::class, 'storeDepartment']);
        Route::patch('/departments/{department}', [AdminGeoController::class, 'updateDepartment'])->whereNumber('department');
        Route::delete('/departments/{department}', [AdminGeoController::class, 'destroyDepartment'])->whereNumber('department');
    });

    // F2.8.1 — Messages de contact : consultation & traitement par l'équipe.
    // Le dépôt public est exposé dans routes/transversal.php.
    Route::middleware('can:traiter:demandes')->group(function () {
        Route::get('/contact-messages', [ContactController::class, 'index']);
        Route::patch('/contact-messages/{contactMessage}', [ContactController::class, 'update'])
            ->whereNumber('contactMessage');
    });
});
