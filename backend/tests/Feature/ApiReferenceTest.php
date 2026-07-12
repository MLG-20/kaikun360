<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * B17.5 — Documentation des endpoints.
 *
 * Garde-fou anti-dérive : toute route réellement enregistrée sous `/api/v1` doit
 * être documentée dans `API.md`. Si une route est ajoutée sans mettre à jour la
 * référence, ce test échoue et pointe la ou les URI manquantes.
 */
class ApiReferenceTest extends TestCase
{
    public function test_toutes_les_routes_api_sont_documentees_dans_api_md(): void
    {
        $doc = file_get_contents(base_path('API.md'));
        $this->assertNotEmpty($doc, 'API.md est introuvable ou vide.');

        $manquantes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            // On documente les URI sans le préfixe /api/v1 (ex. « /auth/login »).
            $documented = substr($uri, strlen('api/v1'));

            if (! str_contains($doc, '`'.$documented.'`')) {
                $manquantes[] = $documented;
            }
        }

        $manquantes = array_unique($manquantes);

        $this->assertSame(
            [],
            array_values($manquantes),
            "Routes /api/v1 absentes de API.md : \n- ".implode("\n- ", $manquantes)
        );
    }
}
