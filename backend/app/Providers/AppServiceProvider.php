<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Policies\UserPolicy;
use App\Modules\Immo\Events\PropertyCreated;
use App\Modules\Immo\Events\PropertyValidated;
use App\Modules\Immo\Listeners\NotifyAgentsOfNewProperty;
use App\Modules\Immo\Listeners\NotifyOwnerOfPropertyValidated;
use App\Modules\Immo\Models\Property;
use App\Modules\Immo\Policies\PropertyPolicy;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Policies\ManagementMandatePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistrement de services dans le conteneur (rien pour l'instant).
     */
    public function register(): void
    {
        //
    }

    /**
     * Amorçage des services de l'application.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureEvents();
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
    }
}
