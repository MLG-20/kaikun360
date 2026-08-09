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

    /*
    |--------------------------------------------------------------------------
    | Driver `claude` (F10.4)
    |--------------------------------------------------------------------------
    |
    | Réglages du cerveau conversationnel. Ils ne servent QUE si
    | `ASSISTANT_DRIVER=claude` : sans cela, rien de tout ceci n'est lu et la
    | plateforme n'émet aucun appel sortant.
    |
    | ⚠️ PRÉREQUIS NON TECHNIQUE : un abonnement Claude Pro NE COUVRE PAS l'API.
    | Il faut un compte d'organisation sur la Console Anthropic, au nom du
    | client, avec un plafond de dépense configuré côté Console. Les plafonds
    | ci-dessous bornent le coût d'UN message ; ils ne bornent pas le mois.
    |
    | Ordre de grandeur mesuré à la conception, pour une conversation de six
    | échanges : ~10 F CFA en Haiku 4.5, ~20 F en Sonnet 5, ~50 F en Opus 5.
    | Le modèle se change par variable d'environnement, sans redéploiement.
    |
    */
    'claude' => [
        // Clé API. Absente, le driver lève à la première requête et l'appelant
        // retombe sur le cerveau déterministe : le service continue, dégradé.
        'api_key' => env('ANTHROPIC_API_KEY'),

        // Modèle. Haiku 4.5 par défaut : l'assistant choisit un outil et résume
        // ce qu'il en reçoit — la difficulté est faible, le volume potentiel
        // élevé (l'endpoint est ouvert aux visiteurs).
        'model' => env('ASSISTANT_CLAUDE_MODEL', 'claude-haiku-4-5'),

        // Plafond de tokens produits PAR RÉPONSE. C'est le garde-fou de coût le
        // plus direct : l'invite impose deux à quatre phrases, 700 tokens les
        // couvrent largement. Un plafond serré coupe une réponse au milieu —
        // le cerveau relaie alors les résultats d'outils plutôt qu'une bulle vide.
        'max_tokens' => (int) env('ASSISTANT_CLAUDE_MAX_TOKENS', 700),

        // Nombre de tours d'appels d'outils autorisés dans un échange. Au-delà,
        // les outils sont retirés au modèle, qui doit conclure en texte : c'est
        // ce qui garantit qu'une conversation se termine toujours, en un nombre
        // d'appels facturés borné.
        'max_tool_rounds' => (int) env('ASSISTANT_CLAUDE_MAX_TOOL_ROUNDS', 3),

        // Délai d'attente (secondes). Court volontairement : passé ce délai, le
        // repli déterministe répond immédiatement, ce qui vaut mieux qu'une
        // personne devant une bulle qui tourne.
        'timeout' => (float) env('ASSISTANT_CLAUDE_TIMEOUT', 20),

        // Reprises sur erreur réseau ou surcharge du fournisseur. Une seule :
        // le repli est plus rapide qu'une seconde attente.
        'max_retries' => (int) env('ASSISTANT_CLAUDE_MAX_RETRIES', 1),
    ],

];
