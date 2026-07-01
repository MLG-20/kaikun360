<?php

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle ServiceRequest (table `requests`).
 *
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'REQ-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'service_type' => fake()->randomElement(ServiceType::values()),
            'message' => fake()->sentence(14),
            'budget_xof' => fake()->numberBetween(100_000, 50_000_000),
            'city' => fake()->randomElement(['Dakar', 'Thiès', 'Saint-Louis', 'Mbour']),
            'status' => RequestStatus::RECU->value,
            'priority' => RequestPriority::NORMALE->value,
        ];
    }

    /**
     * Positionne la demande à un statut donné (pour les tests de transition).
     */
    public function status(RequestStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
