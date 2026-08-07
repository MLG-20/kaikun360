<?php

namespace App\Modules\Mobility\Http\Requests;

use App\Modules\Mobility\Models\MobilityService;

/**
 * Validation du dépôt d'un départ programmé (POST /api/v1/mobility-services).
 *
 * Autorisation via la policy `create` (prestataire vérifié), comme pour un
 * véhicule. Tout est requis : un départ incomplet n'est pas réservable.
 */
class StoreMobilityServiceRequest extends MobilityServiceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MobilityService::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->champsCommuns('required');
    }

    /**
     * Au dépôt, la capacité est toujours dans le formulaire.
     */
    protected function capaciteVisee(): ?int
    {
        return $this->has('capacity') ? (int) $this->input('capacity') : null;
    }
}
