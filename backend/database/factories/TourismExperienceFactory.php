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
            'itinerary' => [
                ['day' => 1, 'title' => 'Arrivée', 'description' => fake()->sentence(10)],
                ['day' => 2, 'title' => 'Excursion', 'description' => fake()->sentence(10)],
            ],
            'duration_days' => fake()->numberBetween(1, 5),
            'price_xof' => fake()->numberBetween(25_000, 250_000),
            'included' => "Restauration\nGuide francophone",
            'excluded' => "Boissons\nDépenses personnelles",
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

    /**
     * Complète l'expérience avec `$count` dates de départ (F21).
     *
     * À utiliser au lieu de `has()` directement : encapsule le nom de relation
     * explicite qu'exige `TourismExperienceDepartureFactory`.
     */
    public function withDepartures(int $count = 2): static
    {
        return $this->has(TourismExperienceDepartureFactory::new()->count($count), 'departures');
    }
}
