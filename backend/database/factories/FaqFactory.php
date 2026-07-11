<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'question' => rtrim(fake()->sentence(), '.').' ?',
            'answer' => fake()->paragraph(),
            'category' => fake()->randomElement(['paiement', 'reservation', 'compte', null]),
            'position' => fake()->numberBetween(0, 20),
            'is_published' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
