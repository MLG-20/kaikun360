<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Modules\Mobility\Models\MobilityService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle MobilityService.
 *
 * @extends Factory<MobilityService>
 */
class MobilityServiceFactory extends Factory
{
    protected $model = MobilityService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'MOB-'.Str::upper(Str::random(8)),
            'provider_id' => User::factory(),
            'vehicle_id' => null,
            'type' => fake()->randomElement(MobilityServiceType::values()),
            'departure' => fake()->randomElement(['Dakar', 'Thiès', 'Saly', 'AIBD']),
            'destination' => fake()->randomElement(['Saint-Louis', 'Mbour', 'AIBD', 'Dakar']),
            'departure_at' => now()->addDays(fake()->numberBetween(1, 30)),
            'capacity' => fake()->numberBetween(3, 50),
            'price_xof' => fake()->numberBetween(5_000, 60_000),
            'description' => fake()->sentence(8),
            'status' => MobilityServiceStatus::EN_ATTENTE_VALIDATION->value,
        ];
    }

    /**
     * Service publié (visible dans la recherche).
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => MobilityServiceStatus::PUBLIE->value,
            'published_at' => now(),
        ]);
    }
}
