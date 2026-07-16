<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Jeu de données de DÉMONSTRATION (hors production).
 *
 * Peuple les 5 catalogues publics (immobilier, nuitées, transport, tourisme,
 * mobilité) avec quelques annonces PUBLIÉES, afin de voir les pages du frontend
 * remplies en développement local. Séparé du DatabaseSeeder (qui n'amorce que
 * les données de référence : rôles, géographie) pour ne jamais polluer les tests.
 *
 * Prérequis : les rôles et le référentiel géographique doivent déjà être seedés
 * (`php artisan db:seed`).
 *
 * À lancer explicitement :
 *   php artisan db:seed --class=DemoSeeder
 *
 * Idempotent : si les comptes de démonstration possèdent déjà des annonces, la
 * commande ne recrée rien (relance sans risque de doublon).
 *
 * NB : on laisse volontairement les événements de modèle actifs (pas de
 * WithoutModelEvents) pour que l'insertion des annonces invalide le cache du
 * catalogue (voir Property::booted(), Vehicle::booted()…).
 */
class DemoSeeder extends Seeder
{
    /** Comptes de démonstration (e-mails sentinelles, mot de passe « password »). */
    private const OWNER_EMAIL = 'demo.proprietaire@kaikun360.test';

    private const PROVIDER_EMAIL = 'demo.prestataire@kaikun360.test';

    private const CLIENT_EMAIL = 'demo.client@kaikun360.test';

    public function run(): void
    {
        $owner = $this->demoUser(self::OWNER_EMAIL, 'Propriétaire Démo', 'proprietaire');
        $provider = $this->demoUser(self::PROVIDER_EMAIL, 'Prestataire Démo', 'prestataire');

        // Compte client de démonstration : pour se connecter et parcourir
        // l'espace client (F3). Idempotent (firstOrCreate sur l'e-mail), donc
        // créé même quand les données de démo existent déjà (garde ci-dessous).
        $this->demoUser(self::CLIENT_EMAIL, 'Client Démo', 'client');

        // Garde d'idempotence : annonces déjà créées → on s'arrête.
        if (Property::query()->where('owner_id', $owner->id)->exists()) {
            $this->command?->info('DemoSeeder : données de démonstration déjà présentes, rien à faire.');

            return;
        }

        // --- Immobilier : 6 biens publiés, types variés ---
        $properties = collect(PropertyType::cases())
            ->take(6)
            ->map(fn (PropertyType $type) => Property::factory()->published()->create([
                'owner_id' => $owner->id,
                'type' => $type->value,
            ]));

        // --- Nuitées : 4 nuitées publiées, posées sur les premiers biens ---
        $properties->take(4)->each(
            fn (Property $property) => Stay::factory()->create(['property_id' => $property->id]),
        );

        // --- Transport : véhicules publiés couvrant les principaux types ---
        $vehicleTypes = [
            VehicleType::VOITURE_PARTICULIERE,
            VehicleType::VOITURE_TOURISTIQUE,
            VehicleType::NAVETTE_AIBD,
            VehicleType::BUS,
            VehicleType::MINIBUS,
            VehicleType::QUATRE_QUATRE,
        ];
        foreach ($vehicleTypes as $type) {
            Vehicle::factory()->published()->create([
                'provider_id' => $provider->id,
                'type' => $type->value,
            ]);
        }
        // Une pirogue conforme et publiée (tourisme fluvial du Saloum).
        Vehicle::factory()->pirogueConforme()->published()->create([
            'provider_id' => $provider->id,
        ]);

        // --- Tourisme : 5 expériences publiées ---
        TourismExperience::factory()->count(5)->published()->create([
            'provider_id' => $provider->id,
        ]);

        // --- Mobilité : 6 trajets publiés (types tirés aléatoirement par la factory) ---
        MobilityService::factory()->count(6)->published()->create([
            'provider_id' => $provider->id,
        ]);

        $this->command?->info('DemoSeeder : données de démonstration créées (biens, nuitées, véhicules, expériences, trajets).');
    }

    /**
     * Crée (ou retrouve) un compte de démonstration et lui attribue son rôle.
     * `firstOrCreate` garantit l'idempotence sur l'e-mail.
     */
    private function demoUser(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // On n'assigne le rôle que s'il existe (référentiel seedé) et n'est pas déjà là.
        if (Role::query()->where('name', $role)->exists() && ! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
