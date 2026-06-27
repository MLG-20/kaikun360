<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle TourismExperience.
 *
 * @extends Factory<TourismExperience>
 */
class TourismExperienceFactory extends Factory
{
    protected $model = TourismExperience::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'EXP-'.Str::upper(Str::random(8)),
            'provider_id' => User::factory(),
            'title' => fake()->randomElement(['Découverte du Saloum', 'Désert de Lompoul', 'Île de Gorée', 'Safari Bandia']),
            'destination' => fake()->randomElement(['Saloum', 'Lompoul', 'Gorée', 'Bandia']),
            'description' => fake()->sentence(14),
            'duration_days' => fake()->numberBetween(1, 5),
            'price_xof' => fake()->numberBetween(25_000, 250_000),
            'capacity' => fake()->numberBetween(4, 30),
            'inclusions' => ['restauration' => true, 'guide' => true, 'transport' => fake()->boolean()],
            'status' => ExperienceStatus::EN_ATTENTE_VALIDATION->value,
        ];
    }

    /**
     * Expérience publiée (visible au catalogue).
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ExperienceStatus::PUBLIE->value,
            'published_at' => now(),
        ]);
    }
}
