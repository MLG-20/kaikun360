<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\Incident;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Manage\Models\Rent;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Enums\ProviderCategory;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Stay\Models\Stay;
use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use App\Modules\TeamBuilding\Enums\TeamBuildingRequestStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Modules\TeamBuilding\Services\TeamBuildingQuoteComposer;
use App\Enums\ReviewStatus;
use App\Services\RatingAggregator;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\QuoteReceivedNotification;
use App\Notifications\RequestStatusChangedNotification;
use Carbon\CarbonImmutable;
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

    /** Compte entreprise de démonstration : espace entreprise / team building (F6). */
    private const ENTERPRISE_EMAIL = 'demo.entreprise@kaikun360.test';

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

        // Profil + missions prestataire de démonstration pour peupler l'espace
        // prestataire : le tableau de bord (F5.1 — le compte de démo avait le rôle
        // mais aucune ligne `Provider`, d'où un 404) et les missions reçues (F5.2 —
        // statuts variés pour illustrer les actions). Idempotent (gardes propres).
        $this->seedProviderProfile($provider, $client);

        // Avis reçus de démonstration (F5.5) pour peupler l'écran « Avis reçus » :
        // quelques avis publiés sur les ressources du prestataire + un avis direct
        // (le client de démo a une mission « Traiteur mariage » terminée). La note
        // agrégée est recalculée à partir de ces avis réels. Idempotent (garde
        // propre) ; s'appuie sur les catalogues et le profil prestataire ci-dessus.
        $this->seedProviderReviews($provider, $client);

        // Gestion locative de démonstration pour peupler le tableau de bord de
        // l'espace propriétaire (F4.1) : mandats, loyers, reversements, incidents.
        // Idempotent (garde propre) ; s'appuie sur les biens de démo du
        // propriétaire ci-dessus.
        $this->seedOwnerManagement($owner);

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

        // Espace entreprise (F6) : compte entreprise de démonstration + demandes de
        // team building à divers stades (nouvelle, devis envoyé, acceptée) pour
        // peupler « Mes demandes » et le suivi des devis, plus une conversation
        // avec le support pour l'écran Messages. Idempotent (gardes propres).
        $enterprise = $this->demoUser(self::ENTERPRISE_EMAIL, 'Entreprise Démo', 'entreprise');
        $this->seedEnterpriseRequests($enterprise);
        $this->seedEnterpriseConversation($enterprise, $agent);
    }

    /**
     * Gestion locative de démonstration pour le propriétaire (F4.1) : deux
     * mandats actifs sur ses biens, avec loyers (payés / impayés), reversements
     * (effectués / en attente), un incident ouvert et une dépense — de quoi
     * remplir le tableau de bord de l'espace propriétaire (`GET /manage/dashboard`).
     *
     * Garde d'idempotence PROPRE : ne recrée rien si le propriétaire a déjà un
     * mandat. Repli silencieux s'il n'a pas encore de bien.
     */
    private function seedOwnerManagement(User $owner): void
    {
        if (ManagementMandate::where('owner_id', $owner->id)->exists()) {
            return;
        }

        // ⚠️ Cohérence AVANT tout : cet écran doit se lire d'un coup d'œil. On ne
        // tire donc PAS de montants aléatoires indépendants (loyers, dépenses,
        // reversements sans lien → chiffres absurdes : reversé > encaissé, net
        // négatif…). On raconte deux HISTOIRES simples, ancrées sur le MOIS
        // COURANT (le rapport mensuel s'ouvre dessus) : un loyer fixe par bien,
        // un seul locataire, des reversements = loyer − commission (− dépenses).

        $thisMonth = CarbonImmutable::now()->startOfMonth();
        $lastMonth = $thisMonth->subMonth();
        $twoMonthsAgo = $thisMonth->subMonths(2);

        // Crée deux biens LOCATIFS dédiés (titres explicites + loyer mensuel comme
        // prix). Kaikun fait vente ET location : ces annonces cohabitent sans souci
        // avec les biens « à vendre » du catalogue.

        // ————————————————————— Mandat A : locataire régulier —————————————————————
        // Awa Ndiaye loue l'appartement 450 000 F/mois depuis 3 mois (tout payé).
        // Une fuite d'eau a été réparée le mois dernier (55 000 F < loyer). Chaque
        // loyer encaissé est reversé net de la commission (10 %) et des dépenses ;
        // le reversement du mois courant est encore « en attente ».
        $rentA = 450_000;
        $rateA = 10.0;
        $tenantA = 'Awa Ndiaye';

        $flatA = Property::factory()->published()->create([
            'owner_id' => $owner->id,
            'type' => PropertyType::APPARTEMENT->value,
            'title' => 'Appartement F3 en location',
            'price_xof' => $rentA,
        ]);
        $mandateA = ManagementMandate::factory()->create([
            'owner_id' => $owner->id,
            'property_id' => $flatA->id,
            'commission_rate' => $rateA,
            'start_date' => $twoMonthsAgo->toDateString(),
        ]);

        foreach ([$twoMonthsAgo, $lastMonth, $thisMonth] as $month) {
            $this->rent($mandateA, $tenantA, $month, $rentA, paid: true);
        }

        $incidentA = Incident::factory()->create([
            'property_id' => $flatA->id,
            'title' => "Fuite d'eau salle de bain",
            'priority' => 'p2',
            'status' => 'resolu',
            'created_at' => $lastMonth->addDays(9),
            'resolved_at' => $lastMonth->addDays(13),
        ]);
        $expenseA = 55_000;
        Expense::factory()->create([
            'property_id' => $flatA->id,
            'incident_id' => $incidentA->id,
            'label' => 'Réparation plomberie',
            'category' => 'reparation',
            'amount_xof' => $expenseA,
            'spent_at' => $lastMonth->addDays(13)->toDateString(),
        ]);

        $commA = (int) round($rentA * $rateA / 100); // 45 000
        $this->payout($mandateA, $owner, $twoMonthsAgo, $rentA - $commA, done: true);           // 405 000
        $this->payout($mandateA, $owner, $lastMonth, $rentA - $commA - $expenseA, done: true);   // 350 000
        $this->payout($mandateA, $owner, $thisMonth, $rentA - $commA, done: false);              // 405 000 en attente

        // —————————————————— Mandat B : locataire en retard ce mois ——————————————————
        // Cheikh Fall loue la villa 800 000 F/mois (commission 12,5 %). Le mois
        // dernier est payé et reversé (700 000 F net) ; le mois courant est IMPAYÉ
        // (une relance est à faire) → illustre le suivi des impayés.
        $rentB = 800_000;
        $rateB = 12.5;
        $tenantB = 'Cheikh Fall';

        $villaB = Property::factory()->published()->create([
            'owner_id' => $owner->id,
            'type' => PropertyType::VILLA->value,
            'title' => 'Villa R+1 en location',
            'price_xof' => $rentB,
        ]);
        $mandateB = ManagementMandate::factory()->create([
            'owner_id' => $owner->id,
            'property_id' => $villaB->id,
            'commission_rate' => $rateB,
            'start_date' => $lastMonth->toDateString(),
        ]);

        $this->rent($mandateB, $tenantB, $lastMonth, $rentB, paid: true);
        $this->rent($mandateB, $tenantB, $thisMonth, $rentB, paid: false); // impayé (relance)

        $commB = (int) round($rentB * $rateB / 100); // 100 000
        $this->payout($mandateB, $owner, $lastMonth, $rentB - $commB, done: true); // 700 000
    }

    /**
     * Crée une échéance de loyer cohérente (même locataire, même montant, libellé
     * de période aligné sur `$month`). `$paid` = encaissée, sinon impayée.
     */
    private function rent(ManagementMandate $mandate, string $tenant, CarbonImmutable $month, int $amount, bool $paid): void
    {
        $factory = $paid ? Rent::factory()->paid() : Rent::factory();
        $factory->create([
            'mandate_id' => $mandate->id,
            'tenant_name' => $tenant,
            'period_label' => $month->locale('fr')->translatedFormat('F Y'),
            'due_date' => $month->toDateString(),
            'amount_xof' => $amount,
        ]);
    }

    /**
     * Crée un reversement au propriétaire pour le mois `$month`. `$done` = effectué
     * (payé en fin de mois, compté dans le rapport de ce mois), sinon en attente.
     */
    private function payout(ManagementMandate $mandate, User $owner, CarbonImmutable $month, int $amount, bool $done): void
    {
        OwnerPayout::factory()->create([
            'mandate_id' => $mandate->id,
            'owner_id' => $owner->id,
            'period_label' => $month->locale('fr')->translatedFormat('F Y'),
            'amount_xof' => $amount,
            'status' => $done ? 'effectue' : 'en_attente',
            'paid_at' => $done ? $month->endOfMonth() : null,
        ]);
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

        // Deux biens à statuts NON publiés pour l'espace propriétaire (F4.2) :
        // « Mes biens » doit montrer la variété de statuts (attente + rejet), pas
        // seulement des annonces publiées. Rattachés au même propriétaire démo.
        Property::factory()->pending()->create([
            'owner_id' => $owner->id,
            'type' => PropertyType::APPARTEMENT->value,
            'title' => 'Appartement F3 (en cours de validation)',
        ]);
        Property::factory()->rejected()->create([
            'owner_id' => $owner->id,
            'type' => PropertyType::TERRAIN->value,
            'title' => 'Terrain non titré (dossier rejeté)',
        ]);

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
     * Crée le profil prestataire (module Pro) et ses missions de démonstration
     * pour le compte prestataire de démo, afin de peupler l'espace prestataire :
     * le **tableau de bord** (F5.1 — prestataire **validé**, certifications, note)
     * et les **missions reçues** (F5.2 — cinq missions à statuts variés, dont le
     * client de démo est le donneur d'ordre).
     *
     * Deux gardes d'idempotence indépendantes (profil / missions) : la relance du
     * seeder ne crée aucun doublon, et les missions peuvent être ajoutées à un
     * prestataire déjà seedé lors d'une phase précédente.
     */
    private function seedProviderProfile(User $provider, User $client): void
    {
        // Profil marketplace (F5.1) — créé une seule fois.
        $marketplace = Provider::query()->where('user_id', $provider->id)->first();

        if (! $marketplace) {
            $marketplace = Provider::create([
                'user_id' => $provider->id,
                'business_name' => 'Teranga Événements & Transport',
                'category' => ProviderCategory::EVENEMENTIEL->value,
                'bio' => 'Organisation d\'événements, animation et transport touristique '
                    .'dans la région de Dakar et du Saloum. Équipe certifiée et véhicules '
                    .'contrôlés.',
                'status' => ProviderStatus::VALIDE->value,
                'validated_at' => CarbonImmutable::now()->subMonths(2),
                // Note initiale ; recalculée à partir des avis réels en F5.5
                // (cf. seedProviderReviews → RatingAggregator).
                'rating_avg' => 4.6,
                'rating_count' => 5,
            ]);

            // Deux certifications : une vérifiée par Kaikun, une en cours.
            $marketplace->certifications()->createMany([
                [
                    'name' => 'Licence de transport touristique',
                    'issuer' => 'Ministère du Tourisme',
                    'verified' => true,
                ],
                [
                    'name' => 'Attestation d\'assurance responsabilité civile',
                    'issuer' => 'AXA Sénégal',
                    'verified' => false,
                ],
            ]);
        }

        // Disponibilités de démonstration (F5.4) — garde propre. Planning
        // hebdomadaire (lun→ven 9h-18h, sam 9h-13h, dim fermé) + une période
        // d'indisponibilité à venir (congés).
        if (! $marketplace->weeklyAvailabilities()->exists()) {
            $open = fn (int $weekday, string $start, string $end) => [
                'weekday' => $weekday, 'is_open' => true,
                'start_time' => $start, 'end_time' => $end,
            ];
            $closed = fn (int $weekday) => [
                'weekday' => $weekday, 'is_open' => false,
                'start_time' => null, 'end_time' => null,
            ];
            $marketplace->weeklyAvailabilities()->createMany([
                $open(0, '09:00', '18:00'), // lundi
                $open(1, '09:00', '18:00'), // mardi
                $open(2, '09:00', '18:00'), // mercredi
                $open(3, '09:00', '18:00'), // jeudi
                $open(4, '09:00', '18:00'), // vendredi
                $open(5, '09:00', '13:00'), // samedi (matinée)
                $closed(6),                 // dimanche
            ]);

            $marketplace->unavailabilities()->create([
                'start_date' => CarbonImmutable::now()->addWeeks(3)->toDateString(),
                'end_date' => CarbonImmutable::now()->addWeeks(3)->addDays(5)->toDateString(),
                'reason' => 'Congés',
            ]);
        }

        // Missions de démonstration (F5.2) — garde propre, indépendante du profil
        // (permet d'ajouter les missions à un prestataire déjà seedé en F5.1).
        // Statuts variés pour illustrer TOUTES les actions possibles côté écran :
        // affectée (Accepter/Refuser), acceptée (Démarrer), en cours (Terminer),
        // et deux missions clôturées (terminée, refusée) sans action.
        if ($marketplace->missions()->exists()) {
            return;
        }

        $now = CarbonImmutable::now();

        // Une mission avec montant, commission Kaikun (12 %) et échéance.
        $mission = fn (string $title, string $desc, int $amount, string $status, ?CarbonImmutable $when) => [
            'reference' => 'MSN-'.Str::upper(Str::random(8)),
            'client_id' => $client->id,
            'title' => $title,
            'description' => $desc,
            'amount_xof' => $amount,
            'commission_xof' => (int) round($amount * 0.12),
            'status' => $status,
            'scheduled_at' => $when,
        ];

        $marketplace->missions()->createMany([
            $mission(
                'Animation gala d\'entreprise',
                'Soirée de fin d\'année pour 150 collaborateurs : animation, sonorisation et scène.',
                450_000,
                MissionStatus::AFFECTEE->value,
                $now->addDays(12),
            ),
            $mission(
                'Navette séminaire résidentiel',
                'Transport aller-retour Dakar → Saly pour un séminaire de 2 jours (2 bus).',
                320_000,
                MissionStatus::ACCEPTEE->value,
                $now->addDays(5),
            ),
            $mission(
                'Guide circuit Delta du Saloum',
                'Accompagnement d\'un groupe de 8 touristes sur 3 jours (pirogue + visites).',
                600_000,
                MissionStatus::EN_COURS->value,
                $now->subDay(),
            ),
            $mission(
                'Traiteur mariage',
                'Service traiteur pour 200 couverts, cérémonie à Dakar.',
                850_000,
                MissionStatus::TERMINEE->value,
                $now->subWeeks(3),
            ),
            $mission(
                'Animation soirée privée',
                'DJ pour un anniversaire privé (créneau déjà pris, décliné).',
                180_000,
                MissionStatus::REFUSEE->value,
                $now->subDays(2),
            ),
        ]);
    }

    /**
     * Crée des avis de démonstration reçus par le prestataire de démo (F5.5) :
     * des avis publiés sur ses ressources (véhicule, expérience) et un avis
     * **direct** déposé par le client de démo (qui a une mission terminée avec
     * lui). La note agrégée du prestataire (`rating_avg`/`rating_count`) est
     * ensuite recalculée à partir de ces avis réels — elle remplace la valeur
     * amorcée en F5.1.
     *
     * Idempotent : ne fait rien si un avis direct existe déjà pour ce prestataire.
     */
    private function seedProviderReviews(User $providerUser, User $client): void
    {
        $provider = Provider::query()->where('user_id', $providerUser->id)->first();

        if ($provider === null) {
            return;
        }

        // Garde d'idempotence : l'avis direct est le marqueur du seeding F5.5.
        $alreadySeeded = Review::query()
            ->where('reviewable_type', Provider::class)
            ->where('reviewable_id', $provider->id)
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        // Auteurs distincts (comptes clients de démonstration) pour des avis variés.
        $awa = $this->demoUser('avis.awa@kaikun360.test', 'Awa Ndiaye', 'client');
        $cheikh = $this->demoUser('avis.cheikh@kaikun360.test', 'Cheikh Fall', 'client');
        $marie = $this->demoUser('avis.marie@kaikun360.test', 'Marie Sagna', 'client');

        $vehicle = Vehicle::query()->where('provider_id', $providerUser->id)->first();
        $experience = TourismExperience::query()->where('provider_id', $providerUser->id)->first();

        // Avis publiés sur les ressources du prestataire.
        if ($vehicle) {
            $this->publishReview($awa, $vehicle, 5, 'Chauffeur ponctuel et véhicule impeccable.');
            $this->publishReview($cheikh, $vehicle, 4, 'Très bon trajet, climatisation au top.');
        }

        if ($experience) {
            $this->publishReview($marie, $experience, 5, 'Une expérience inoubliable dans le Delta du Saloum.');
            $this->publishReview($awa, $experience, 4, 'Guide passionnant, à recommander.');
        }

        // Avis DIRECT : le client de démo a une mission « Traiteur mariage » terminée.
        $this->publishReview($client, $provider, 5, 'Prestation traiteur remarquable, service très soigné.');

        // La note affichée reflète désormais les avis réellement présents.
        app(RatingAggregator::class)->recomputeForProviderUser($providerUser->id);
    }

    /**
     * Crée un avis PUBLIÉ (déjà modéré) porté par `$author` sur la ressource
     * `$reviewable` (véhicule, expérience ou prestataire). Utilitaire de
     * démonstration : contourne volontairement la vérification d'éligibilité.
     */
    private function publishReview(User $author, \Illuminate\Database\Eloquent\Model $reviewable, int $rating, string $comment): void
    {
        Review::create([
            'reference' => 'REV-'.Str::upper(Str::random(8)),
            'user_id' => $author->id,
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
            'rating' => $rating,
            'comment' => $comment,
            'status' => ReviewStatus::PUBLIE->value,
            'moderated_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(2, 40)),
        ]);
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
     * Peuple l'écran « Mes favoris » du client de démonstration avec des favoris
     * de PLUSIEURS univers (favoris polymorphes) : deux biens, une nuitée, un
     * véhicule et une expérience — pour illustrer un espace favoris multi-univers.
     * Idempotent : ne fait rien si le client possède déjà des favoris. Chaque
     * cible n'est prise que si elle existe et est visible (publiée / réservable).
     */
    private function seedClientFavorites(User $client): void
    {
        if ($client->favorites()->exists()) {
            return;
        }

        // Une cible par univers (les deux biens les plus récents + une nuitée, un
        // véhicule, une expérience), en ne gardant que ce qui existe réellement.
        $targets = collect()
            ->concat(Property::published()->latest('id')->take(2)->get())
            ->push(Stay::bookable()->latest('id')->first())
            ->push(Vehicle::published()->latest('id')->first())
            ->push(TourismExperience::published()->latest('id')->first())
            ->filter();

        if ($targets->isEmpty()) {
            return;
        }

        foreach ($targets as $target) {
            $client->favorites()->firstOrCreate([
                'favoritable_type' => $target::class,
                'favoritable_id' => $target->getKey(),
            ]);
        }

        $this->command?->info('DemoSeeder : favoris de démonstration (multi-univers) créés pour le client.');
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
     * Demandes de team building de démonstration pour l'espace entreprise (F6).
     *
     * Trois demandes couvrant le cycle décrit au cahier §9.4, afin d'illustrer
     * chaque état de l'écran de suivi :
     *   1. NOUVEAU — déposée, en attente d'étude (aucun devis) ;
     *   2. DEVIS_ENVOYE — l'admin a composé et envoyé un devis (statut `envoye`),
     *      l'entreprise peut l'accepter ;
     *   3. ACCEPTE — devis accepté, suivi opérationnel amorcé.
     *
     * Les devis sont composés par le vrai service `TeamBuildingQuoteComposer`
     * (lignes + marge + totaux figés) pour rester fidèles au calcul de production.
     *
     * Garde d'idempotence PROPRE : ne recrée rien si l'entreprise a déjà une demande.
     */
    private function seedEnterpriseRequests(User $enterprise): void
    {
        if (TeamBuildingRequest::query()->where('company_id', $enterprise->id)->exists()) {
            return;
        }

        $composer = app(TeamBuildingQuoteComposer::class);

        // 1) Nouvelle demande (aucun devis encore) — colonne « en attente d'étude ».
        TeamBuildingRequest::create([
            'reference' => 'TBR-'.Str::upper(Str::random(8)),
            'company_id' => $enterprise->id,
            'participants' => 45,
            'city' => 'Saly',
            'start_date' => now()->addMonths(2)->toDateString(),
            'end_date' => now()->addMonths(2)->addDays(2)->toDateString(),
            'budget_xof' => 6_000_000,
            'needs' => ['hebergement' => true, 'restauration' => true, 'activite' => true, 'mobilite' => true],
            'description' => 'Séminaire annuel de cohésion pour nos équipes commerciales.',
            'status' => TeamBuildingRequestStatus::NOUVEAU->value,
        ]);

        // 2) Demande avec un devis ENVOYÉ (l'entreprise peut l'accepter).
        $sentRequest = TeamBuildingRequest::create([
            'reference' => 'TBR-'.Str::upper(Str::random(8)),
            'company_id' => $enterprise->id,
            'participants' => 30,
            'city' => 'Dakar',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDay()->toDateString(),
            'budget_xof' => 3_500_000,
            'needs' => ['restauration' => true, 'activite' => true, 'animation' => true],
            'description' => 'Journée cohésion pour le lancement de notre nouveau produit.',
            'status' => TeamBuildingRequestStatus::DEVIS_ENVOYE->value,
        ]);
        $sentQuote = $composer->composeFor($sentRequest, [
            ['category' => 'activite', 'label' => 'Ateliers cohésion', 'module' => 'explore', 'quantity' => 30, 'unit_price_xof' => 25_000],
            ['category' => 'restauration', 'label' => 'Déjeuner traiteur', 'quantity' => 30, 'unit_price_xof' => 15_000],
            ['category' => 'animation', 'label' => 'Animateur événementiel', 'quantity' => 1, 'unit_price_xof' => 250_000],
        ]);
        $sentQuote->update([
            'status' => TeamBuildingQuoteStatus::ENVOYE->value,
            'sent_at' => now()->subDays(2),
        ]);

        // 3) Demande ACCEPTÉE (devis accepté, suivi opérationnel en cours).
        $acceptedRequest = TeamBuildingRequest::create([
            'reference' => 'TBR-'.Str::upper(Str::random(8)),
            'company_id' => $enterprise->id,
            'participants' => 60,
            'city' => 'Sine-Saloum',
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->subWeeks(2)->addDays(3)->toDateString(),
            'budget_xof' => 12_000_000,
            'needs' => ['hebergement' => true, 'restauration' => true, 'activite' => true, 'mobilite' => true, 'animation' => true],
            'description' => 'Séminaire résidentiel de direction (3 nuits).',
            'status' => TeamBuildingRequestStatus::ACCEPTE->value,
        ]);
        $acceptedQuote = $composer->composeFor($acceptedRequest, [
            ['category' => 'hebergement', 'label' => 'Lodge (3 nuits)', 'module' => 'stay', 'quantity' => 60, 'unit_price_xof' => 120_000],
            ['category' => 'restauration', 'label' => 'Pension complète', 'quantity' => 60, 'unit_price_xof' => 45_000],
            ['category' => 'mobilite', 'label' => 'Bus aller-retour', 'module' => 'mobility', 'quantity' => 2, 'unit_price_xof' => 400_000],
            ['category' => 'activite', 'label' => 'Excursion pirogue', 'module' => 'explore', 'quantity' => 60, 'unit_price_xof' => 20_000],
        ]);
        $acceptedQuote->update([
            'status' => TeamBuildingQuoteStatus::ACCEPTE->value,
            'sent_at' => now()->subWeeks(4),
            'accepted_at' => now()->subWeeks(3),
        ]);

        $this->command?->info('DemoSeeder : demandes de team building créées pour l\'entreprise.');
    }

    /**
     * Une conversation entreprise ↔ support Kaikun pour peupler l'écran
     * « Messages » de l'espace entreprise (F6, cahier §5 « Messages = Tous »).
     * Garde d'idempotence propre (aucune conversation existante pour l'entreprise).
     */
    private function seedEnterpriseConversation(User $enterprise, User $agent): void
    {
        if ($enterprise->conversations()->exists()) {
            return;
        }

        $this->makeConversation(
            'Organisation de votre séminaire',
            [
                [$enterprise, 'Bonjour, nous souhaitons organiser un séminaire pour 30 personnes le mois prochain.'],
                [$agent, 'Bonjour ! Avec plaisir. Je prépare une proposition et vous envoie un devis détaillé.'],
                [$agent, 'Le devis vient de vous être envoyé, vous pouvez le consulter dans « Mes demandes ».'],
            ],
            // L'entreprise a lu jusqu'à son propre message : les 2 réponses de
            // l'agent restent non lues (2 non-lus attendus sur ce fil).
            readBy: [$enterprise->id => 0],
        );

        $this->command?->info('DemoSeeder : conversation de démonstration créée pour l\'entreprise.');
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
