<?php

namespace Database\Factories;

use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle ProviderMission.
 *
 * @extends Factory<ProviderMission>
 */
class ProviderMissionFactory extends Factory
{
    protected $model = ProviderMission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(50_000, 1_000_000);

        return [
            'reference' => 'MSN-'.Str::upper(Str::random(8)),
            'provider_id' => Provider::factory()->validated(),
            'title' => fake()->randomElement(['Animation soirée', 'Traiteur événement', 'Guide journée']),
            'description' => fake()->sentence(10),
            'amount_xof' => $amount,
            'commission_xof' => (int) round($amount * 0.12),
            'status' => MissionStatus::AFFECTEE->value,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => MissionStatus::ACCEPTEE->value]);
    }
}
