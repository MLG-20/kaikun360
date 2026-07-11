<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\UpdateSettingsRequest;
use App\Support\ApiResponse;
use App\Support\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Paramétrage global de la plateforme (B13.4) : commissions, tarifs,
 * coordonnées… Réservé à la permission `gerer:parametres`.
 *
 * Les réglages connus (SettingsRepository::DEFAULTS) sont toujours renvoyés,
 * avec leur valeur effective (surcharge en base ou valeur par défaut).
 */
class AdminSettingsController extends Controller
{
    /**
     * Liste des réglages effectifs. GET /api/v1/admin/settings
     */
    public function index(SettingsRepository $settings): JsonResponse
    {
        return ApiResponse::success(['settings' => $settings->all()]);
    }

    /**
     * Met à jour un lot de réglages. PATCH /api/v1/admin/settings
     *
     * Corps : { "settings": { "commission.default_rate": 10, ... } }.
     */
    public function update(UpdateSettingsRequest $request, SettingsRepository $settings): JsonResponse
    {
        $input = $request->validated()['settings'];

        // Seules les clés connues sont modifiables (évite de polluer la table
        // avec des réglages fantômes) ; les taux/nombres doivent être numériques.
        foreach ($input as $key => $value) {
            if (! array_key_exists($key, SettingsRepository::DEFAULTS)) {
                throw ValidationException::withMessages(["settings.{$key}" => ['Clé de réglage inconnue.']]);
            }

            $type = SettingsRepository::DEFAULTS[$key]['type'];
            if (in_array($type, ['float', 'integer'], true) && ! is_numeric($value)) {
                throw ValidationException::withMessages(["settings.{$key}" => ['Valeur numérique attendue.']]);
            }
        }

        foreach ($input as $key => $value) {
            $settings->set($key, $value, $request->user());
        }

        return ApiResponse::success(['settings' => $settings->all()]);
    }
}
