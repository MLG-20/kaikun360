<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un compte vérifié pour les actions sensibles (B15.2).
 *
 * Un compte dont ni l'e-mail ni le téléphone n'a été vérifié peut naviguer et
 * consulter, mais ne peut ni transiger (réservation, paiement) ni publier
 * (bien, véhicule, circuit, inscription prestataire). Complète la vérification
 * de compte (B1.4) en la rendant OBLIGATOIRE avant toute action à effet.
 */
class EnsureAccountVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ($user->email_verified_at === null && $user->phone_verified_at === null)) {
            abort(403, 'Compte non vérifié : vérifiez votre e-mail ou votre téléphone pour effectuer cette action.');
        }

        return $next($request);
    }
}
