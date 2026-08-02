<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Support\Notifications\SmsProviderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * Tests B16.1 : canal de notification SMS abstrait + envoi asynchrone.
 */
class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_par_telephone_part_en_sms_via_le_fournisseur(): void
    {
        // Le repli e-mail (actif tant qu'aucun fournisseur SMS réel n'est
        // branché) détournerait ce code vers la boîte mail : on l'écarte, c'est
        // bien le chemin SMS qu'on éprouve ici.
        config()->set('services.sms.verification_via_mail', false);

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
        config()->set('services.sms.verification_via_mail', false);

        $user = User::factory()->create();

        $this->assertSame(['sms'], (new VerificationCodeNotification('1', 'p', 'phone'))->via($user));
        $this->assertSame(['mail'], (new VerificationCodeNotification('1', 'p', 'email'))->via($user));
    }

    /**
     * Repli e-mail : tant qu'aucun fournisseur SMS réel n'est branché, un code
     * destiné au téléphone doit partir par e-mail. Sans cela, le provider `log`
     * se contente d'écrire le SMS dans les journaux et l'utilisateur, qui ne
     * reçoit rien, reste bloqué devant la saisie du code.
     */
    public function test_le_code_du_telephone_bascule_sur_l_email_quand_le_sms_est_factice(): void
    {
        config()->set('services.sms.verification_via_mail', true);

        $user = User::factory()->create(['phone' => '+221771112233']);

        $this->assertSame(['mail'], (new VerificationCodeNotification('1', 'p', 'phone'))->via($user));
        $this->assertSame('mail', VerificationCodeNotification::deliveryFor('phone'));
        $this->assertSame('mail', VerificationCodeNotification::deliveryFor('email'));
    }

    /**
     * Le repli est branché sur le fournisseur configuré : il s'active seul en
     * `log` (SMS factice) et s'efface dès qu'un fournisseur réel est en place —
     * personne n'a à penser à basculer un réglage le jour du branchement.
     */
    public function test_le_repli_suit_le_fournisseur_configure(): void
    {
        // On relit le fichier de configuration, seule source de vérité de la
        // valeur par défaut, en faisant varier le fournisseur.
        // ⚠️ `putenv()` ne suffit pas : le dépôt d'environnement de Laravel
        // interroge d'abord $_SERVER / $_ENV, qui masqueraient notre valeur.
        $original = Env::getRepository()->get('SMS_PROVIDER');

        $verificationViaMail = function (string $provider): bool {
            Env::getRepository()->set('SMS_PROVIDER', $provider);
            $config = require config_path('services.php');

            return $config['sms']['verification_via_mail'];
        };

        // SMS factice → repli actif : sinon l'utilisateur n'aurait rien reçu.
        $this->assertTrue($verificationViaMail('log'));

        // Fournisseur réel → repli levé, sans intervention manuelle.
        $this->assertFalse($verificationViaMail('twilio'));
        $this->assertFalse($verificationViaMail('orange'));

        // On rend l'environnement tel qu'on l'a trouvé : les tests suivants
        // partagent le même processus.
        if ($original === null) {
            Env::getRepository()->clear('SMS_PROVIDER');
        } else {
            Env::getRepository()->set('SMS_PROVIDER', $original);
        }
    }

    public function test_la_notification_est_asynchrone(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new VerificationCodeNotification('1', 'p', 'email'),
        );
    }
}
