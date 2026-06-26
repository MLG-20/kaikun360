<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\User;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle Property — biens de test.
 *
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Rattache le bien à une commune réelle du référentiel, si celui-ci est
        // seedé. Sinon (référentiel absent dans certains tests), reste à null.
        $commune = Commune::query()->with('department')->inRandomOrder()->first();

        return [
            'owner_id' => User::factory(),
            'type' => fake()->randomElement(PropertyType::values()),
            'title' => fake()->randomElement(['Villa', 'Appartement', 'Maison']).' à vendre',
            'description' => fake()->sentence(12),
            'price_xof' => fake()->numberBetween(5_000_000, 150_000_000),
            'region_id' => $commune?->department?->region_id,
            'department_id' => $commune?->department_id,
            'commune_id' => $commune?->id,
            'address' => fake()->streetAddress(),
            // Par défaut : en attente de validation (un bien neuf n'est pas public).
            'status' => PropertyStatus::EN_ATTENTE_VALIDATION->value,
            'verification_level' => 'unverified',
        ];
    }

    /**
     * Bien publié (visible au catalogue public).
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PropertyStatus::PUBLIE->value,
            'published_at' => now(),
        ]);
    }

    /**
     * Bien explicitement en attente de validation (état par défaut, pour la lisibilité des tests).
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PropertyStatus::EN_ATTENTE_VALIDATION->value,
        ]);
    }
}
