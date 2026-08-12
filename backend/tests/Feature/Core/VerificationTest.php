<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Modules\Core\Notifications\WelcomeNotification;
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

    /**
     * L'e-mail de bienvenue part À L'ACTIVATION, pas à l'inscription : deux
     * e-mails simultanés à la seconde zéro noieraient le code de vérification,
     * qui est le seul message utile à cet instant.
     */
    public function test_l_email_de_bienvenue_part_a_l_activation_et_pas_avant(): void
    {
        [$token, $user, $code] = $this->inscrireEtCapturerCode();

        // À l'inscription : seulement le code, aucun message d'accueil.
        Notification::assertNotSentTo($user, WelcomeNotification::class);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => $code])
            ->assertOk();

        // Après vérification : l'accueil part, adapté au profil choisi.
        Notification::assertSentTo(
            $user,
            WelcomeNotification::class,
            fn (WelcomeNotification $n) => $n->profileType === ProfileType::CLIENT,
        );
    }

    /**
     * Garde-fou : rejouer la vérification (double clic, page rechargée) ne doit
     * pas déclencher un second message d'accueil.
     */
    public function test_l_email_de_bienvenue_n_est_pas_renvoye_deux_fois(): void
    {
        [$token, $user, $code] = $this->inscrireEtCapturerCode();

        $headers = ['Authorization' => "Bearer {$token}"];
        $this->withHeaders($headers)->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => $code]);
        $this->withHeaders($headers)->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => $code]);

        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);
    }

    public function test_un_mauvais_code_est_refuse(): void
    {
        [$token] = $this->inscrireEtCapturerCode();

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => 'mauvais-code']);

        $res->assertStatus(422);
    }

    /**
     * Revue de sécurité (2026-08) : seul le throttle IP de la route (10/min)
     * bornait le nombre d'essais sur un code — insuffisant contre un
     * attaquant distribuant ses tentatives sur plusieurs IP. Au-delà de
     * `VerificationService::MAX_ATTEMPTS` essais ratés, le BON code lui-même
     * ne doit plus être accepté : il faut en redemander un.
     */
    public function test_le_code_est_invalide_apres_trop_d_essais_rates(): void
    {
        [$token, , $code] = $this->inscrireEtCapturerCode();
        $headers = ['Authorization' => "Bearer {$token}"];

        for ($i = 0; $i < \App\Modules\Core\Services\VerificationService::MAX_ATTEMPTS; $i++) {
            $this->withHeaders($headers)
                ->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => '000000'])
                ->assertStatus(422);
        }

        $res = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => $code]);

        $res->assertStatus(422);
    }

    public function test_la_verification_exige_d_etre_authentifie(): void
    {
        $this->postJson('/api/v1/auth/verify', ['channel' => 'email', 'code' => '123456'])
            ->assertStatus(401);
    }
}
