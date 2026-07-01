<?php

namespace Database\Factories;

use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle TeamBuildingQuote.
 *
 * @extends Factory<TeamBuildingQuote>
 */
class TeamBuildingQuoteFactory extends Factory
{
    protected $model = TeamBuildingQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = 1_000_000;
        $marginRate = 15.0;
        $margin = (int) round($subtotal * $marginRate / 100);

        return [
            'reference' => 'TBQ-'.Str::upper(Str::random(8)),
            'request_id' => TeamBuildingRequest::factory(),
            'lines' => [
                ['category' => 'hebergement', 'label' => 'Lodge', 'module' => 'Stay', 'quantity' => 20, 'unit_price_xof' => 40_000, 'amount_xof' => 800_000],
                ['category' => 'activite', 'label' => 'Excursion', 'module' => 'Explore', 'quantity' => 20, 'unit_price_xof' => 10_000, 'amount_xof' => 200_000],
            ],
            'subtotal_xof' => $subtotal,
            'margin_rate' => $marginRate,
            'margin_xof' => $margin,
            'total_xof' => $subtotal + $margin,
            'status' => TeamBuildingQuoteStatus::BROUILLON->value,
        ];
    }

    /**
     * Devis envoyé à l'entreprise.
     */
    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => TeamBuildingQuoteStatus::ENVOYE->value,
            'sent_at' => now(),
        ]);
    }
}
