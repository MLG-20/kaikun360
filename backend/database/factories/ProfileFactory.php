<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory du modèle Profile — génère des profils de test.
 *
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * Valeurs par défaut d'un profil de test (un client non vérifié).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Crée automatiquement un utilisateur associé si aucun n'est fourni.
            'user_id' => User::factory(),
            'type' => ProfileType::CLIENT->value,
            'verification_status' => 'non_verifie',
            'preferences' => [],
        ];
    }

    /**
     * État pratique : un profil propriétaire.
     */
    public function proprietaire(): static
    {
        return $this->state(fn () => ['type' => ProfileType::PROPRIETAIRE->value]);
    }
}
