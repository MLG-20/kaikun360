<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\SettingsRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F16.2 : bande défilante des univers, juste sous le héros de
 * l'accueil. Réutilise le catalogue d'univers de `HeroCatalog` — la seule
 * chose propre à cette tranche est le masquage (`home.universe_strip_hidden`),
 * c'est donc lui qu'on protège.
 */
class UniverseStripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_les_dix_univers_defilent_tant_que_rien_n_est_masque(): void
    {
        $response = $this->getJson('/api/v1/universe-strip')->assertOk();

        $noms = $response->json('data.names');
        $this->assertCount(10, $noms);
        $this->assertContains('Immobilier', $noms);
        $this->assertContains('Espace prestataires', $noms);
    }

    public function test_masquer_un_univers_le_retire_de_la_bande(): void
    {
        app(SettingsRepository::class)->set('home.universe_strip_hidden', ['team-building']);

        $noms = $this->getJson('/api/v1/universe-strip')->json('data.names');

        $this->assertNotContains('Team building', $noms);
        $this->assertCount(9, $noms);
    }

    public function test_une_cle_d_univers_inconnue_est_refusee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);
        Sanctum::actingAs($admin);

        // Une clé mal orthographiée ne masquerait rien en silence — l'équipe
        // croirait avoir retiré un univers qui continue de défiler.
        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['home.universe_strip_hidden' => ['immobiliers']],
        ])->assertStatus(422);
    }
}
