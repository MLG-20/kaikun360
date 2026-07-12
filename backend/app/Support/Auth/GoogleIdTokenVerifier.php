<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Http;

/**
 * Vérificateur réel d'ID token Google (B19).
 *
 * Interroge l'endpoint `tokeninfo` de Google, qui valide la signature et
 * l'expiration du JWT, puis on applique nos contrôles de sécurité :
 *   - l'**audience** (`aud`) DOIT être notre `client_id` (sinon un token émis pour
 *     une autre application serait accepté — faille classique) ;
 *   - l'e-mail doit être **vérifié** par Google.
 *
 * Renvoie toujours null en cas de doute — jamais l'inverse.
 */
class GoogleIdTokenVerifier implements GoogleTokenVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    public function __construct(private readonly ?string $clientId)
    {
    }

    public function verify(string $idToken): ?GoogleUser
    {
        if (empty($this->clientId) || empty($idToken)) {
            return null;
        }

        $response = Http::get(self::TOKENINFO_URL, ['id_token' => $idToken]);
        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        // Sécurité : l'audience doit correspondre à NOTRE application.
        if (($payload['aud'] ?? null) !== $this->clientId) {
            return null;
        }

        // Google renvoie parfois `email_verified` en chaîne ("true"/"false").
        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (empty($payload['sub']) || empty($payload['email']) || ! $emailVerified) {
            return null;
        }

        return new GoogleUser(
            sub: (string) $payload['sub'],
            email: (string) $payload['email'],
            name: (string) ($payload['name'] ?? $payload['email']),
            emailVerified: true,
        );
    }
}
