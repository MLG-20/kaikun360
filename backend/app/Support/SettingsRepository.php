<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Dépôt des paramètres globaux (B13.4).
 *
 * Fournit un accès typé aux réglages de la plateforme, avec des valeurs par
 * défaut définies en code (DEFAULTS) et surchargées à la demande depuis la table
 * `settings`. Les lectures sont mises en cache et invalidées à chaque écriture.
 *
 * On y accède en pratique via la façade statique {@see Settings}.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'kaikun.settings';

    /**
     * Réglages connus et leurs valeurs par défaut. Toute clé absente de la table
     * `settings` retombe sur ces valeurs — ce qui garantit un comportement
     * identique tant qu'aucune surcharge n'a été saisie au back-office.
     *
     * @var array<string, array{value: mixed, type: string, group: string}>
     */
    public const DEFAULTS = [
        'commission.default_rate' => ['value' => 12.0, 'type' => 'float', 'group' => 'commissions'],
        'teambuilding.margin_rate' => ['value' => 15.0, 'type' => 'float', 'group' => 'commissions'],
        'platform.currency' => ['value' => 'XOF', 'type' => 'string', 'group' => 'general'],
        'support.email' => ['value' => 'support@kaikun360.sn', 'type' => 'string', 'group' => 'general'],
        'support.phone' => ['value' => '+221 33 000 00 00', 'type' => 'string', 'group' => 'general'],

        // Barème du simulateur de construction (B5.4, enrichi). Ces valeurs sont
        // des ORDRES DE GRANDEUR par défaut, destinés à être remplacés par
        // l'équipe (chiffres réels validés par des experts BTP) via le
        // back-office, SANS redéploiement. Structure consommée par
        // App\Modules\Build\Services\ConstructionEstimator.
        'build.pricing' => ['type' => 'json', 'group' => 'construction', 'value' => [
            // Coût de base au m² (XOF) selon l'objectif des travaux.
            'price_m2' => [
                'construction_neuve' => 250_000,
                'extension' => 220_000,
                'renovation' => 150_000,
            ],
            // Coefficient multiplicateur selon le niveau de finition.
            'finish_coeff' => [
                'economique' => 0.85,
                'standard' => 1.0,
                'premium' => 1.35,
            ],
            // Coefficient de zone géographique (surcoût logistique hors Dakar).
            'zone_coeff' => [
                'dakar' => 1.0,
                'autres_regions' => 1.06,
                'zones_eloignees' => 1.12,
            ],
            // Frais annexes officiels, en part du coût des travaux, par objectif.
            'fees' => [
                'construction_neuve' => ['etudes' => 0.08, 'permis' => 0.02, 'viabilisation' => 0.03],
                'extension' => ['etudes' => 0.08, 'permis' => 0.02, 'viabilisation' => 0.03],
                'renovation' => ['etudes' => 0.05, 'permis' => 0.01, 'viabilisation' => 0.0],
            ],
            // Frais d'acquisition du terrain (notaire, bornage, enregistrement),
            // en part du prix du terrain saisi par l'utilisateur.
            'land_acquisition_rate' => 0.10,
            // Rendement locatif brut annuel indicatif, par mode d'exploitation.
            'rental_yield' => [
                'longue_duree' => ['min' => 0.06, 'max' => 0.08],
                'nuitee' => ['min' => 0.10, 'max' => 0.14],
            ],
            // Pas d'arrondi de l'estimation (pour rester indicatif).
            'rounding_step' => 100_000,
        ]],
    ];

    /**
     * Valeur typée d'un réglage : surcharge en base, sinon défaut connu, sinon
     * la valeur de repli fournie.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return self::DEFAULTS[$key]['value'] ?? $default;
    }

    /**
     * Vue complète pour le back-office : défauts fusionnés avec les surcharges,
     * sous forme de LISTE d'objets `{ key, value, type, group, overridden }`
     * (plus commode pour le frontend qu'une map à clés pointées).
     *
     * @return list<array{key: string, value: mixed, type: string, group: ?string, overridden: bool}>
     */
    public function all(): array
    {
        $merged = [];

        foreach (self::DEFAULTS as $key => $meta) {
            $merged[$key] = [
                'key' => $key,
                'value' => $meta['value'],
                'type' => $meta['type'],
                'group' => $meta['group'],
                'overridden' => false,
            ];
        }

        foreach (Setting::all() as $setting) {
            $merged[$setting->key] = [
                'key' => $setting->key,
                'value' => $this->cast($setting->value, $setting->type),
                'type' => $setting->type,
                'group' => $setting->group,
                'overridden' => true,
            ];
        }

        return array_values($merged);
    }

    /**
     * Enregistre (ou met à jour) une surcharge. Le type/groupe d'une clé connue
     * sont conservés ; sinon ils sont déduits de la valeur.
     */
    public function set(string $key, mixed $value, ?User $actor = null): Setting
    {
        $type = self::DEFAULTS[$key]['type'] ?? $this->infer($value);
        $group = self::DEFAULTS[$key]['group'] ?? null;

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serialize($value, $type),
                'type' => $type,
                'group' => $group,
                'updated_by' => $actor?->id,
            ],
        );

        Cache::forget(self::CACHE_KEY);

        return $setting;
    }

    /**
     * Carte clé → valeur typée des surcharges en base (mise en cache).
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $this->cast($s->value, $s->type)])
                ->all();
        });
    }

    /**
     * Reconvertit une valeur texte selon son type logique.
     */
    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Sérialise une valeur en texte pour le stockage.
     */
    private function serialize(mixed $value, string $type): string
    {
        return match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Déduit un type logique d'une valeur PHP (clés non déclarées dans DEFAULTS).
     */
    private function infer(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
