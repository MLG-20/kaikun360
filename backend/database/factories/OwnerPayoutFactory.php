<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Manage\Enums\OwnerPayoutStatus;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnerPayout>
 */
class OwnerPayoutFactory extends Factory
{
    protected $model = OwnerPayout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RVS-'.Str::upper(Str::random(8)),
            // Rattache au mandat ; l'owner_id reprend le propriétaire du mandat.
            'mandate_id' => ManagementMandate::factory(),
            'owner_id' => fn (array $attributes) => ManagementMandate::find($attributes['mandate_id'])?->owner_id
                ?? User::factory(),
            'period_label' => now()->translatedFormat('F Y'),
            'amount_xof' => fake()->numberBetween(50_000, 2_000_000),
            'status' => OwnerPayoutStatus::EN_ATTENTE->value,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => OwnerPayoutStatus::EFFECTUE->value,
            'paid_at' => now(),
        ]);
    }
}
