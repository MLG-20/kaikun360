<?php

/**
 * Messages de validation en français (dette soldée le 2026-08-06).
 *
 * ⚠️ **POURQUOI CE FICHIER N'EXISTAIT PAS, ET CE QUE ÇA COÛTAIT.**
 * Le projet tournait bien en locale **`fr`** (c'était réglé dans `.env` depuis
 * le début) — mais **aucun dossier `lang/` n'existait**, et le repli était lui
 * aussi `fr`. Laravel ne résout un message que s'il trouve une traduction : ni
 * dans la locale, ni dans le repli, il renvoie donc la **clé brute**. Résultat
 * vérifié sur le serveur réel :
 *
 *     POST /api/v1/contact  →  {"message":"validation.required", …}
 *
 * `/contact` est un endpoint **public**, et la page Contact est un canal de
 * conversion prioritaire du cahier des charges (§4.1) : de **vrais visiteurs**
 * voyaient « validation.required » au lieu de « Le nom est obligatoire ».
 *
 * ⚠️ **Le défaut était invisible sur les écrans les plus soignés.** Les
 * `FormRequest` qui définissent leurs propres `messages()` (`RegisterRequest`
 * par exemple) répondaient correctement en français — d'où l'impression que tout
 * allait bien. Seuls les endpoints s'appuyant sur les messages **par défaut**
 * fuyaient leurs clés.
 *
 * ⚠️ **`APP_FALLBACK_LOCALE` est passé de `fr` à `en`** dans le même geste, et
 * c'est la moitié de la correction. Laravel embarque ses propres traductions
 * anglaises : une clé oubliée ici retombe désormais sur une phrase anglaise
 * lisible, jamais sur `validation.quelquechose`. Tant que le repli valait `fr`,
 * il pointait sur un dossier vide — il ne rattrapait rien.
 *
 * Les `messages()` explicites déjà écrits dans les FormRequests **priment**
 * toujours sur ce fichier : rien de l'existant n'est modifié.
 */
