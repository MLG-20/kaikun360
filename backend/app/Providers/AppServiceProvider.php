<?php

namespace App\Providers;

use App\Events\RequestCreated;
use App\Events\RequestStatusChanged;
use App\Listeners\NotifyAvailableAgentsOfRequest;
use App\Listeners\NotifyUserOfRequestStatusChange;
use App\Models\Quote;
use App\Models\Review;
use App\Models\User;
use App\Modules\Assistant\Brains\RuleBasedBrain;
use App\Modules\Assistant\Contracts\AssistantBrain;
use App\Modules\Assistant\Tools\BackOffice\AccountLookupTool;
use App\Modules\Assistant\Tools\BackOffice\PaymentLookupTool;
use App\Modules\Assistant\Tools\BackOffice\PendingRequestsTool;
use App\Modules\Assistant\Tools\BackOffice\PlatformActivityTool;
use App\Modules\Assistant\Tools\BackOffice\SupportInboxTool;
use App\Modules\Assistant\Tools\BackOffice\ValidationQueueTool;
use App\Modules\Assistant\Tools\FaqTool;
use App\Modules\Assistant\Tools\MyBookingsTool;
use App\Modules\Assistant\Tools\MyDiasporaProjectsTool;
use App\Modules\Assistant\Tools\MyMissionsTool;
use App\Modules\Assistant\Tools\MyPropertiesTool;
use App\Modules\Assistant\Tools\MyRequestsTool;
use App\Modules\Assistant\Tools\SearchCatalogTool;
use App\Modules\Assistant\Tools\SupportEscalationTool;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Build\Events\ConstructionQuoteSent;
use App\Modules\Build\Events\ConstructionRequestCreated;
use App\Modules\Build\Listeners\NotifyAdminsOfConstructionRequest;
use App\Modules\Build\Listeners\NotifyClientOfConstructionQuote;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Build\Policies\ConstructionRequestPolicy;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Policies\UserPolicy;
use App\Modules\Diaspora\Models\DiasporaProject;
use App\Modules\Diaspora\Policies\DiasporaProjectPolicy;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Policies\ExperiencePolicy;
use App\Modules\Immo\Events\PropertyCreated;
use App\Modules\Immo\Events\PropertyValidated;
use App\Modules\Immo\Listeners\NotifyAgentsOfNewProperty;
use App\Modules\Immo\Listeners\NotifyOwnerOfPropertyValidated;
use App\Modules\Immo\Models\Property;
use App\Modules\Immo\Policies\PropertyPolicy;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Policies\ManagementMandatePolicy;
use App\Modules\Mobility\Events\MobilityServiceCreated;
use App\Modules\Mobility\Events\VehicleCreated;
use App\Modules\Mobility\Events\VehicleValidated;
use App\Modules\Mobility\Listeners\NotifyAgentsOfNewMobilityService;
use App\Modules\Mobility\Listeners\NotifyAgentsOfNewVehicle;
use App\Modules\Mobility\Listeners\NotifyProviderOfVehicleValidated;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Mobility\Policies\MobilityServicePolicy;
use App\Modules\Mobility\Policies\VehiclePolicy;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Policies\ProviderPolicy;
use App\Modules\TeamBuilding\Events\QuoteAccepted;
use App\Modules\TeamBuilding\Events\QuoteSent;
use App\Modules\TeamBuilding\Events\TeamBuildingRequestCreated;
use App\Modules\TeamBuilding\Listeners\NotifyAdminsOfTeamBuildingRequest;
use App\Modules\TeamBuilding\Listeners\NotifyCompanyOfQuoteSent;
use App\Modules\TeamBuilding\Listeners\StartOperationalFollowUp;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Modules\TeamBuilding\Policies\TeamBuildingRequestPolicy;
use App\Policies\QuotePolicy;
use App\Policies\ReviewPolicy;
use App\Support\Auth\GoogleIdTokenVerifier;
use App\Support\Auth\GoogleTokenVerifier;
use App\Support\Notifications\LogSmsProvider;
use App\Support\Notifications\OrangeSmsProvider;
use App\Support\Notifications\SmsChannel;
use App\Support\Notifications\SmsProviderInterface;
use App\Support\Notifications\TwilioSmsProvider;
use App\Support\Payments\PaymentProviderInterface;
use App\Support\Payments\PaytechProvider;
use App\Support\Payments\PaytechWebhookVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistrement de services dans le conteneur (rien pour l'instant).
     */
    public function register(): void
    {
        // B14 — Le contrat de paiement est résolu vers PayTech (seule
        // implémentation active). Les modules ne dépendent que de l'interface.
        $this->app->singleton(PaymentProviderInterface::class, function () {
            $config = config('services.paytech');

            return new PaytechProvider(
                baseUrl: $config['base_url'],
                apiKey: $config['api_key'] ?? null,
                apiSecret: $config['api_secret'] ?? null,
                env: $config['env'] ?? 'test',
                ipnUrl: $config['ipn_url'] ?? null,
                successUrl: $config['success_url'] ?? null,
                cancelUrl: $config['cancel_url'] ?? null,
            );
        });

        // Vérificateur de signature des webhooks PayTech (B14.3).
        // Vérification des notifications PayTech : les DEUX clés sont
        // nécessaires — l'API_KEY entre dans le message signé, l'API_SECRET est
        // la clé de signature.
        $this->app->singleton(PaytechWebhookVerifier::class, function () {
            return new PaytechWebhookVerifier(
                config('services.paytech.api_key'),
                config('services.paytech.api_secret'),
            );
        });

        // Fournisseur SMS (B16.1/B18.2) : Twilio, Orange, sinon journalisation.
        $this->app->singleton(SmsProviderInterface::class, function () {
            $provider = config('services.sms.provider');

            if ($provider === 'twilio') {
                $twilio = config('services.sms.twilio');

                return new TwilioSmsProvider($twilio['sid'] ?? null, $twilio['token'] ?? null, $twilio['from'] ?? null);
            }

            if ($provider === 'orange') {
                $orange = config('services.sms.orange');

                return new OrangeSmsProvider(
                    $orange['client_id'] ?? null,
                    $orange['client_secret'] ?? null,
                    $orange['base_url'] ?? 'https://api.orange.com',
                    $orange['token_url'] ?? 'https://api.orange.com/oauth/v3/token',
                    $orange['sender_address'] ?? null,
                    $orange['sender_name'] ?? null,
                );
            }

            return new LogSmsProvider;
        });

        // Vérificateur d'ID token Google (B19).
        $this->app->bind(GoogleTokenVerifier::class, function () {
            return new GoogleIdTokenVerifier(config('services.google.client_id'));
        });

        $this->registerAssistant();
    }

    /**
     * Assistant Kaikun (F10.0) : trousse à outils et cerveau interchangeable.
     *
     * Deux liaisons, et c'est tout le câblage du module :
     *
     *   - le REGISTRE reçoit la liste complète des outils connus ; c'est lui
     *     qui filtrera ensuite selon le rôle de l'appelant. Ajouter un outil
     *     pour les espaces connectés (F10.2) ou le back-office (F10.3) se
     *     réduira à une ligne dans ce tableau.
     *   - le CERVEAU est résolu depuis la configuration. Toute valeur inconnue
     *     retombe sur le déterministe : en production, une faute de frappe dans
     *     `ASSISTANT_DRIVER` doit dégrader le service, pas l'interrompre.
     */
    protected function registerAssistant(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry([
                // Publics (F10.0) — ouverts à tout le monde, visiteurs compris.
                $app->make(SearchCatalogTool::class),
                $app->make(FaqTool::class),
                $app->make(SupportEscalationTool::class),

                // Espaces connectés (F10.2) — chacun se réserve à ses rôles et
                // ne lit que les dossiers de l'appelant. Les ajouter ici ne les
                // ouvre à personne : c'est `isAvailableFor()` qui décide, outil
                // par outil, et le registre ne présente au cerveau que ceux qui
                // ont répondu oui.
                $app->make(MyBookingsTool::class),
                $app->make(MyRequestsTool::class),
                $app->make(MyPropertiesTool::class),
                $app->make(MyMissionsTool::class),
                $app->make(MyDiasporaProjectsTool::class),

                // Back-office (F10.3) — LECTURE SEULE, et filtrés non par rôle
                // mais par PERMISSION FINE (cf. BackOfficeTool) : depuis F7.1.b
                // le back-office délègue dossier par dossier, deux agents de la
                // même équipe n'ont donc pas la même trousse. Aucun de ces
                // outils n'écrit : valider, confirmer un règlement ou répondre à
                // un client restent des gestes d'écran.
                $app->make(PlatformActivityTool::class),
                $app->make(ValidationQueueTool::class),
                $app->make(PendingRequestsTool::class),
                $app->make(SupportInboxTool::class),
                $app->make(AccountLookupTool::class),
                $app->make(PaymentLookupTool::class),
            ]);
        });

        $this->app->bind(AssistantBrain::class, function ($app) {
            return match (config('assistant.driver')) {
                // 'claude' => $app->make(ClaudeBrain::class),  // F10.4
                default => $app->make(RuleBasedBrain::class),
            };
        });
    }

    /**
     * Amorçage des services de l'application.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureEvents();

        // Canal de notification « sms » (B16.1) branché sur le SmsProviderInterface.
        Notification::resolved(function ($service) {
            $service->extend('sms', fn ($app) => $app->make(SmsChannel::class));
        });
    }

    /**
     * Associe les événements métier à leurs listeners.
     *
     * Enregistrement explicite car les listeners vivent dans les modules
     * (hors de app/Listeners), donc non couverts par l'auto-découverte.
     */
    protected function configureEvents(): void
    {
        Event::listen(PropertyCreated::class, NotifyAgentsOfNewProperty::class);
        Event::listen(PropertyValidated::class, NotifyOwnerOfPropertyValidated::class);
        Event::listen(VehicleCreated::class, NotifyAgentsOfNewVehicle::class);
        // F8.23 — Départ programmé déposé par un prestataire. Même file de
        // validation que les véhicules, mais l'alerte porte la DATE du départ :
        // un trajet validé après son heure ne se vend plus.
        Event::listen(MobilityServiceCreated::class, NotifyAgentsOfNewMobilityService::class);
        Event::listen(VehicleValidated::class, NotifyProviderOfVehicleValidated::class);
        Event::listen(TeamBuildingRequestCreated::class, NotifyAdminsOfTeamBuildingRequest::class);
        Event::listen(QuoteSent::class, NotifyCompanyOfQuoteSent::class);
        // F3.9 — Devis de CHANTIER envoyé au client (à ne pas confondre avec
        // `QuoteSent`, qui est le devis pack du team building).
        Event::listen(ConstructionQuoteSent::class, NotifyClientOfConstructionQuote::class);
        // F8.15.b — Nouveau CHANTIER déposé depuis la page publique. Il n'y
        // avait aucune alerte : le dossier arrivait en base sans que personne
        // ne l'apprenne, faute d'écran public qui alimente cette table.
        Event::listen(ConstructionRequestCreated::class, NotifyAdminsOfConstructionRequest::class);
        Event::listen(QuoteAccepted::class, StartOperationalFollowUp::class);
        Event::listen(RequestCreated::class, NotifyAvailableAgentsOfRequest::class);
        Event::listen(RequestStatusChanged::class, NotifyUserOfRequestStatusChange::class);
    }

    /**
     * Règles d'autorisation globales.
     *
     * Gate::before s'exécute AVANT toute vérification de permission/policy :
     * si l'utilisateur est super_admin, on autorise tout (retour true). Sinon,
     * on retourne null pour laisser la vérification normale suivre son cours.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole(UserRole::SUPER_ADMIN->value) ? true : null;
        });

        // Policies des modules.
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(ManagementMandate::class, ManagementMandatePolicy::class);
        Gate::policy(ConstructionRequest::class, ConstructionRequestPolicy::class);
        Gate::policy(TourismExperience::class, ExperiencePolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(MobilityService::class, MobilityServicePolicy::class);
        Gate::policy(DiasporaProject::class, DiasporaProjectPolicy::class);
        Gate::policy(TeamBuildingRequest::class, TeamBuildingRequestPolicy::class);
        Gate::policy(Provider::class, ProviderPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
    }

    /**
     * Définit le limiteur de débit "api" appliqué à toute l'API.
     *
     * Règle : 60 requêtes par minute, comptées par utilisateur connecté
     * (via son id) ou, pour un visiteur anonyme, par adresse IP.
     * Cela protège l'API contre les abus et les attaques par saturation basiques.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Endpoints d'authentification (register, login, vérification, mot de
        // passe) : plafond serré par IP contre le bourrinage / l'énumération.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Endpoints de paiement : plafond par utilisateur (ou IP) pour limiter
        // les tentatives répétées d'initiation / remboursement.
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // Assistant (F10.0) : plafond volontairement bas — une conversation
        // humaine ne dépasse pas quelques messages par minute. C'est la parade
        // au « déni de portefeuille » : dès que le driver Claude sera actif
        // (F10.4), chaque message coûtera de l'argent réel, et un endpoint
        // d'assistant non bridé est une facture ouverte à tout Internet.
        //
        // Le comptage se fait par utilisateur connecté, sinon par IP : un
        // visiteur anonyme ne peut pas diluer son débit en ouvrant des onglets.
        RateLimiter::for('assistant', function (Request $request) {
            return Limit::perMinute((int) config('assistant.rate_limit.per_minute', 12))
                ->by($request->user('sanctum')?->id ?: $request->ip());
        });
    }
}
