<?php

namespace Tests\Feature\Performance;

use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B17.2 — Mise en cache Redis des catalogues + invalidation automatique.
 *
 * On prouve deux choses :
 *   1. le résultat d'un catalogue est bien servi depuis le cache (une écriture
 *      qui NE déclenche PAS les événements Eloquent — `withoutEvents` — n'apparaît
 *      pas tant que le cache n'est pas invalidé) ;
 *   2. toute écriture normale sur le modèle invalide le cache, y compris la
 *      propagation bien immobilier → catalogue des nuitées.
 */
class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_catalogue_des_biens_est_servi_du_cache_puis_invalide(): void
    {
        Property::factory()->published()->create();

        // Premier appel : peuple le cache (1 bien publié).
        $this->getJson('/api/v1/properties')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Écriture SANS événement Eloquent : le cache n'est pas invalidé.
        Model::withoutEvents(fn () => Property::factory()->published()->create());

        // Toujours servi depuis le cache : le bien caché n'apparaît pas.
        $this->getJson('/api/v1/properties')
            ->assertJsonCount(1, 'data');

        // Une écriture normale invalide le catalogue → tous les biens publiés
        // réapparaissent (l'original, le bien « caché » et le nouveau).
        Property::factory()->published()->create();

        $this->getJson('/api/v1/properties')
            ->assertJsonCount(3, 'data');
    }

    public function test_une_modification_de_bien_invalide_le_catalogue_des_nuitees(): void
    {
        $property = Property::factory()->published()->create();
        Stay::factory()->create(['property_id' => $property->id]);

        // Peuple le cache des nuitées (1 nuitée réservable).
        $this->getJson('/api/v1/stays')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Ajoute une seconde nuitée réservable SANS événement → cache non invalidé.
        Model::withoutEvents(function () {
            $hidden = Property::factory()->published()->create();
            Stay::factory()->create(['property_id' => $hidden->id]);
        });

        $this->getJson('/api/v1/stays')
            ->assertJsonCount(1, 'data');

        // Modifier le bien initial (écriture Eloquent) invalide AUSSI le catalogue
        // des nuitées, car leur visibilité dépend de la publication du bien.
        $property->update(['title' => 'Titre mis à jour']);

        $this->getJson('/api/v1/stays')
            ->assertJsonCount(2, 'data');
    }
}
