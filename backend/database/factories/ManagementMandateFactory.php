<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\MandateStatus;
use App\Modules\Manage\Models\ManagementMandate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle ManagementMandate.
 *
 * @extends Factory<ManagementMandate>
 */
class ManagementMandateFactory extends Factory
{
    protected $model = ManagementMandate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'MND-'.Str::upper(Str::random(8)),
            // Le propriétaire est créé d'abord...
            'owner_id' => User::factory(),
            // ...puis le bien est rattaché à CE même propriétaire (closure qui
            // lit l'owner_id déjà résolu), pour garder des données cohérentes.
            'property_id' => fn (array $attributes) => Property::factory()->create([
                'owner_id' => $attributes['owner_id'],
            ])->id,
            'commission_rate' => fake()->randomElement([8, 10, 12.5, 15]),
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'status' => MandateStatus::ACTIF->value,
            'terms' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => MandateStatus::EN_ATTENTE->value]);
    }
}
