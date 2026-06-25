<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests de la vérification de compte (phase B1.4).
 */
class VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Inscrit un utilisateur et récupère [token, user, code e-mail capturé].
     *
     * @return array{0: string, 1: User, 2: string|null}
     */
    private function inscrireEtCapturerCode(): array
    {
        Notification::fake();

        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'phone' => '+221770000000',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'profile_type' => 'client',
        ]);

        $token = $res->json('data.token');
        $user = User::where('email', 'awa@example.com')->first();

        // Capture le code envoyé par la notification de vérification e-mail.
        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function ($notification) use (&$code) {
            if ($notification->purpose === 'account_verification' && $notification->channel === 'email') {
                $code = $notification->code;
            }
            return true;
        });

        return [$token, $user, $code];
    }

    public function test_un_code_est_envoye_a_l_inscription(): void
    {
        [, , $code] = $this->inscrireEtCapturerCode();

        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_la_verification_active_le_compte(): void
    {
        [$token, $user, $code] = $this->inscrireEtCapturerCode();

        // Avant vérification : compte en attente.
        $this->assertSame(UserStatus::EN_ATTENTE_VERIFICATION, $user->status);

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => $code]);

        $res->assertOk()->assertJsonPath('data.user.status', 'actif');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(UserStatus::ACTIF, $user->status);
    }

    public function test_un_mauvais_code_est_refuse(): void
    {
        [$token] = $this->inscrireEtCapturerCode();

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => 'mauvais-code']);

        $res->assertStatus(422);
    }

    public function test_la_verification_exige_d_etre_authentifie(): void
    {
        $this->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => '123456'])
            ->assertStatus(401);
    }
}
