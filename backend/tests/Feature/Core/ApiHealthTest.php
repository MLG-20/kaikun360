<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de "santé" du socle API (phase B0.4 / B0.6).
 *
 * Ils valident deux garanties fondamentales :
 *  1. l'enveloppe JSON de succès standard ({ "data": ... }) ;
 *  2. le format d'erreur standard (404 propre).
 *
 * RefreshDatabase migre une base de test FRAÎCHE avant chaque test :
 * c'est aussi une vérification que TOUTES les migrations s'exécutent sans erreur.
 */
class ApiHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_version_repond_avec_enveloppe_standard(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['name', 'api', 'status']])
            ->assertJsonPath('data.api', 'v1')
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_route_inconnue_renvoie_404_au_format_standard(): void
    {
        $response = $this->getJson('/api/v1/route-inexistante');

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Ressource introuvable.']);
    }
}
