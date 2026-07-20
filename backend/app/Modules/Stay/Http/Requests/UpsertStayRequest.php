<?php

namespace App\Modules\Stay\Http\Requests;

use App\Modules\Immo\Models\Property;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la config « nuitées » d'un bien
 * (PUT /api/v1/properties/{property}/stay).
 *
 * Endpoint d'UPSERT : le même corps crée la config si le bien n'en a pas encore,
 * ou la met à jour sinon. L'autorisation réutilise la PropertyPolicy — celui qui
 * peut éditer le bien (propriétaire ou admin) configure ses nuitées.
 *
 * Seul `price_per_night_xof` est obligatoire ; les autres champs ont des valeurs
 * par défaut en base (caution 0, capacité 1, min_nights 1) appliquées à la
 * création lorsqu'ils sont omis.
 */
class UpsertStayRequest extends FormRequest
{
    /**
     * Autorisé au propriétaire du bien (ou admin), via la PropertyPolicy.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('update', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_per_night_xof' => ['required', 'integer', 'min:0'],
            'caution_xof' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_nights' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_nights' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'check_in_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'amenities' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Cohérence croisée : le nombre maximal de nuits, quand il est fourni, ne
     * peut pas être inférieur au minimum. On le vérifie après coup car `gte:`
     * ne gère pas proprement le cas où `min_nights` est absent.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = $this->integerOrNull('min_nights') ?? 1;
            $max = $this->integerOrNull('max_nights');

            if ($max !== null && $max < $min) {
                $validator->errors()->add(
                    'max_nights',
                    'Le nombre maximum de nuits ne peut pas être inférieur au minimum.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_per_night_xof.required' => 'Le prix par nuit est obligatoire.',
            'price_per_night_xof.integer' => 'Le prix par nuit doit être un nombre.',
            'capacity.min' => 'La capacité doit être d’au moins une personne.',
            'min_nights.min' => 'Le nombre minimum de nuits doit être d’au moins 1.',
            'check_in_time.date_format' => 'L’heure d’arrivée doit être au format HH:MM.',
            'check_out_time.date_format' => 'L’heure de départ doit être au format HH:MM.',
        ];
    }

    /** Lit un champ numérique en tolérant l'absence / la chaîne vide. */
    private function integerOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
