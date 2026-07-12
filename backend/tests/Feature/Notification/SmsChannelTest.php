<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Support\Notifications\SmsProviderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests B16.1 : canal de notification SMS abstrait + envoi asynchrone.
 */
class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_par_telephone_part_en_sms_via_le_fournisseur(): void
    {
        // Fournisseur SMS espion injecté dans le conteneur.
        $spy = new class implements SmsProviderInterface
        {
            /** @var array<int, array{to: string, message: string}> */
            public array $sent = [];

            public function send(string $to, string $message): bool
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return true;
            }
        };
        $this->app->instance(SmsProviderInterface::class, $spy);

        $user = User::factory()->create(['phone' => '+221771112233']);
        $user->notify(new VerificationCodeNotification('123456', 'account_verification', 'phone'));

        $this->assertCount(1, $spy->sent);
        $this->assertSame('+221771112233', $spy->sent[0]['to']);
        $this->assertStringContainsString('123456', $spy->sent[0]['message']);
    }

    public function test_le_canal_depend_de_la_cible(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['sms'], (new VerificationCodeNotification('1', 'p', 'phone'))->via($user));
        $this->assertSame(['mail'], (new VerificationCodeNotification('1', 'p', 'email'))->via($user));
    }

    public function test_la_notification_est_asynchrone(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new VerificationCodeNotification('1', 'p', 'email'),
        );
    }
}
