<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Modules\Core\Services\VerificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests F7.1.d : double authentification du back-office.
 *
 * Un compte à fort privilège (admin / super_admin) ne reçoit PAS de jeton à la
 * connexion : il doit valider un code e-mail (2FA) via POST /auth/two-factor,
 * qui délivre alors un jeton à **expiration courte**. Les comptes publics et les
 * agents ne sont pas concernés.
 */
class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function utilisateur(string $role, string $password = 'motdepasse123'): User
    {
        $user = User::factory()->create(['password' => $password]);
        $user->assignRole($role);

        return $user;
    }

    public function test_la_connexion_admin_declenche_un_defi_2fa_sans_jeton(): void
    {
        $admin = $this->utilisateur(UserRole::ADMIN->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'motdepasse123',
        ])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonMissingPath('data.token');

        // Un code de 2FA a bien été émis pour ce compte.
        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $admin->id,
            'purpose' => VerificationService::PURPOSE_TWO_FACTOR,
            'channel' => VerificationService::CHANNEL_EMAIL,
        ]);
    }

    public function test_le_bon_code_delivre_un_jeton_a_expiration_courte(): void
    {
        Notification::fake();
        $admin = $this->utilisateur(UserRole::ADMIN->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'motdepasse123',
        ])->assertOk();

        // Récupère le code en clair depuis la notification interceptée.
        $code = null;
        Notification::assertSentTo($admin, VerificationCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->postJson('/api/v1/auth/two-factor', [
            'login' => $admin->email,
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token', 'expires_at']]);

        // Le jeton back-office porte une expiration (session courte).
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull($admin->tokens()->first()->expires_at);
    }

    public function test_un_mauvais_code_est_refuse(): void
    {
        $admin = $this->utilisateur(UserRole::ADMIN->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'motdepasse123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/two-factor', [
            'login' => $admin->email,
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertCount(0, $admin->tokens()->get());
    }

    public function test_un_client_se_connecte_sans_2fa(): void
    {
        $client = $this->utilisateur(UserRole::CLIENT->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => $client->email,
            'password' => 'motdepasse123',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseMissing('verification_codes', [
            'user_id' => $client->id,
            'purpose' => VerificationService::PURPOSE_TWO_FACTOR,
        ]);
    }

    public function test_un_agent_se_connecte_sans_2fa(): void
    {
        $agent = $this->utilisateur(UserRole::AGENT_KAIKUN->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => $agent->email,
            'password' => 'motdepasse123',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }
}
