<?php

namespace Database\Factories;

use App\Modules\Manage\Enums\RentStatus;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\Rent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle Rent.
 *
 * @extends Factory<Rent>
 */
class RentFactory extends Factory
{
    protected $model = Rent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mandate_id' => ManagementMandate::factory(),
            'tenant_name' => fake()->name(),
            'period_label' => now()->translatedFormat('F Y'),
            'due_date' => now()->startOfMonth()->toDateString(),
            'amount_xof' => fake()->numberBetween(75_000, 500_000),
            'status' => RentStatus::IMPAYE->value,
        ];
    }

    /**
     * Loyer payé.
     */
    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => RentStatus::PAYE->value,
            'paid_at' => now(),
        ]);
    }
}
