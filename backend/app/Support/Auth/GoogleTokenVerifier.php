<?php

namespace App\Support\Auth;

/**
 * Contrat de vérification d'un ID token Google (B19).
 *
 * Le reste de l'application ne dépend que de cette abstraction : on peut ainsi
 * la remplacer par un double en test (comme le PSP de paiement).
 */
interface GoogleTokenVerifier
{
    /**
     * Vérifie l'ID token et renvoie l'identité Google, ou null si invalide
     * (signature/audience/expiration incorrectes, e-mail non vérifié…).
     */
    public function verify(string $idToken): ?GoogleUser;
}
