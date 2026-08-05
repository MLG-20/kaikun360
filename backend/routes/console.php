<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| ⚠️ Ces tâches ne tournent que si le planificateur Laravel est lui-même
| appelé chaque minute par le système hôte. À prévoir au déploiement :
|
|     * * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
|
| Sans cette ligne de cron, les commandes ci-dessous ne s'exécutent jamais.
|
*/

// F8.15.a — fait avancer les réservations datées : « en cours » quand le
// service commence, « terminée » quand il s'achève. Une fois par jour suffit :
// la granularité du cycle est la journée, pas l'heure. Sans elle, aucune
// réservation n'atteint jamais `terminee` et personne ne peut déposer d'avis.
Schedule::command('reservations:cloturer')
    ->dailyAt('03:00')
    ->withoutOverlapping();

