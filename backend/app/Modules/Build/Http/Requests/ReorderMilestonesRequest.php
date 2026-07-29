<?php

namespace App\Modules\Build\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du réordonnancement des jalons d'un chantier
 * (PUT /api/v1/construction-requests/{id}/milestones/reorder) — phase F7.3.e1.
 *
 * On envoie la liste ORDONNÉE des identifiants plutôt qu'une position par jalon :
 * un simple échange de deux positions produirait sinon deux doublons transitoires
 * (et un ordre indéterminé si l'une des deux écritures échoue). Ici le serveur
 * réécrit les positions 1..n en une transaction, l'ordre est donc toujours sain.
 */
class ReorderMilestonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:chantiers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'milestones' => ['required', 'array', 'min:1'],
            'milestones.*' => ['integer'],
        ];
    }
}
