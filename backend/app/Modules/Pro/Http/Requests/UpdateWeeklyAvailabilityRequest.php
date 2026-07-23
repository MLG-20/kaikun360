<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du planning hebdomadaire (PUT /api/v1/providers/availability/weekly).
 *
 * Le prestataire envoie la liste des jours (0 = lundi … 6 = dimanche). Chaque
 * jour peut être ouvert (heures requises) ou fermé (heures ignorées). L'accès est
 * garanti par le contrôleur (résolution du profil prestataire du compte connecté).
 */
class UpdateWeeklyAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6'],
            'days.*.is_open' => ['required', 'boolean'],
            // Heures requises seulement si le jour est ouvert.
            'days.*.start_time' => ['nullable', 'required_if:days.*.is_open,true', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'required_if:days.*.is_open,true', 'date_format:H:i'],
        ];
    }

    /**
     * Contrôles croisés : pas de doublon de jour, et fin > début quand ouvert.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $days = $this->input('days', []);
            $seen = [];

            foreach ($days as $i => $day) {
                $weekday = $day['weekday'] ?? null;
                if ($weekday !== null) {
                    if (in_array($weekday, $seen, true)) {
                        $validator->errors()->add("days.$i.weekday", 'Ce jour est en double.');
                    }
                    $seen[] = $weekday;
                }

                $open = filter_var($day['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $start = $day['start_time'] ?? null;
                $end = $day['end_time'] ?? null;
                if ($open && $start && $end && $end <= $start) {
                    $validator->errors()->add("days.$i.end_time", 'L\'heure de fin doit être après le début.');
                }
            }
        });
    }
}
