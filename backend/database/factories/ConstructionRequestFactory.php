<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionRequestStatus;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle ConstructionRequest.
 *
 * @extends Factory<ConstructionRequest>
 */
class ConstructionRequestFactory extends Factory
{
    protected $model = ConstructionRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CST-'.Str::upper(Str::random(8)),
            'client_id' => User::factory(),
            'objective' => fake()->randomElement(ConstructionObjective::values()),
            'city' => fake()->randomElement(['Dakar', 'Thiès', 'Saint-Louis', 'Mbour']),
            'surface_m2' => fake()->numberBetween(60, 500),
            'budget_xof' => fake()->numberBetween(10_000_000, 150_000_000),
            'finish_level' => fake()->randomElement(FinishLevel::values()),
            'description' => fake()->sentence(12),
            'status' => ConstructionRequestStatus::SOUMISE->value,
        ];
    }

    /**
     * Demande au statut « en chantier ».
     */
    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => ConstructionRequestStatus::EN_CHANTIER->value]);
    }
}
