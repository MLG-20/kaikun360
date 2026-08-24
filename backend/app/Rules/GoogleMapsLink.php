<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Une carte Google Maps INTÉGRÉE, collée par le propriétaire/prestataire
 * (F5.10) — pas de carte interactive au dépôt ni de clé API facturable
 * (la plateforme appartient à un client). Le propriétaire/prestataire trouve
 * son lieu sur Google Maps, clique « Partager » → « Intégrer une carte », et
 * colle le code (ou seulement le lien `src` qu'il contient) : c'est le mode
 * de partage GRATUIT de Google Maps, distinct de l'API Maps Embed payante.
 *
 * Vérifie que le lien pointe bien vers un domaine Google Maps connu, pour
 * éviter qu'un lien quelconque (voire malveillant) ne soit intégré en iframe
 * sur une fiche cliente.
 */
class GoogleMapsLink implements ValidationRule
{
    private const PATTERN = '/^https:\/\/(www\.)?(maps\.)?google\.[a-z.]{2,10}\/maps'
        .'(\/embed\?pb=|\?.*output=embed)/i';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('Le lien doit être une carte Google Maps intégrée (Partager → Intégrer une carte).');
        }
    }
}
