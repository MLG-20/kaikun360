<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCategory as ProviderCategoryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle Provider.
 *
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'category' => fake()->randomElement(
                ProviderCategoryModel::query()->approved()->pluck('key')->all(),
            ),
            'bio' => fake()->sentence(12),
            'status' => ProviderStatus::EN_ATTENTE->value,
        ];
    }

    /**
     * Prestataire validé.
     */
    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => ProviderStatus::VALIDE->value,
            'validated_at' => now(),
        ]);
    }

    /**
     * Prestataire suspendu.
     */
    public function suspended(): static
    {
        return $this->state(fn () => ['status' => ProviderStatus::SUSPENDU->value]);
    }
}
