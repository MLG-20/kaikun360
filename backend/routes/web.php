<?php

use App\Support\Mail\MailPreview;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
