<?php

namespace Database\Factories;

use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\IncidentStatus;
use App\Modules\Manage\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'INC-'.Str::upper(Str::random(8)),
            'property_id' => Property::factory(),
            'title' => fake()->randomElement(['Fuite d\'eau', 'Panne électrique', 'Serrure cassée']),
            'description' => fake()->sentence(10),
            'priority' => fake()->randomElement(['p1', 'p2', 'p3', 'p4']),
            'status' => IncidentStatus::OUVERT->value,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => IncidentStatus::RESOLU->value,
            'resolved_at' => now(),
        ]);
    }
}
