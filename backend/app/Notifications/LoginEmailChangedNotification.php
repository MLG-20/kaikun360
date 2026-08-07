<?php

namespace App\Notifications;

use App\Support\Mail\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'ANCIENNE adresse qu'elle ne sert plus à se connecter (F8.22).
 *
 * POURQUOI ELLE EXISTE
 * --------------------
 * Changer l'adresse de connexion, c'est déplacer la serrure : toute la
 * récupération de compte (« mot de passe oublié ») part désormais ailleurs.
 * Exiger le mot de passe actuel empêche l'inconnu de le faire ; **cet e-mail
 * permet de s'en apercevoir** si le mot de passe a fuité. Sans lui, une prise de
 * contrôle serait parfaitement silencieuse pour le titulaire légitime.
 *
 * ⚠️ **Elle part à l'ANCIENNE adresse, jamais à la nouvelle.** L'alerte n'a de
 * valeur que pour celui qui **perd** l'accès : prévenir la nouvelle adresse
 * reviendrait à informer l'attaquant de sa propre réussite.
 *
 * ⚠️ **Aucun réglage du back-office ne l'éteint** (contrairement aux alertes
 * internes de F7.2.l) : c'est un e-mail de sécurité, pas une notification de
 * confort. Un canal qu'on peut couper est un canal sur lequel on ne peut pas
 * compter le jour où il faut.
 */
class LoginEmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $ancienneAdresse,
        public string $nouvelleAdresse,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make()
            ->subject('Votre adresse de connexion Kaikun 360 a été modifiée')
            ->preheader('Si vous n\'êtes pas à l\'origine de ce changement, réagissez maintenant.')
            ->eyebrow('Sécurité du compte')
            ->heading('Votre adresse de connexion a changé.')
            ->intro(
                'L\'adresse utilisée pour vous connecter à Kaikun 360 vient d\'être remplacée. '
                .'Ce message part à l\'ancienne adresse, pour que vous en soyez informé.'
            )
            ->facts([
                'Ancienne adresse' => $this->ancienneAdresse,
                'Nouvelle adresse' => $this->nouvelleAdresse,
                'Modifié le' => BrandedMail::date(now()),
            ])
            // ⚠️ On ne propose PAS de bouton « annuler » : il n'existe pas, et
            // un lien qui promet ce qu'il ne fait pas est pire que rien. La
            // consigne est humaine et immédiate.
            ->note(
                'Si vous êtes à l\'origine de ce changement, il n\'y a rien à faire. '
                .'Sinon, votre mot de passe est probablement connu d\'un tiers : '
                .'contactez immédiatement l\'équipe Kaikun 360 pour faire reprendre la main sur le compte.'
            )
            ->reason('Vous recevez cet e-mail parce que cette adresse était celle de votre compte Kaikun 360.')
            ->toMailMessage();
    }
}
