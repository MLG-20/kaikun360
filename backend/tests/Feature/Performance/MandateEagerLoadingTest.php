<?php

namespace Tests\Feature\Performance;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Models\ManagementMandate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B17.3 — Chasse aux N+1.
 *
 * MandateResource embarque PropertyResource, qui accède aux relations
 * region/department/commune/owner du bien. Sans eager loading imbriqué, lister
 * N mandats déclencherait 4×N requêtes supplémentaires. On vérifie que le nombre
 * de requêtes reste borné et INDÉPENDANT du nombre de mandats listés.
 */
class MandateEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_des_mandats_charge_les_biens_sans_n_plus_un(): void
    {
        $owner = User::factory()->create();

        // 5 mandats, chacun sur un bien publié rattaché au référentiel géographique.
        Property::factory()->count(5)->published()->create(['owner_id' => $owner->id])
            ->each(fn (Property $p) => ManagementMandate::factory()->create([
                'property_id' => $p->id,
                'owner_id' => $owner->id,
            ]));

        Sanctum::actingAs($owner);

        DB::enableQueryLog();
        $this->getJson('/api/v1/manage/mandates/mine')
            ->assertOk()
            ->assertJsonCount(5, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Avec l'eager loading imbriqué (property.region/department/commune/owner),
        // le nombre de requêtes ne dépend pas du nombre de mandats. Sans lui, on
        // aurait ~4×5 requêtes de plus. Seuil large mais bien en dessous du N+1.
        $this->assertLessThanOrEqual(
            12,
            $queries,
            "Trop de requêtes ({$queries}) : un N+1 s'est probablement réintroduit sur la liste des mandats."
        );
    }
}
