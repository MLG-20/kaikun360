<?php

namespace App\Modules\Mobility\Http\Requests;

use App\Enums\BookingStatus;
use App\Modules\Mobility\Models\MobilityService;
use Illuminate\Validation\Validator;

/**
 * Validation de la correction d'un départ (PATCH /api/v1/mobility-services/{id}).
 *
 * Tout est facultatif (`sometimes`) : on corrige un prix ou une heure sans
 * réémettre le trajet entier. L'autorisation est portée par la policy `update`,
 * vérifiée dans le contrôleur — ici on ne juge que la forme et la cohérence.
 */
class UpdateMobilityServiceRequest extends MobilityServiceRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->champsCommuns('sometimes');
    }

    /**
     * Contrôles de forme, PUIS la règle propre à la correction.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(fn (Validator $validator) => $this->verifierLesPlacesDejaVendues($validator));
    }

    /**
     * On ne descend pas la capacité sous les places DÉJÀ VENDUES.
     *
     * ⚠️ Sans cette règle, un prestataire qui ramène son départ de 30 à 4 places
     * après en avoir vendu 12 laisserait douze clients détenteurs d'une place
     * qui n'existe plus : le produit afficherait « 0 place restante » sur un
     * départ surbooké, et personne ne saurait qui débarquer. Le refus est donc
     * la seule issue honnête — annuler des réservations est une décision
     * commerciale, elle ne peut pas être l'effet de bord d'un champ corrigé.
     */
    private function verifierLesPlacesDejaVendues(Validator $validator): void
    {
        if (! $this->has('capacity') || $validator->errors()->has('capacity')) {
            return;
        }

        $service = $this->departConcerne();

        if ($service === null) {
            return;
        }

        $vendues = (int) $service->bookings()
            ->whereNotIn('status', BookingStatus::valeursAnnulees())
            ->sum('guests');

        if ((int) $this->input('capacity') < $vendues) {
            $validator->errors()->add(
                'capacity',
                "{$vendues} place(s) sont déjà réservées sur ce départ : la capacité ne peut pas "
                .'descendre en dessous. Annulez les réservations concernées avant de la réduire.',
            );
        }
    }

    /**
     * En correction, la capacité de référence est celle du formulaire si elle y
     * figure, et sinon celle déjà enregistrée (cf. `MobilityServiceRequest`).
     */
    protected function capaciteVisee(): ?int
    {
        if ($this->has('capacity')) {
            return (int) $this->input('capacity');
        }

        return $this->departConcerne()?->capacity;
    }

    /**
     * Le départ visé par la route, tel que l'a résolu le liage implicite.
     */
    private function departConcerne(): ?MobilityService
    {
        $service = $this->route('mobility_service');

        return $service instanceof MobilityService ? $service : null;
    }
}
