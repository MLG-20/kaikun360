<?php

namespace Database\Factories;

use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Models\TourismExperienceDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle TourismExperienceDeparture.
 *
 * ⚠️ Le nom du modèle ne suit pas la convention de devinette des relations de
 * Laravel (`tourismExperienceDepartures` au lieu de `departures`) : toujours
 * préciser la relation explicitement, ex.
 * `TourismExperience::factory()->has(self::new()->count(2), 'departures')`.
 *
 * @extends Factory<TourismExperienceDeparture>
 */
class TourismExperienceDepartureFactory extends Factory
{
    protected $model = TourismExperienceDeparture::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tourism_experience_id' => TourismExperience::factory(),
            'start_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'seats_total' => fake()->numberBetween(4, 30),
        ];
    }
}
