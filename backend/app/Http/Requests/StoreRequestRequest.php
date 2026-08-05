<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'une demande générique (POST /api/v1/requests).
 *
 * ⚠️ **La construction n'en fait plus partie (F8.15.b).** Un chantier a sa
 * propre table, son estimation, ses jalons, ses rapports et ses devis ventilés
 * par lot : déposé en demande générique, il atterrissait dans `requests` et
 * n'atteignait jamais l'écran back-office « Construction » — le module entier
 * restait inatteignable. La page publique appelle désormais
 * `POST /construction-requests` ; ce garde-fou empêche de recâbler le mauvais
 * aiguillage par inadvertance, depuis un futur écran, l'application mobile ou un
 * appel direct à l'API.
 *
 * ⚠️ Le cas `ServiceType::BUILD` **reste dans l'enum** : des demandes déposées
 * avant F8.15.b le portent en base, et le retirer casserait leur relecture (cast,
 * libellé, filtres du back-office). Il est simplement devenu **historique** — plus
 * rien ne peut en créer.
 */
class StoreRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Univers acceptés en demande GÉNÉRIQUE : tous sauf ceux qui ont leur propre
     * porte d'entrée structurée.
     *
     * @return list<string>
     */
    private function universAcceptes(): array
    {
        return array_values(array_diff(ServiceType::values(), [ServiceType::BUILD->value]));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_type' => ['required', Rule::in($this->universAcceptes())],
            'message' => ['required', 'string', 'max:2000'],
            'budget_xof' => ['nullable', 'integer', 'min:0'],
            'city' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(RequestPriority::values())],
        ];
    }

    /**
     * Un refus utile plutôt qu'un « valeur invalide » : l'appelant doit savoir
     * quelle porte prendre, sinon il croira à un bug de l'enum.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_type.in' => "Une demande de construction se dépose sur POST /construction-requests : elle exige un objectif, une surface et un niveau de finition, et ouvre un dossier de chantier (jalons, rapports, devis par lot).",
        ];
    }
}
