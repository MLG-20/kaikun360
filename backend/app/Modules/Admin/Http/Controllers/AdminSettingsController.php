<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\UpdateSettingsRequest;
use App\Support\ApiResponse;
use App\Support\Notifications\NotificationEvent;
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
        return ApiResponse::success([
            'settings' => $settings->all(),
            // F7.2.l — Catalogue des événements notifiables (libellé, public
            // visé, état actif). Il vit dans le code, pas en base : l'envoyer
            // avec les réglages évite au back-office de dupliquer ces libellés.
            'notification_events' => NotificationEvent::catalog(),
        ]);
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

            // Un réglage `json` doit rester structuré : une chaîne sérialisée
            // deux fois est un piège classique côté client.
            if ($type === 'json' && ! is_array($value)) {
                throw ValidationException::withMessages(["settings.{$key}" => ['Structure attendue (objet ou tableau).']]);
            }

            // Les liens de réseaux sociaux atterrissent dans le pied de page
            // PUBLIC : une adresse mal saisie y resterait visible et cliquable.
            // Vide = réseau simplement masqué (cas normal, pas une erreur).
            if (str_starts_with($key, 'social.') && $value !== '' && $value !== null) {
                if (! filter_var($value, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', (string) $value)) {
                    throw ValidationException::withMessages([
                        "settings.{$key}" => ['Adresse invalide : indiquez une URL complète (https://…) ou laissez le champ vide.'],
                    ]);
                }
            }
        }

        // F7.2.l — La carte des événements notifiables est le seul réglage dont
        // le CONTENU est contraint : une clé inconnue serait ignorée en silence
        // par NotificationSettings (tout événement absent est actif), ce qui
        // ferait croire à l'équipe qu'une notification est coupée alors qu'elle
        // continue de partir. On la refuse donc explicitement.
        if (array_key_exists('notifications.events', $input)) {
            $known = NotificationEvent::values();

            foreach ($input['notifications.events'] as $event => $enabled) {
                if (! in_array($event, $known, true)) {
                    throw ValidationException::withMessages([
                        "settings.notifications.events.{$event}" => ['Événement de notification inconnu.'],
                    ]);
                }

                if (! is_bool($enabled)) {
                    throw ValidationException::withMessages([
                        "settings.notifications.events.{$event}" => ['Valeur booléenne attendue.'],
                    ]);
                }
            }
        }

        foreach ($input as $key => $value) {
            $settings->set($key, $value, $request->user());
        }

        return ApiResponse::success([
            'settings' => $settings->all(),
            'notification_events' => NotificationEvent::catalog(),
        ]);
    }
}
