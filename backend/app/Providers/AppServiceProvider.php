<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
