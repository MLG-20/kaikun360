<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\QuoteReceivedNotification;
use App\Notifications\RequestStatusChangedNotification;
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

    /** Agent Kaikun de démonstration : correspondant « support » du client (F3.7). */
    private const AGENT_EMAIL = 'demo.agent@kaikun360.test';

    public function run(): void
    {
        $owner = $this->demoUser(self::OWNER_EMAIL, 'Propriétaire Démo', 'proprietaire');
        $provider = $this->demoUser(self::PROVIDER_EMAIL, 'Prestataire Démo', 'prestataire');
        // Agent Kaikun : joue le « support » dans la messagerie de démo (F3.7).
        $agent = $this->demoUser(self::AGENT_EMAIL, 'Agent Kaikun', 'agent_kaikun');

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

        // Quelques biens en favori pour peupler l'écran « Mes favoris » (F3.5)
        // de l'espace client. Idempotent (garde propre) ; s'appuie sur les biens
        // publiés de démo ci-dessus.
        $this->seedClientFavorites($client);

        // Quelques notifications « base de données » pour peupler l'écran
        // « Mes notifications » (F3.6) de l'espace client. Idempotent (garde propre).
        $this->seedClientNotifications($client);

        // Deux conversations pour peupler l'écran « Messages » (F3.7) de l'espace
        // client : une avec le support Kaikun, une avec le propriétaire de démo.
        // Idempotent (garde propre).
        $this->seedClientConversations($client, $agent, $owner);
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
     * Peuple l'écran « Mes favoris » (F3.5) du client de démonstration avec
     * quelques biens immobiliers publiés. Idempotent : ne fait rien si le client
     * possède déjà des favoris. Si aucun bien publié n'existe (base incomplète),
     * on s'abstient sans erreur.
     */
    private function seedClientFavorites(User $client): void
    {
        if ($client->favoriteProperties()->exists()) {
            return;
        }

        // Trois biens publiés les plus récents (l'ajout aux favoris exige un
        // bien publié, cf. FavoriteController@store).
        $properties = Property::query()
            ->where('status', PropertyStatus::PUBLIE)
            ->latest('id')
            ->take(3)
            ->get();

        if ($properties->isEmpty()) {
            return;
        }

        $client->favoriteProperties()->syncWithoutDetaching($properties->pluck('id')->all());

        $this->command?->info('DemoSeeder : favoris de démonstration créés pour le client.');
    }

    /**
     * Crée quelques notifications « base de données » de démonstration pour le
     * client, afin que l'écran « Mes notifications » (F3.6) ne soit pas vide.
     *
     * On insère directement dans la table `notifications` via la relation
     * Notifiable (déterministe, sans déclencher un flux métier réel) ; la charge
     * utile `data` reproduit exactement ce que produisent les `toArray()` des
     * notifications concernées, telles que les lit NotificationResource.
     * Garde d'idempotence PROPRE : rien n'est recréé si le client en a déjà.
     */
    private function seedClientNotifications(User $client): void
    {
        if ($client->notifications()->exists()) {
            return;
        }

        // Trois notifications : deux non lues (demande + devis) et une déjà lue
        // (réservation), pour illustrer la pastille de non-lues et l'état « lu ».
        $notifications = [
            [
                'type' => RequestStatusChangedNotification::class,
                'read_at' => null,
                'data' => [
                    'category' => 'request',
                    'title' => 'Votre demande a avancé',
                    'body' => 'La demande « REQ-DEMO-01 » est passée au statut : Devis.',
                    'action_url' => '/mon-espace/demandes',
                ],
            ],
            [
                'type' => QuoteReceivedNotification::class,
                'read_at' => null,
                'data' => [
                    'category' => 'quote',
                    'title' => 'Vous avez reçu un devis',
                    'body' => 'Un devis vous a été transmis pour la demande « REQ-DEMO-01 ».',
                    'action_url' => '/mon-espace/demandes',
                ],
            ],
            [
                'type' => BookingConfirmedNotification::class,
                'read_at' => now()->subDay(),
                'data' => [
                    'category' => 'booking',
                    'title' => 'Votre réservation est confirmée',
                    'body' => 'Votre réservation « BK-DEMO-01 » a bien été confirmée.',
                    'action_url' => '/mon-espace/reservations',
                ],
            ],
        ];

        foreach ($notifications as $notification) {
            $client->notifications()->create(array_merge(
                ['id' => (string) Str::uuid()],
                $notification,
            ));
        }

        $this->command?->info('DemoSeeder : notifications de démonstration créées pour le client.');
    }

    /**
     * Crée deux conversations de démonstration pour le client (écran « Messages »,
     * F3.7) : une avec le support Kaikun (agent) et une avec le propriétaire.
     *
     * Garde d'idempotence PROPRE : on ne recrée rien si le client a déjà des
     * conversations, afin que la relance du seeder reste sans doublon. La
     * première laisse un dernier message NON LU (émis par l'agent) pour illustrer
     * la pastille de non-lus ; la seconde est entièrement lue par le client.
     */
    private function seedClientConversations(User $client, User $agent, User $owner): void
    {
        if ($client->conversations()->exists()) {
            return;
        }

        // 1) Support Kaikun — dernier message de l'agent NON lu par le client.
        $this->makeConversation(
            'Bienvenue sur Kaikun 360',
            [
                [$client, 'Bonjour, je découvre la plateforme, pouvez-vous m’aider ?'],
                [$agent, 'Bien sûr ! Je suis votre conseiller Kaikun, posez-moi vos questions.'],
                [$agent, 'N’hésitez pas : je reste disponible pour toute demande.'],
            ],
            // Le client a lu jusqu'au 1er échange : les 2 messages de l'agent
            // restants sont « non lus » (2 non-lus attendus sur ce fil).
            readBy: [$client->id => 0],
        );

        // 2) Propriétaire — conversation entièrement lue par le client.
        $this->makeConversation(
            'Visite de la villa aux Almadies',
            [
                [$client, 'Bonjour, la villa est-elle disponible le mois prochain ?'],
                [$owner, 'Bonjour, oui, elle est libre à partir du 5. Souhaitez-vous la visiter ?'],
                [$client, 'Parfait, je vous confirme une date très vite. Merci !'],
            ],
            // Tout est lu de part et d'autre (aucun non-lu).
            readBy: 'all',
        );

        $this->command?->info('DemoSeeder : conversations de démonstration créées pour le client.');
    }

    /**
     * Fabrique une conversation avec sa suite de messages, en horodatant chaque
     * message de façon croissante et en positionnant `last_read_at` par participant.
     *
     * @param  array<int, array{0: User, 1: string}>  $messages  couples [auteur, corps]
     * @param  array<int, int>|string  $readBy  'all' = tout le monde a tout lu ;
     *         sinon map [user_id => index du dernier message lu] (les messages au-delà
     *         restent non lus). Les participants absents de la map n'ont rien lu.
     */
    private function makeConversation(string $subject, array $messages, array|string $readBy = 'all'): void
    {
        $conversation = Conversation::create(['subject' => $subject]);

        // Participants = tous les auteurs distincts des messages.
        $participants = collect($messages)->map(fn ($m) => $m[0])->unique('id');
        $conversation->participants()->attach($participants->pluck('id')->all());

        // Horodatage croissant : le 1er message il y a N heures, le dernier récent.
        $count = count($messages);
        $createdRows = [];
        foreach (array_values($messages) as $index => [$author, $body]) {
            $at = now()->subHours($count - $index);
            $message = $conversation->messages()->create([
                'sender_id' => $author->id,
                'body' => $body,
            ]);
            // On force l'horodatage (create() met « maintenant » par défaut).
            $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            $createdRows[$index] = $message;
        }

        $last = end($createdRows);
        $conversation->update(['last_message_at' => $last->created_at]);

        // Positionne le last_read_at de chaque participant.
        foreach ($participants as $participant) {
            if ($readBy === 'all') {
                $readAt = $last->created_at;
            } else {
                $readIndex = $readBy[$participant->id] ?? -1;
                $readAt = $readIndex >= 0 ? $createdRows[$readIndex]->created_at : null;
            }

            $conversation->participants()->updateExistingPivot($participant->id, [
                'last_read_at' => $readAt,
            ]);
        }
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
