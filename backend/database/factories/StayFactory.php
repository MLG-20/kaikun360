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
            // F5.8 : la caution reste réservée aux locations au mois (`Property`)
            // depuis que le formulaire ne propose plus de la saisir pour une
            // nuitée — toujours 0 ici, pour ne pas fabriquer une fausse caution
            // qu'aucun propriétaire n'a jamais demandée.
            'caution_xof' => 0,
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
