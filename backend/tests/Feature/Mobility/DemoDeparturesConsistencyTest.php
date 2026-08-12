<?php

declare(strict_types=1);

namespace Tests\Feature\Mobility;

use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Models\MobilityService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cohérence des départs de démonstration (F13.6.a).
 *
 * ⚠️ **Ce test protège une DÉMONSTRATION CLIENT, pas une règle métier.** Le
 * défaut qu'il ferme s'est vu en montrant le site pour de vrai : les six trajets
 * publiés par le `DemoSeeder` n'avaient **aucun véhicule rattaché**, si bien que
 * toutes les cartes de l'univers Mobilité s'affichaient en vignette de repli —
 * sur l'univers où l'image décide presque seule du clic (un départ hérite des
 * photos de son véhicule depuis F8.18).
 *
 * Et le rattraper à la main était impossible : la factory tirait des capacités
 * allant jusqu'à 50 places pour un parc qui plafonne à 25, or la validation
 * refuse de vendre plus de places que le véhicule n'en transporte. Le seeder
 * produisait donc des données que le produit lui-même aurait rejetées.
 */
class DemoDeparturesConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_departs_de_demonstration_ont_un_vehicule_qui_peut_les_porter(): void
    {
        $this->seed(DemoSeeder::class);

        $departs = MobilityService::query()
            ->where('status', MobilityServiceStatus::PUBLIE->value)
            ->with('vehicle')
            ->get();

        $this->assertNotEmpty($departs, 'Le seeder de démonstration doit publier des départs.');

        foreach ($departs as $depart) {
            $this->assertNotNull(
                $depart->vehicle,
                "Le départ {$depart->reference} n'a pas de véhicule : sa carte sera sans photo.",
            );

            $this->assertLessThanOrEqual(
                $depart->vehicle->capacity,
                $depart->capacity,
                "Le départ {$depart->reference} vend {$depart->capacity} places pour un véhicule "
                ."de {$depart->vehicle->capacity} : la validation refuserait ce rattachement.",
            );
        }
    }
}
