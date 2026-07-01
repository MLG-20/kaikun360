<?php

namespace Database\Factories;

use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCertification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle ProviderCertification.
 *
 * @extends Factory<ProviderCertification>
 */
class ProviderCertificationFactory extends Factory
{
    protected $model = ProviderCertification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => fake()->randomElement(['Agrément tourisme', 'Diplôme cuisine', 'Assurance RC', 'Permis transport']),
            'issuer' => fake()->randomElement(['Ministère du Tourisme', 'CFPT', 'Assureur', null]),
            'verified' => false,
        ];
    }

    /**
     * Certification vérifiée par un agent.
     */
    public function verified(): static
    {
        return $this->state(fn () => ['verified' => true]);
    }
}
