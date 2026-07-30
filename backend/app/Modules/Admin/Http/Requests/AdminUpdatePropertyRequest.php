<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Immo\Http\Requests\UpdatePropertyRequest;

/**
 * Correction d'un bien depuis le back-office (F7.3.g — dette CDC §15
 * « un admin peut modifier »).
 *
 * Reprend **exactement** les règles de validation du propriétaire
 * (`UpdatePropertyRequest`) — mêmes champs, même cohérence géographique — et ne
 * change que l'autorisation.
 *
 * ⚠️ Le parent autorise via la policy `update` = propriétaire **ou rôle admin**.
 * Ici on garde par **permission** `valider:bien` : quelqu'un à qui l'on confie
 * déjà la publication ou le rejet d'une annonce peut en corriger le titre. Cela
 * ouvre le geste à l'`agent_kaikun`, conformément au CDC §7 qui lui confie la
 * « validation de base » — et évite de reproduire l'écart de rôle constaté sur le
 * team building.
 */
class AdminUpdatePropertyRequest extends UpdatePropertyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('valider:bien') ?? false;
    }
}
