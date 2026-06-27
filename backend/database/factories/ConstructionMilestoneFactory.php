<?php

namespace Database\Factories;

use App\Modules\Build\Enums\MilestoneStatus;
use App\Modules\Build\Models\ConstructionMilestone;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle ConstructionMilestone.
 *
 * @extends Factory<ConstructionMilestone>
 */
class ConstructionMilestoneFactory extends Factory
{
    protected $model = ConstructionMilestone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'construction_request_id' => ConstructionRequest::factory(),
            'name' => fake()->randomElement(['Fondations', 'Gros œuvre', 'Toiture', 'Finitions']),
            'position' => fake()->numberBetween(1, 7),
            'status' => MilestoneStatus::A_VENIR->value,
            'planned_date' => now()->addMonths(2)->toDateString(),
        ];
    }

    /**
     * Jalon terminé (avec date réelle).
     */
    public function done(): static
    {
        return $this->state(fn () => [
            'status' => MilestoneStatus::TERMINE->value,
            'actual_date' => now()->toDateString(),
        ]);
    }
}
