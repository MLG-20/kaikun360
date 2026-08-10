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

// F8.16.a — inscrit au registre ce que Kaikun doit aux partenaires, puis rend
// exigibles les dettes dont le délai de sûreté est écoulé.
//
// ⚠️ **Lancée APRÈS `reservations:cloturer`, et ce n'est pas un détail** : une
// dette naît d'un service rendu, or c'est la clôture qui pose `terminee`. Dans
// l'ordre inverse, chaque service attendrait un jour de plus pour entrer au
// registre. L'écart d'une demi-heure laisse la première finir.
//
// ⚠️ Elle ne paie RIEN : aucun virement n'est déclenché par le serveur. Elle
// constate une dette, un agent l'exécute et pointe son justificatif.
Schedule::command('reversements:calculer')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// F11.4 — vide la corbeille des espaces utilisateurs : supprime DÉFINITIVEMENT
// les annonces qui y dorment depuis plus de 30 jours.
//
// ⚠️ C'est la moitié invisible de la corbeille. Sans cette ligne, « supprimé »
// ne veut plus rien dire : les annonces s'accumulent en base pour toujours, et
// le compte à rebours affiché à l'utilisateur (« supprimé définitivement dans
// 12 jours ») devient un mensonge.
//
// ⚠️ Passée à 04:00, donc APRÈS `reversements:calculer` : une annonce effacée
// ne doit pas disparaître sous une dette qu'on est en train de calculer.
Schedule::command('corbeille:purger')
    ->dailyAt('04:00')
    ->withoutOverlapping();
