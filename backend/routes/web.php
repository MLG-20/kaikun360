<?php

use App\Support\Mail\MailPreview;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Plan du site (F9.2)
|--------------------------------------------------------------------------
|
| Énumère pour les moteurs toutes les pages publiques ET toutes les fiches
| publiées. Voir `App\Support\Seo\SitemapBuilder` pour le détail des règles.
|
| ⚠️ **Route `web` et pas `api`** : ce document n'est pas consommé par le
| frontend Angular mais par des robots, qui le demandent tel quel, sans préfixe
| `/api/v1` ni en-tête `Accept`. Le mettre derrière l'API le rendrait
| introuvable — un plan du site s'annonce à une adresse conventionnelle.
|
| ⚠️ **Il doit être servi depuis le DOMAINE DU SITE**, pas celui de l'API : un
| moteur refuse un plan qui liste des URL d'un autre domaine que le sien. Deux
| montages possibles, l'un ou l'autre :
|   - une règle de reverse-proxy qui achemine `/sitemap.xml` vers Laravel ;
|   - le relais du serveur de rendu Angular (`frontend/src/server.ts`), qui va
|     chercher ce document et le resert sous son propre domaine. C'est le
|     montage par défaut : il fonctionne sans configuration d'infrastructure.
|
| Mis en cache une heure : le document se reconstruit à partir de six tables, et
| un robot sérieux le redemande souvent. Une heure de retard sur une annonce
| fraîche n'a aucune conséquence — l'indexation, elle, prend des jours.
*/
Route::get('/sitemap.xml', function (SitemapBuilder $builder) {
    $xml = Cache::remember('seo:sitemap', now()->addHour(), fn () => $builder->render());

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
});

/*
|--------------------------------------------------------------------------
| Prévisualisation des e-mails — ENVIRONNEMENT LOCAL UNIQUEMENT
|--------------------------------------------------------------------------
|
| Permet de relire chaque e-mail dans un navigateur, avec des données fictives
| et sans rien envoyer : c'est le seul moyen sérieux de juger la mise en page,
| le rendu mobile (réduire la fenêtre) et le mode sombre (basculer le thème du
| système). Le groupe est fermé partout ailleurs qu'en local : ces pages ne
| doivent jamais être accessibles en production.
|
|   Sommaire ............ http://127.0.0.1:8000/apercu-emails
|   Un e-mail ........... http://127.0.0.1:8000/apercu-emails/bienvenue-client
|   Sa version texte .... http://127.0.0.1:8000/apercu-emails/bienvenue-client?texte=1
|
*/
Route::middleware([])->group(function () {
    Route::get('/apercu-emails', function () {
        abort_unless(app()->environment('local'), 404);

        return view('emails.preview-index', ['items' => MailPreview::catalog()]);
    });

    Route::get('/apercu-emails/{key}', function (string $key) {
        abort_unless(app()->environment('local'), 404);

        $text = request()->boolean('texte');

        // La version texte est renvoyée en text/plain : c'est ainsi que le
        // client de messagerie l'affichera, sans interprétation HTML.
        return response(
            MailPreview::render($key, $text),
            200,
            ['Content-Type' => $text ? 'text/plain; charset=utf-8' : 'text/html; charset=utf-8'],
        );
    });
});
