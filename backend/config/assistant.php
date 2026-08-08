<?php

/*
|--------------------------------------------------------------------------
| Assistant Kaikun (phase F10)
|--------------------------------------------------------------------------
|
| L'assistant est HORS CAHIER DES CHARGES : c'est un ajout, et il doit pouvoir
| être coupé sans toucher au code (`ASSISTANT_ENABLED=false`) — par exemple si
| le client ne valide pas le budget des clés API, ou en cas d'incident.
|
| Le « cerveau » est interchangeable derrière un contrat unique :
|   - `rules`  : compréhension déterministe, aucune clé, aucun coût  (F10.0)
|   - `claude` : modèle de langage avec outils                        (F10.4)
|
| Les plafonds ci-dessous ne sont PAS cosmétiques. Un endpoint d'assistant est
| une porte ouverte sur l'API : sans plafond de longueur, d'historique et de
| débit, il devient un moyen de saturer le serveur — et, une fois le driver
| `claude` actif, de faire exploser la facture d'un tiers (« déni de
| portefeuille »). On les applique donc dès le socle, avant même qu'un modèle
| soit branché, pour qu'ils soient éprouvés le jour où ils protègent de l'argent.
|
*/

return [

    /*
    | Interrupteur général. À false, l'endpoint répond 503 sans rien exécuter.
    */
    'enabled' => (bool) env('ASSISTANT_ENABLED', true),

    /*
    | Cerveau actif. Toute valeur inconnue retombe sur `rules` : en cas de
    | faute de frappe en production, on dégrade vers le déterministe (qui
    | fonctionne toujours) plutôt que de tomber en erreur.
    */
    'driver' => env('ASSISTANT_DRIVER', 'rules'),

    /*
    | Garde-fous appliqués à chaque requête.
    */
    'limits' => [
        // Longueur maximale d'un message entrant (caractères). Un besoin
        // d'orientation tient largement dedans ; au-delà, c'est du remplissage
        // — et, avec le driver `claude`, des tokens payés pour rien.
        'message_length' => 500,

        // Nombre de tours d'historique acceptés dans une requête. Borne la
        // taille du contexte : sans cela, un client malveillant renvoie un
        // historique de 10 000 messages à chaque appel.
        'history_turns' => 10,

        // Nombre de résultats renvoyés par un outil. L'assistant ORIENTE, il ne
        // remplace pas la page catalogue : trois résultats et un lien profond
        // valent mieux qu'une liste que personne ne lit.
        'results_per_tool' => 3,
    ],

    /*
    | Limitation de débit dédiée (limiteur `assistant`, défini dans
    | AppServiceProvider). Volontairement plus stricte que le limiteur `api`
    | général (60/min) : une conversation humaine normale ne dépasse jamais
    | quelques messages par minute.
    */
    'rate_limit' => [
        'per_minute' => (int) env('ASSISTANT_RATE_PER_MINUTE', 12),
    ],

];