return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'accepted_if' => 'Le champ :attribute doit être accepté quand :other vaut :value.',
    'active_url' => 'Le champ :attribute doit être une URL valide.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'alpha' => 'Le champ :attribute ne doit contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne doit contenir que des lettres, des chiffres, des tirets et des tirets bas.',
    'alpha_num' => 'Le champ :attribute ne doit contenir que des lettres et des chiffres.',
    'array' => 'Le champ :attribute doit être une liste.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'date_equals' => 'Le champ :attribute doit être une date égale au :date.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'declined' => 'Le champ :attribute doit être refusé.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'dimensions' => "L'image :attribute n'a pas des dimensions valides.",
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'doesnt_end_with' => 'Le champ :attribute ne doit pas se terminer par : :values.',
    'doesnt_start_with' => 'Le champ :attribute ne doit pas commencer par : :values.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'ends_with' => 'Le champ :attribute doit se terminer par : :values.',
    'enum' => 'La valeur du champ :attribute n\'est pas autorisée.',
    'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'array' => 'Le champ :attribute doit contenir au moins :value éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caractères.',
    ],
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'in_array' => "Le champ :attribute n'existe pas dans :other.",
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'ip' => 'Le champ :attribute doit être une adresse IP valide.',
    'ipv4' => 'Le champ :attribute doit être une adresse IPv4 valide.',
    'ipv6' => 'Le champ :attribute doit être une adresse IPv6 valide.',
    'json' => 'Le champ :attribute doit être un document JSON valide.',
    'lt' => [
        'array' => 'Le champ :attribute doit contenir moins de :value éléments.',
        'file' => 'Le fichier :attribute doit peser moins de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caractères.',
    ],
    'lte' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser au plus :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au plus :value caractères.',
    ],
    'mac_address' => 'Le champ :attribute doit être une adresse MAC valide.',
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas peser plus de :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne doit pas être supérieur à :max.',
        'string' => 'Le champ :attribute ne doit pas contenir plus de :max caractères.',
    ],
    'max_digits' => 'Le champ :attribute ne doit pas contenir plus de :max chiffres.',
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'mimetypes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'min_digits' => 'Le champ :attribute doit contenir au moins :min chiffres.',
    'missing' => 'Le champ :attribute doit être absent.',
    'multiple_of' => 'Le champ :attribute doit être un multiple de :value.',
    'not_in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'not_regex' => 'Le format du champ :attribute est invalide.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'present' => 'Le champ :attribute doit être présent.',
    'prohibited' => "Le champ :attribute n'est pas autorisé.",
    'prohibited_if' => "Le champ :attribute n'est pas autorisé quand :other vaut :value.",
    'prohibited_unless' => "Le champ :attribute n'est pas autorisé sauf si :other est :values.",
    'prohibits' => 'Le champ :attribute interdit la présence de :other.',
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_array_keys' => 'Le champ :attribute doit contenir les clés : :values.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_if_accepted' => 'Le champ :attribute est obligatoire quand :other est accepté.',
    'required_unless' => 'Le champ :attribute est obligatoire sauf si :other est :values.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est présent.',
    'required_with_all' => 'Le champ :attribute est obligatoire quand :values sont présents.',
    'required_without' => "Le champ :attribute est obligatoire quand :values n'est pas présent.",
    'required_without_all' => "Le champ :attribute est obligatoire quand aucun de :values n'est présent.",
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'starts_with' => 'Le champ :attribute doit commencer par : :values.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'timezone' => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',
    'uploaded' => 'Le téléversement du fichier :attribute a échoué.',
    'url' => 'Le champ :attribute doit être une URL valide.',
    'uuid' => 'Le champ :attribute doit être un UUID valide.',

    /*
    |---------------------------------------------------------------------------
    | Noms lisibles des champs
    |---------------------------------------------------------------------------
    |
    | Sans cette table, `:attribute` reste le nom TECHNIQUE de la colonne :
    | « Le champ price_xof est obligatoire » ou « Le champ commune_id est
    | obligatoire ». Un visiteur ne sait pas ce qu'est `commune_id`.
    |
    | On ne couvre que les champs réellement exposés dans les formulaires
    | publics et les espaces connectés : traduire les 300 colonnes du schéma
    | serait un entretien sans fin pour des champs que personne ne voit.
    */
    'attributes' => [
        // Compte & identité
        'name' => 'nom',
        'first_name' => 'prénom',
        'last_name' => 'nom de famille',
        'email' => 'adresse e-mail',
        'phone' => 'téléphone',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'current_password' => 'mot de passe actuel',
        'profile_type' => 'type de profil',
        'code' => 'code de vérification',
        'avatar' => 'photo de profil',

        // Contact & demandes
        'subject' => 'objet',
        'message' => 'message',
        'description' => 'description',
        'service_type' => 'type de service',
        'city' => 'ville',
        'budget_xof' => 'budget',

        // Localisation
        'region_id' => 'région',
        'department_id' => 'département',
        'commune_id' => 'commune',
        'address' => 'adresse',

        // Catalogue & annonces
        'title' => 'titre',
        'type' => 'type',
        'price_xof' => 'prix',
        'price_per_day_xof' => 'prix par jour',
        'price_per_night_xof' => 'prix par nuit',
        'capacity' => 'capacité',
        'caution_xof' => 'caution',
        'brand' => 'marque',
        'model' => 'modèle',
        'departure' => 'lieu de départ',
        'destination' => 'destination',
        'departure_at' => 'date de départ',

        // Réservation & paiement
        'start_date' => 'date de début',
        'end_date' => 'date de fin',
        'guests' => 'nombre de voyageurs',
        'seats' => 'nombre de places',
        'amount_xof' => 'montant',
        'booking_id' => 'réservation',
        'mode' => 'mode de paiement',

        // Pièces & documents
        'file' => 'fichier',
        'proof' => 'justificatif',
        'document_type' => 'type de document',

        // Avis
        'rating' => 'note',
        'comment' => 'commentaire',

        // Liste d'attente (2026-08-14)
        'category' => 'catégorie',
        'precisions' => 'précisions',
        'details.type_bien' => 'type de bien',
        'details.nb_biens' => 'nombre de biens',
        'details.type_service' => 'type de service',
        'details.univers' => 'univers qui vous intéresse',
        'details.taille_equipe' => 'taille de l’équipe',
        'details.budget_xof' => 'budget',
        'details.pays_residence' => 'pays de résidence',
        'details.type_projet' => 'type de projet',
    ],
];
