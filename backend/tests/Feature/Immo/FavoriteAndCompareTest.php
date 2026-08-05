<?php

namespace Tests\Feature\Immo;

use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la comparaison de biens (phase B2.5).
 *
 * NB : les favoris, autrefois testés ici, sont devenus POLYMORPHES (tous univers)
 * et sont désormais couverts par `Tests\Feature\Transversal\FavoriteTest`.
 */
class FavoriteAndCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_la_comparaison_ne_renvoie_que_les_biens_publies(): void
    {
        $p1 = Property::factory()->published()->create();
        $p2 = Property::factory()->published()->create();
        $p3 = Property::factory()->pending()->create();

        $this->getJson("/api/v1/properties/compare?ids={$p1->id},{$p2->id},{$p3->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // le bien non publié est exclu
    }

    /**
     * Le plafond de 4 et l'indulgence aux ids inconnus sont devenus un CONTRAT
     * avec l'écran de comparaison (F8.15.e) : celui-ci reproduit le plafond pour
     * pouvoir refuser le cinquième bien AVEC un message, et compare la réponse à
     * sa demande pour signaler les biens disparus. Les abaisser ici, ou les
     * transformer en erreur, casserait cet écran sans qu'aucun autre test ne
     * s'en aperçoive.
     */
    public function test_la_comparaison_tronque_a_quatre_biens_et_ignore_les_ids_inconnus(): void
    {
        $ids = Property::factory()->published()->count(6)->create()->pluck('id');

        $this->getJson('/api/v1/properties/compare?ids='.$ids->implode(','))
            ->assertOk()
            ->assertJsonCount(4, 'data');

        // Un identifiant qui n'existe pas est ignoré, pas rejeté : la sélection
        // vit dans le navigateur du visiteur et peut citer un bien supprimé.
        $vivant = $ids->first();

        $this->getJson("/api/v1/properties/compare?ids={$vivant},999999")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vivant);
    }

    public function test_la_comparaison_sans_identifiant_repond_une_liste_vide(): void
    {
        // L'écran n'appelle pas le serveur quand la sélection est vide, mais
        // l'URL est publique : elle ne doit pas casser pour autant.
        $this->getJson('/api/v1/properties/compare')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
