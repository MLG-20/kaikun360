<?php

namespace Database\Factories;

use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle Stay.
 *
 * @extends Factory<Stay>
 */
class StayFactory extends Factory
{
    protected $model = Stay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Par défaut, rattache à un bien publié (cas réel d'une nuitée en ligne).
            'property_id' => Property::factory()->published(),
            'price_per_night_xof' => fake()->numberBetween(15_000, 150_000),
            'caution_xof' => fake()->randomElement([0, 50_000, 100_000]),
            'capacity' => fake()->numberBetween(1, 8),
            'min_nights' => 1,
            'max_nights' => fake()->randomElement([null, 14, 30]),
            'rules' => ['non_fumeur' => true],
            'amenities' => ['wifi' => true, 'climatisation' => true],
            'is_active' => true,
        ];
    }

    /**
     * Nuitée désactivée par le propriétaire.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
