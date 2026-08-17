<?php

namespace Tests\Feature\Transversal;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fermeture d'accès avant ouverture officielle (2026-08-14) : tant que
 * `platform.gate_enabled` est actif, seuls le super_admin, les comptes portant
 * `acces:plateforme` et le back-office restent joignables.
 */
class PlatformGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::CLIENT->value);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SUPER_ADMIN->value);

        return $user;
    }

    private function agent(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::AGENT_KAIKUN->value);

        return $user;
    }

    public function test_reglage_eteint_laisse_tout_ouvert(): void
    {
        // `platform.gate_enabled` vaut false par défaut : rien à activer.
        $this->getJson('/api/v1/properties')->assertOk();
    }

    public function test_visiteur_anonyme_bloque_quand_le_reglage_est_actif(): void
    {
        Settings::set('platform.gate_enabled', true);

        $this->getJson('/api/v1/properties')->assertStatus(423);
    }

    public function test_les_chemins_de_la_liste_blanche_restent_ouverts(): void
    {
        Settings::set('platform.gate_enabled', true);

        $this->getJson('/api/v1/faqs')->assertOk();
        $this->getJson('/api/v1/contact-info')->assertOk();
        $this->getJson('/api/v1/platform-status')->assertOk();
        // F15 — l'équipe doit pouvoir communiquer (actualités) avant l'ouverture.
        $this->getJson('/api/v1/news')->assertOk();
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'client', 'details' => ['univers' => 'immobilier'],
        ])->assertCreated();
        $this->postJson('/api/v1/auth/login', ['email' => 'inexistant@example.com', 'password' => 'x'])
            ->assertStatus(422); // atteint la validation, donc pas bloqué par le gate (423).
    }

    public function test_compte_client_sans_permission_reste_bloque(): void
    {
        Settings::set('platform.gate_enabled', true);
        Sanctum::actingAs($this->client());

        $this->getJson('/api/v1/users/me')->assertStatus(423);
    }

    public function test_compte_avec_acces_plateforme_passe(): void
    {
        Settings::set('platform.gate_enabled', true);
        $user = $this->client();
        $user->givePermissionTo('acces:plateforme');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me')->assertOk();
    }

    public function test_super_admin_passe_sans_permission_explicite(): void
    {
        Settings::set('platform.gate_enabled', true);
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/v1/users/me')->assertOk();
    }

    public function test_agent_back_office_passe_quel_que_soit_le_reglage(): void
    {
        Settings::set('platform.gate_enabled', true);
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/dashboard')->assertOk();
    }

    public function test_platform_status_dit_si_la_plateforme_est_fermee(): void
    {
        $this->getJson('/api/v1/platform-status')
            ->assertOk()
            ->assertJsonPath('data.gate_enabled', false)
            ->assertJsonPath('data.bypass', true);

        Settings::set('platform.gate_enabled', true);

        $this->getJson('/api/v1/platform-status')
            ->assertOk()
            ->assertJsonPath('data.gate_enabled', true)
            ->assertJsonPath('data.bypass', false);

        $user = $this->client();
        $user->givePermissionTo('acces:plateforme');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/platform-status')
            ->assertOk()
            ->assertJsonPath('data.bypass', true);
    }
}
