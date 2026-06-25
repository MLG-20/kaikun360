<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du parcours d'authentification (phase B1.3) :
 * inscription, validation, connexion (e-mail ET téléphone), déconnexion.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Les rôles doivent exister pour pouvoir être attribués à l'inscription.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Données d'inscription valides réutilisables. */
    private function donneesInscription(array $surcharge = []): array
    {
        return array_merge([
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'phone' => '+221770000000',
            'city' => 'Dakar',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'profile_type' => 'client',
        ], $surcharge);
    }

    public function test_inscription_cree_user_profil_role_et_renvoie_un_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->donneesInscription());

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'awa@example.com')
            ->assertJsonPath('data.user.roles.0', 'client')
            ->assertJsonPath('data.user.profile.type', 'client')
            ->assertJsonStructure(['data' => ['user' => ['id', 'roles', 'profile'], 'token']]);

        // Vérifications côté base.
        $this->assertDatabaseHas('users', ['email' => 'awa@example.com']);
        $this->assertDatabaseHas('profiles', ['type' => 'client']);

        $user = User::where('email', 'awa@example.com')->first();
        $this->assertTrue($user->hasRole('client'));
        // Le compte démarre en attente de vérification.
        $this->assertSame(UserStatus::EN_ATTENTE_VERIFICATION, $user->status);
    }

    public function test_inscription_rejette_les_donnees_invalides(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'pas-un-email',
            'password' => '123',
            'profile_type' => 'inexistant',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'profile_type']);
    }

    public function test_inscription_refuse_un_email_deja_utilise(): void
    {
        $this->postJson('/api/v1/auth/register', $this->donneesInscription());

        $response = $this->postJson('/api/v1/auth/register', $this->donneesInscription([
            'phone' => '+221771111111', // téléphone différent pour isoler le conflit d'email
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_connexion_par_email(): void
    {
        $this->postJson('/api/v1/auth/register', $this->donneesInscription());

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'awa@example.com',
            'password' => 'motdepasse123',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_connexion_par_telephone(): void
    {
        $this->postJson('/api/v1/auth/register', $this->donneesInscription());

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '+221770000000',
            'password' => 'motdepasse123',
        ]);

        $response->assertOk()->assertJsonPath('data.user.phone', '+221770000000');
    }

    public function test_connexion_echoue_avec_mauvais_mot_de_passe(): void
    {
        $this->postJson('/api/v1/auth/register', $this->donneesInscription());

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'awa@example.com',
            'password' => 'mauvais',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['login']);
    }

    public function test_deconnexion_revoque_le_token_courant(): void
    {
        // L'inscription émet déjà un token : on l'utilise directement.
        $register = $this->postJson('/api/v1/auth/register', $this->donneesInscription());
        $token = $register->json('data.token');

        // Le token est bien présent avant déconnexion.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        // Le token courant a été révoqué.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
