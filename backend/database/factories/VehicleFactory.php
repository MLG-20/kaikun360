<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle Vehicle.
 *
 * Par défaut : un transport motorisé CONFORME (assurance + identité chauffeur),
 * en attente de validation. États : published(), pirogue(), pirogueConforme().
 *
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'VEH-'.Str::upper(Str::random(8)),
            'provider_id' => User::factory(),
            'type' => VehicleType::VOITURE_TOURISTIQUE->value,
            'brand' => fake()->randomElement(['Toyota', 'Hyundai', 'Renault', 'Mercedes']),
            'model' => fake()->randomElement(['Hiace', 'Land Cruiser', 'Sprinter', 'Duster']),
            'capacity' => fake()->numberBetween(4, 30),
            'price_per_day_xof' => fake()->numberBetween(25_000, 200_000),
            'has_driver' => true,
            // F5.8 : la caution reste réservée à la gestion locative
            // (`Property.caution_xof`) — un prestataire ne fixe plus de caution
            // au dépôt/à l'édition d'un véhicule (champ retiré du formulaire le
            // 2026-08-23), toujours 0 ici pour ne pas fabriquer une fausse
            // caution qu'aucun prestataire n'a jamais demandée.
            'caution_xof' => 0,
            'description' => fake()->sentence(10),
            // Conformité motorisé renseignée par défaut.
            'insurance_ref' => 'ASS-'.Str::upper(Str::random(6)),
            'driver_identity' => fake()->name(),
            'status' => VehicleStatus::EN_ATTENTE_VALIDATION->value,
        ];
    }

    /**
     * Véhicule publié (visible dans la recherche).
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => VehicleStatus::PUBLIE->value,
            'published_at' => now(),
        ]);
    }

    /**
     * Pirogue SANS les champs de conformité (validation bloquée).
     */
    public function pirogue(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::PIROGUE->value,
            'has_driver' => true,
            'brand' => null,
            'model' => null,
            'insurance_ref' => null,
            'driver_identity' => null,
        ]);
    }

    /**
     * Pirogue avec conformité complète (gilets, météo, prestataire).
     */
    public function pirogueConforme(): static
    {
        return $this->pirogue()->state(fn () => [
            'life_jackets_count' => 20,
            'weather_compliant' => true,
            'provider_compliant' => true,
        ]);
    }
}
