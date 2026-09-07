<?php

namespace Database\Factories;

use App\Models\VehicleShowcaseCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleShowcaseCard>
 */
class VehicleShowcaseCardFactory extends Factory
{
    protected $model = VehicleShowcaseCard::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(12),
            'category' => fake()->randomElement(['Berline', '4x4', 'Minibus']),
            'image_path' => 'vehicle-showcase/'.fake()->uuid().'.jpg',
            'link_url' => 'https://kaikun360.com/transport',
            'link_label' => 'Réserver',
            'is_published' => false,
            'position' => 0,
        ];
    }
}
