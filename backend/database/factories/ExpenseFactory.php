<?php

namespace Database\Factories;

use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\ExpenseCategory;
use App\Modules\Manage\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'incident_id' => null,
            'label' => fake()->randomElement(['Plomberie', 'Peinture', 'Électricité']),
            'category' => fake()->randomElement(ExpenseCategory::values()),
            'amount_xof' => fake()->numberBetween(10_000, 800_000),
            'spent_at' => now()->toDateString(),
        ];
    }
}
