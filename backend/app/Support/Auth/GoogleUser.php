<?php

namespace App\Support\Auth;

/**
 * Identité vérifiée renvoyée par Google (B19), extraite d'un ID token valide.
 */
final class GoogleUser
{
    public function __construct(
        /** Identifiant Google stable de l'utilisateur (claim `sub`). */
        public readonly string $sub,
        public readonly string $email,
        public readonly string $name,
        public readonly bool $emailVerified,
    ) {
    }
}
