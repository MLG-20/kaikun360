<?php

namespace App\Modules\TeamBuilding\Policies;

use App\Models\User;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;

/**
 * Policy d'accès aux demandes/devis de team building (phase B9.3).
 *
 * Règle : une entreprise ne voit que SES demandes et devis. La composition et
 * l'envoi des devis relèvent du back-office (admin) ; l'acceptation appartient à
 * l'entreprise. super_admin passe par Gate::before.
 */
class TeamBuildingRequestPolicy
{
    /**
     * Déposer une demande : tout utilisateur authentifié (entreprise).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Consulter une demande/ses devis : entreprise propriétaire ou admin.
     */
    public function view(User $user, TeamBuildingRequest $request): bool
    {
        return $user->id === $request->company_id
            || $user->can('traiter:demandes');
    }

    /**
     * Composer / envoyer un devis, affecter les prestataires : back-office.
     *
     * ⚠️ F7.4.b — garde passée du RÔLE `admin` à la PERMISSION
     * `traiter:demandes`. Le CDC §7 confie « traitement demandes » et
     * « affectation prestataire » à l'agent Kaikun : avec l'ancienne règle, la
     * file des demandes entreprises s'ouvrait bien à lui (la route est gardée
     * `consulter:dashboard-admin`) mais chaque fiche répondait 403 — un écran
     * visible et inutilisable. L'admin détient la permission via son rôle : son
     * accès est inchangé.
     */
    public function manage(User $user, TeamBuildingRequest $request): bool
    {
        return $user->can('traiter:demandes');
    }

    /**
     * Accepter un devis : entreprise propriétaire de la demande.
     */
    public function accept(User $user, TeamBuildingRequest $request): bool
    {
        return $user->id === $request->company_id;
    }
}
