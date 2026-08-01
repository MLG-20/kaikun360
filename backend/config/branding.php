<?php

/**
 * Identité de marque utilisée par les E-MAILS transactionnels.
 *
 * Tout ce qui est « habillage » (couleurs, coordonnées, liens de pied de page)
 * est centralisé ici : les gabarits Blade ne codent en dur aucune valeur, si
 * bien qu'un changement de charte ou d'adresse de support se fait en un seul
 * endroit. Les couleurs reprennent EXACTEMENT la charte du frontend Angular.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Identité
    |--------------------------------------------------------------------------
    */

    'name' => 'Kaikun 360',

    // Baseline affichée sous la marque dans l'en-tête des e-mails.
    'tagline' => 'La plateforme de confiance pour l\'immobilier, le séjour, la construction et la mobilité au Sénégal.',

    /*
    |--------------------------------------------------------------------------
    | Liens
    |--------------------------------------------------------------------------
    |
    | `frontend` sert à construire TOUS les liens cliquables des e-mails (boutons
    | d'action, espace client…). En local il pointe sur le serveur Angular ; en
    | production, sur le domaine public. Ne jamais utiliser APP_URL pour ça :
    | APP_URL désigne l'API, pas le site que voit l'utilisateur.
    |
    */

    'frontend' => rtrim((string) env('FRONTEND_URL', 'http://localhost:4200'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Coordonnées affichées en pied d'e-mail
    |--------------------------------------------------------------------------
    |
    | La présence de coordonnées réelles et vérifiables est un critère de
    | délivrabilité (anti-spam) autant qu'un signal de confiance : un e-mail
    | sans adresse ni téléphone est perçu comme suspect.
    |
    */

    'support_email' => env('BRAND_SUPPORT_EMAIL', 'contact@kaikun360.com'),
    'support_phone' => env('BRAND_SUPPORT_PHONE', '+221 33 000 00 00'),
    'address' => env('BRAND_ADDRESS', 'Dakar, Sénégal'),

    /*
    |--------------------------------------------------------------------------
    | Palette (charte README, cf. frontend)
    |--------------------------------------------------------------------------
    |
    | Les clients de messagerie ne comprennent ni les variables CSS ni les
    | feuilles externes : les gabarits injectent donc ces valeurs en style
    | « inline ». D'où ce tableau, seule source de vérité.
    |
    */

    'colors' => [
        'blue' => '#0348FB',   // bleu principal — boutons d'action
        'green' => '#38A774',  // vert — confirmation / succès
        'navy' => '#03193F',   // fond foncé de l'en-tête
        'navy_soft' => '#08265B',
        'gold' => '#D3AE52',   // accent premium (filet, sceau vérifié)
        'gold_soft' => '#F0D27E',
        'cream' => '#F7F4EB',  // fond de page
        'sand' => '#EFE8D8',   // bordures douces
        'ink' => '#11213C',    // texte principal
        'muted' => '#66738B',  // texte secondaire
        'danger' => '#C0392B', // alerte / sécurité
        'white' => '#FFFFFF',
    ],
];
