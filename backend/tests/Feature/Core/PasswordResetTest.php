<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests de la récupération de compte — mot de passe oublié (phase B1.4).
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function inscrire(): User
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'phone' => '+221770000000',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'profile_type' => 'client',
        ]);

        return User::where('email', 'awa@example.com')->first();
    }

    public function test_le_cycle_oubli_puis_reset_fonctionne(): void
    {
        Notification::fake();
        $user = $this->inscrire();

        // Demande de réinitialisation.
        $this->postJson('/api/v1/auth/password/forgot', ['login' => 'awa@example.com'])
            ->assertOk();

        // Capture le code de réinitialisation (purpose password_reset).
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            if ($notification->purpose === 'password_reset') {
                $code = $notification->code;
            }
            return true;
        });
        $this->assertNotNull($code);

        // Réinitialisation effective.
        $this->postJson('/api/v1/auth/password/reset', [
            'login' => 'awa@example.com',
            'code' => $code,
            'password' => 'nouveaupass123',
            'password_confirmation' => 'nouveaupass123',
        ])->assertOk();

        // Le nouveau mot de passe fonctionne...
        $this->postJson('/api/v1/auth/login', [
            'login' => 'awa@example.com',
            'password' => 'nouveaupass123',
        ])->assertOk();

        // ...et l'ancien ne fonctionne plus.
        $this->postJson('/api/v1/auth/login', [
            'login' => 'awa@example.com',
            'password' => 'motdepasse123',
        ])->assertStatus(422);
    }

    public function test_forgot_repond_pareil_pour_un_compte_inconnu(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['login' => 'inconnu@example.com'])
            ->assertOk();

        // Aucun code envoyé puisque le compte n'existe pas (mais la réponse est identique).
        Notification::assertNothingSent();
    }

    public function test_reset_refuse_un_code_invalide(): void
    {
        Notification::fake();
        $this->inscrire();
        $this->postJson('/api/v1/auth/password/forgot', ['login' => 'awa@example.com']);

        $this->postJson('/api/v1/auth/password/reset', [
            'login' => 'awa@example.com',
            'code' => 'mauvais-code',
            'password' => 'nouveaupass123',
            'password_confirmation' => 'nouveaupass123',
        ])->assertStatus(422);
    }
}
