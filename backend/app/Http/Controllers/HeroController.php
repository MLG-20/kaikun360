<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\Heroes\HeroCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Bandeaux d'en-tête des pages publiques (F12) — lecture publique.
 *
 * Le frontend appelle cet endpoint une seule fois et sert toutes ses pages avec
 * la réponse : un bandeau pèse quatre champs, les envoyer tous coûte moins
 * qu'un aller-retour par page. L'édition passe par le back-office
 * (`/admin/heroes`, permission `gerer:parametres`).
 */
class HeroController extends Controller
{
    /**
     * Bandeaux effectifs, héritage d'image déjà appliqué.
     * GET /api/v1/heroes (public).
     *
     * Réponse : `{ heroes: { "immobilier": { image, eyebrow, title, lead }, … } }`.
     * Les clés dont rien n'a été personnalisé sont absentes — la page concernée
     * garde alors exactement son apparence d'origine.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            // Cast en objet : sans lui, une plateforme fraîchement installée
            // (aucun bandeau saisi) renverrait `[]`, un tableau JSON, là où le
            // contrat côté frontend est une **map** clé → bandeau. Même piège
            // que les réseaux sociaux de `GET /contact-info`.
            'heroes' => (object) HeroCatalog::published(),
        ]);
    }
}
