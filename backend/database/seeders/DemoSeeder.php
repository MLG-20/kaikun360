<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\ServiceRequest;
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
use Illuminate\Support\Str;
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
        $client = $this->demoUser(self::CLIENT_EMAIL, 'Client Démo', 'client');

        // Quelques demandes de service pour peupler l'écran « Mes demandes »
        // (F3.3) de l'espace client. Garde d'idempotence PROPRE (indépendante de
        // celle des annonces ci-dessous) : on ne recrée rien si le client en a
        // déjà, afin que la relance du seeder reste sans doublon.
        $this->seedClientRequests($client);

        // Catalogues de démonstration : créés une seule fois (garde d'idempotence
        // sur l'existence d'un bien du propriétaire de démo). On n'utilise plus un
        // `return` anticipé afin que le seeding des réservations client (tout en
        // bas) s'exécute AUSSI sur une base où les annonces existent déjà.
        if (! Property::query()->where('owner_id', $owner->id)->exists()) {
            $this->seedCatalogues($owner, $provider);
        } else {
            $this->command?->info('DemoSeeder : catalogues de démonstration déjà présents.');
        }

        // Quelques réservations pour peupler l'écran « Mes réservations » (F3.4)
        // de l'espace client. Idempotent (garde propre) ; s'appuie sur les
        // bookables de démo ci-dessus (nuitées, véhicules, expériences, trajets).
        $this->seedClientBookings($client);
    }

    /**
     * Crée les catalogues publics de démonstration (immobilier, nuitées,
     * transport, tourisme, mobilité) rattachés aux comptes de démo.
     */
    private function seedCatalogues(User $owner, User $provider): void
    {
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
     * Peuple l'écran « Mes demandes » (F3.3) du client de démonstration avec
     * trois demandes à des statuts variés (pour illustrer la chronologie de
     * suivi). Idempotent : ne fait rien si le client possède déjà des demandes.
     */
    private function seedClientRequests(User $client): void
    {
        if (ServiceRequest::query()->where('user_id', $client->id)->exists()) {
            return;
        }

        // Une demande par étape marquante de la machine à états, univers variés.
        ServiceRequest::factory()->status(RequestStatus::VISITE)->create([
            'user_id' => $client->id,
            'service_type' => ServiceType::IMMO->value,
            'message' => 'Je souhaite visiter la villa aux Almadies ce week-end.',
            'budget_xof' => 45_000_000,
            'city' => 'Dakar',
        ]);

        ServiceRequest::factory()->status(RequestStatus::RECU)->create([
            'user_id' => $client->id,
            'service_type' => ServiceType::STAY->value,
            'message' => 'Réservation d’une nuitée pour deux personnes à Saly.',
            'budget_xof' => 60_000,
            'city' => 'Saly',
        ]);

        ServiceRequest::factory()->status(RequestStatus::CLOTURE)->create([
            'user_id' => $client->id,
            'service_type' => ServiceType::BUILD->value,
            'message' => 'Devis pour la construction d’un mur de clôture.',
            'budget_xof' => null,
            'city' => 'Thiès',
        ]);

        $this->command?->info('DemoSeeder : demandes de démonstration créées pour le client.');
    }

    /**
     * Peuple l'écran « Mes réservations » (F3.4) du client de démonstration avec
     * quelques réservations couvrant les différents univers et statuts (nuitée,
     * véhicule, expérience, trajet). Idempotent : ne fait rien si le client
     * possède déjà des réservations. S'appuie sur les bookables de démo ; si les
     * catalogues n'ont pas encore été semés, on s'abstient sans erreur.
     */
    private function seedClientBookings(User $client): void
    {
        if (Booking::query()->where('user_id', $client->id)->exists()) {
            return;
        }

        $stay = Stay::query()->latest('id')->first();
        $vehicles = Vehicle::query()->latest('id')->take(2)->get();
        $experience = TourismExperience::query()->latest('id')->first();
        $trip = MobilityService::query()->latest('id')->first();

        // Sans bookables de démo, rien à réserver (base incomplète) : on sort.
        if (! $stay || $vehicles->count() < 2 || ! $experience || ! $trip) {
            return;
        }

        // Une nuitée confirmée (non annulable côté client : pas d'endpoint dédié).
        $this->makeBooking($client, $stay, BookingStatus::CONFIRMEE, [
            'start_date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->addDays(3)->toDateString(),
            'guests' => 2,
            'amount_xof' => 180_000,
            'caution_xof' => 100_000,
        ]);

        // Une location de véhicule confirmée à venir (annulable côté client).
        $this->makeBooking($client, $vehicles[0], BookingStatus::CONFIRMEE, [
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'guests' => 3,
            'amount_xof' => 90_000,
            'caution_xof' => 150_000,
        ]);

        // Une expérience en attente de confirmation (annulable côté client).
        $this->makeBooking($client, $experience, BookingStatus::EN_ATTENTE, [
            'start_date' => now()->addWeeks(3)->toDateString(),
            'end_date' => now()->addWeeks(3)->toDateString(),
            'guests' => 4,
            'amount_xof' => 120_000,
        ]);

        // Un trajet déjà terminé (historique, non annulable).
        $this->makeBooking($client, $trip, BookingStatus::TERMINEE, [
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->subWeeks(2)->toDateString(),
            'guests' => 1,
            'amount_xof' => 15_000,
        ]);

        // Une location de véhicule déjà annulée par le client (état terminal).
        $this->makeBooking($client, $vehicles[1], BookingStatus::ANNULEE_CLIENT, [
            'start_date' => now()->addWeeks(4)->toDateString(),
            'end_date' => now()->addWeeks(4)->addDay()->toDateString(),
            'guests' => 2,
            'amount_xof' => 60_000,
        ]);

        $this->command?->info('DemoSeeder : réservations de démonstration créées pour le client.');
    }

    /**
     * Crée une réservation de démonstration polymorphe pour le client, rattachée
     * au bookable donné, avec une référence unique.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $bookable
     * @param  array<string, mixed>  $attributes
     */
    private function makeBooking(User $client, $bookable, BookingStatus $status, array $attributes): void
    {
        Booking::create(array_merge([
            'reference' => 'BK-'.strtoupper(Str::random(8)),
            'user_id' => $client->id,
            'bookable_type' => $bookable::class,
            'bookable_id' => $bookable->id,
            'status' => $status->value,
        ], $attributes));
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
