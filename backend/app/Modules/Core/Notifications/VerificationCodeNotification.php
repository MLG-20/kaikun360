<?php

namespace App\Modules\Core\Notifications;

use App\Support\Mail\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification d'envoi d'un code (vérification de compte ou reset mot de passe).
 *
 * Multi-canal (B16.1) : SMS quand le code est destiné au TÉLÉPHONE, e-mail
 * sinon. L'envoi est asynchrone (ShouldQueue) pour ne jamais bloquer la requête.
 * En dev, MAIL_MAILER=log et le fournisseur SMS `log` écrivent dans les logs
 * (aucun envoi réel). Le SMS réel (Twilio) s'active via `SMS_PROVIDER=twilio`.
 */
class VerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $purpose,
        public string $channel,
    ) {}

    /**
     * Par quel média un code destiné à `$channel` part-il RÉELLEMENT ?
     *
     * Un code « téléphone » voyage par SMS… sauf quand le repli e-mail est
     * actif (voir `config/services.php`, clé `sms.verification_via_mail`), ce
     * qui est le cas tant qu'aucun fournisseur SMS réel n'est branché.
     *
     * L'API expose cette information au frontend : l'écran de saisie doit
     * annoncer le bon endroit où chercher le code, sous peine d'envoyer
     * l'utilisateur consulter des SMS qui n'arriveront jamais.
     *
     * @return 'sms'|'mail'
     */
    public static function deliveryFor(string $channel): string
    {
        if ($channel !== 'phone') {
            return 'mail';
        }

        return config('services.sms.verification_via_mail') ? 'mail' : 'sms';
    }

    /**
     * Canaux de livraison.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // ⚠️ Notification de SÉCURITÉ : volontairement EXCLUE du pilotage des
        // notifications du back-office (F7.2.l). Couper ce canal condamnerait
        // l'accès (2FA admin) et l'inscription — aucun réglage ne doit pouvoir
        // le faire. Voir App\Support\Notifications\NotificationEvent.
        // Code destiné au téléphone → SMS ; sinon e-mail. Le repli éventuel
        // vers l'e-mail est décidé par deliveryFor().
        return [self::deliveryFor($this->channel)];
    }

    /**
     * Contenu du SMS (canal `sms`, B16.1).
     */
    public function toSms(object $notifiable): string
    {
        return "Kaikun 360 : votre code est {$this->code}. Valable 15 min, ne le partagez pas.";
    }

    /**
     * Contenu de l'e-mail.
     *
     * C'est, pour un nouvel inscrit, le TOUT PREMIER message reçu de nous : il
     * doit être immédiatement identifiable et sans ambiguïté. On va donc droit
     * au but — le code est l'élément dominant de la page — et on rappelle la
     * règle de sécurité qui protège l'utilisateur : personne chez Kaikun ne lui
     * demandera jamais ce code (c'est le scénario d'arnaque le plus courant).
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Chaque finalité a son propre cadre : un code de connexion admin et un
        // code d'inscription n'appellent pas le même niveau de vigilance.
        [$eyebrow, $heading, $intro] = match ($this->purpose) {
            'password_reset' => [
                'Mot de passe',
                'Réinitialisation de votre mot de passe',
                'Vous avez demandé à définir un nouveau mot de passe. Saisissez le code ci-dessous pour poursuivre.',
            ],
            'two_factor' => [
                'Sécurité',
                'Votre code de connexion',
                'Une connexion au back-office Kaikun 360 a été demandée avec votre compte. Saisissez ce code pour la valider.',
            ],
            default => [
                'Vérification',
                'Confirmons votre adresse e-mail',
                'Bienvenue. Pour activer votre compte Kaikun 360, saisissez le code ci-dessous dans la page de vérification.',
            ],
        };

        // Cas du repli : le code visait le TÉLÉPHONE et nous arrive par e-mail.
        // Le message par défaut parlerait de confirmer l'adresse e-mail, ce qui
        // n'a aucun sens ici et sèmerait le doute. On dit donc les choses : ce
        // code confirme un numéro, et il transite par e-mail faute de SMS.
        if ($this->channel === 'phone' && $this->purpose === 'account_verification') {
            $heading = 'Confirmons votre numéro de téléphone';
            $intro = 'Vous avez ajouté ou modifié votre numéro de téléphone sur Kaikun 360. '
                .'Saisissez le code ci-dessous dans la page de vérification pour le confirmer. '
                .'Ce code vous parvient par e-mail, et non par SMS.';
        }

        $mail = BrandedMail::make()
            ->subject('Votre code de vérification')
            // L'aperçu affiche le code : sur mobile, l'utilisateur le lit
            // souvent sans même ouvrir le message.
            ->preheader("Votre code : {$this->code} — valable 15 minutes.")
            ->eyebrow($eyebrow)
            ->tone('brand')
            ->heading($heading)
            ->intro($intro)
            ->code($this->code, 'Ce code expire dans 15 minutes.')
            ->security(
                '<strong>Ne communiquez ce code à personne.</strong> Aucun conseiller Kaikun 360 ne vous le demandera, ni par téléphone, ni par message.'
            )
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail suite à une demande faite sur Kaikun 360.');

        // Si l'utilisateur n'est pas à l'origine de la demande, la consigne
        // n'est pas la même selon l'enjeu.
        $mail->outro($this->purpose === 'account_verification'
            ? 'Vous n\'êtes pas à l\'origine de cette inscription ? Ignorez simplement cet e-mail, aucun compte ne sera activé.'
            : 'Vous n\'êtes pas à l\'origine de cette demande ? Ignorez cet e-mail et prévenez-nous : votre compte pourrait être visé.');

        return $mail->toMailMessage();
    }
}
