<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle Review.
 *
 * Cible par défaut un véhicule ; les tests surchargent `reviewable_type`/
 * `reviewable_id` pour n'importe quelle ressource notable.
 *
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'REV-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => Vehicle::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
            'status' => ReviewStatus::EN_ATTENTE->value,
            'moderated_by' => null,
            'moderated_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::PUBLIE->value,
            'moderated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::REJETE->value,
            'moderated_at' => now(),
        ]);
    }
}
