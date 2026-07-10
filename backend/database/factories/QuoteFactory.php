<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle Quote.
 *
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'QTE-'.Str::upper(Str::random(8)),
            'request_id' => ServiceRequest::factory(),
            'amount_xof' => fake()->numberBetween(100_000, 20_000_000),
            'details' => ['conditions' => 'Paiement 50% à la commande'],
            'valid_until' => now()->addWeeks(2)->toDateString(),
            'status' => QuoteStatus::ENVOYE->value,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::BROUILLON->value]);
    }
}
