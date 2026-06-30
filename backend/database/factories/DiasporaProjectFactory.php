<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Enums\DiasporaProjectStatus;
use App\Modules\Diaspora\Enums\DiasporaProjectType;
use App\Modules\Diaspora\Models\DiasporaProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle DiasporaProject.
 *
 * @extends Factory<DiasporaProject>
 */
class DiasporaProjectFactory extends Factory
{
    protected $model = DiasporaProject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'DSP-'.Str::upper(Str::random(8)),
            'client_id' => User::factory(),
            'agent_id' => null,
            'project_type' => fake()->randomElement(DiasporaProjectType::values()),
            'residence_country' => fake()->randomElement(['France', 'États-Unis', 'Italie', 'Canada', 'Espagne']),
            'budget_xof' => fake()->numberBetween(5_000_000, 200_000_000),
            'description' => fake()->sentence(12),
            'priority' => DiasporaPriority::NORMALE->value,
            'status' => DiasporaProjectStatus::NOUVEAU->value,
        ];
    }

    /**
     * Projet avec un agent déjà affecté (et donc passé « en cours »).
     */
    public function assigned(?User $agent = null): static
    {
        return $this->state(fn () => [
            'agent_id' => $agent?->id ?? User::factory(),
            'status' => DiasporaProjectStatus::EN_COURS->value,
        ]);
    }

    /**
     * Dossier à forte valeur (priorité stratégique).
     */
    public function strategic(): static
    {
        return $this->state(fn () => ['priority' => DiasporaPriority::STRATEGIQUE->value]);
    }
}
