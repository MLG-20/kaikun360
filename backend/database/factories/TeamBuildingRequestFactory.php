<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\TeamBuilding\Enums\TeamBuildingRequestStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle TeamBuildingRequest.
 *
 * @extends Factory<TeamBuildingRequest>
 */
class TeamBuildingRequestFactory extends Factory
{
    protected $model = TeamBuildingRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addMonth();

        return [
            'reference' => 'TBR-'.Str::upper(Str::random(8)),
            'company_id' => User::factory(),
            'participants' => fake()->numberBetween(10, 80),
            'city' => fake()->randomElement(['Saly', 'Saint-Louis', 'Toubab Dialaw', 'Dakar']),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'budget_xof' => fake()->numberBetween(2_000_000, 30_000_000),
            'needs' => [
                'hebergement' => true,
                'restauration' => true,
                'activite' => true,
                'mobilite' => true,
                'animation' => fake()->boolean(),
            ],
            'description' => fake()->sentence(12),
            'status' => TeamBuildingRequestStatus::NOUVEAU->value,
        ];
    }
}
