<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * B19 — Connexion Google.
 *
 * On vérifie via `Http::fake` (sans identifiants Google réels) : création d'un
 * compte client au 1er login, liaison d'un compte e-mail existant, et les
 * contrôles de sécurité (jeton invalide, audience incorrecte → 401).
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'kaikun-web.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('services.google.client_id', self::CLIENT_ID);
    }

    private function fakeGoogle(array $overrides = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(array_merge([
                'aud' => self::CLIENT_ID,
                'sub' => 'google-sub-123',
                'email' => 'awa@example.com',
                'email_verified' => 'true',
                'name' => 'Awa Diop',
            ], $overrides)),
        ]);
    }

    public function test_un_nouveau_compte_google_est_cree_comme_client_actif(): void
    {
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['id', 'email', 'roles'], 'token']])
            ->assertJsonPath('data.user.email', 'awa@example.com');

        $user = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertSame('actif', $user->status->value);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole(UserRole::CLIENT->value));
        $this->assertSame('client', $user->profile->type->value);
    }

    public function test_un_compte_email_existant_est_lie_a_google_sans_doublon(): void
    {
        $existing = User::factory()->create(['email' => 'awa@example.com', 'google_id' => null]);

        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $existing->id);

        $this->assertSame(1, User::where('email', 'awa@example.com')->count());
        $this->assertSame('google-sub-123', $existing->fresh()->google_id);
    }

    public function test_un_jeton_google_invalide_est_refuse(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'bad'])
            ->assertStatus(401);

        $this->assertSame(0, User::count());
    }

    public function test_un_jeton_pour_une_autre_audience_est_refuse(): void
    {
        // Sécurité : un ID token émis pour une AUTRE application ne doit pas passer.
        $this->fakeGoogle(['aud' => 'une-autre-app.apps.googleusercontent.com']);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])
            ->assertStatus(401);

        $this->assertSame(0, User::count());
    }
}
