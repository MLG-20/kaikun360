<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Façade statique d'accès aux paramètres globaux (B13.4).
 *
 * Point d'accès pratique au {@see SettingsRepository} (résolu en singleton via
 * le conteneur), utilisable partout sans injection — notamment dans les
 * calculateurs métier :
 *
 *   $rate = Settings::get('commission.default_rate', 12.0);
 */
class Settings
{
    private static function repository(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::repository()->get($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::repository()->all();
    }

    public static function set(string $key, mixed $value, ?User $actor = null): Setting
    {
        return self::repository()->set($key, $value, $actor);
    }
}
