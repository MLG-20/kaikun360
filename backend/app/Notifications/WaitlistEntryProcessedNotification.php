<?php

namespace App\Notifications;

use App\Enums\WaitlistCategory;
use App\Models\WaitlistEntry;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invite le PROSPECT à rejoindre la plateforme, une fois son inscription à la
 * liste d'attente marquée « traitée » par un agent (2026-08-14).
 *
 * ⚠️ **Le destinataire n'a pas de compte.** Contrairement à toutes les autres
 * notifications d'exploitation (envoyées à un `User`), celle-ci part vers
 * l'adresse brute laissée dans `WaitlistEntry::email` via un routage anonyme
 * (`Notification::route('mail', $email)`) — voir `AdminWaitlistController::update()`.
 * D'où l'absence de `forRecipient()` : il n'existe aucun espace privé, aucune
 * préférence de notification, à résoudre pour ce destinataire.
 *
 * ⚠️ **N'est envoyée que si une adresse e-mail a été laissée** (`email` est
 * facultatif sur le formulaire de la liste d'attente, seul `phone` est requis) —
 * le contrôleur ne construit cette notification que dans ce cas.
 *
 * Contenu adapté à la catégorie du prospect, même principe que
 * {@see \App\Modules\Core\Notifications\WelcomeNotification} : un propriétaire,
 * un prestataire et un client de passage ne viennent pas chercher la même chose.
 */
class WaitlistEntryProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WaitlistEntry $entry) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return NotificationSettings::channels(
            NotificationEvent::WAITLIST_ENTRY_PROCESSED,
            $notifiable,
            ['mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $prenom = $this->firstName();

        $mail = match ($this->entry->category) {
            WaitlistCategory::PROPRIETAIRE => $this->forProprietaire($prenom),
            WaitlistCategory::PRESTATAIRE => $this->forPrestataire($prenom),
            WaitlistCategory::TEAM_BUILDING => $this->forTeamBuilding($prenom),
            WaitlistCategory::DIASPORA => $this->forDiaspora($prenom),
            WaitlistCategory::CLIENT => $this->forClient($prenom),
        };

        return $mail
            ->tone('success')
            ->reason('Vous recevez cet e-mail car vous vous étiez inscrit(e) sur la liste d\'attente de Kaikun 360.')
            ->toMailMessage();
    }

    /**
     * -------------------------------------------------------------- CLIENT
     */
    private function forClient(string $prenom): BrandedMail
    {
        return BrandedMail::make()
            ->subject('La porte est ouverte')
            ->preheader('Votre patience est récompensée : vous pouvez créer votre compte dès maintenant.')
            ->eyebrow('Liste d\'attente')
            ->heading("{$prenom}, c'est à vous.")
            ->intro(
                'Vous vous étiez inscrit·e avant l\'heure, et nous ne vous avons pas oublié·e. Kaikun 360 réunit désormais en un seul endroit ce qui demandait jusqu\'ici une dizaine d\'interlocuteurs : trouver un logement, réserver un séjour, faire construire, louer un véhicule.',
                'Votre place vous attend — il ne reste plus qu\'à créer votre compte.',
            )
            ->action('Créer mon compte', '/auth/inscription')
            ->secondaryAction('Découvrir la plateforme', '/')
            ->steps([
                'Créez votre compte : moins d\'une minute, avec votre e-mail ou votre compte Google.',
                'Complétez votre profil pour recevoir des offres réellement pertinentes.',
                'Explorez le catalogue : chaque bien, véhicule et prestataire est vérifié par notre équipe avant publication.',
            ], 'Vos trois prochaines minutes')
            ->outro('Merci d\'avoir patienté. Nous avons pris ce temps pour que votre premier passage sur la plateforme soit le bon.');
    }

    /**
     * ----------------------------------------------------------- DIASPORA
     */
    private function forDiaspora(string $prenom): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Le pays vous attend, nous aussi')
            ->preheader('Votre inscription est traitée : vous pouvez créer votre compte dès maintenant.')
            ->eyebrow('Liste d\'attente')
            ->heading("{$prenom}, votre place est prête.")
            ->intro(
                'Investir au pays depuis l\'étranger repose trop souvent sur la parole de quelqu\'un — un cousin, un intermédiaire, une photo envoyée par messagerie. Nous avons construit Kaikun 360 pour remplacer cette parole par des preuves : visites filmées et datées, documents vérifiés, suivi de chantier.',
                'Vous vous étiez inscrit·e avant l\'ouverture. Votre compte peut maintenant être créé.',
            )
            ->action('Créer mon compte', '/auth/inscription')
            ->secondaryAction('Découvrir la plateforme', '/')
            ->steps([
                'Créez votre compte, où que vous soyez et quel que soit le fuseau horaire.',
                'Décrivez votre projet : achat, construction, gestion locative ou suivi d\'un bien existant.',
                'Un agent Kaikun devient votre interlocuteur unique jusqu\'à la fin du projet.',
            ], 'Vos trois prochaines minutes')
            ->outro('Nous savons ce que représente un investissement fait depuis l\'étranger. Nous le traitons avec ce sérieux-là, dès votre premier clic.');
    }

    /**
     * -------------------------------------------------------- PRESTATAIRE
     */
    private function forPrestataire(string $prenom): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Votre place dans le réseau Kaikun Pro')
            ->preheader('Votre inscription est traitée : vous pouvez créer votre compte prestataire dès maintenant.')
            ->eyebrow('Kaikun Pro')
            ->heading("{$prenom}, le réseau vous attend.")
            ->intro(
                'Vous vous étiez inscrit·e avant l\'ouverture pour rejoindre un réseau de professionnels sélectionnés — bâtiment, transport, hébergement, restauration, services. Nos clients ne cherchent pas le prestataire le moins cher : ils cherchent celui dont le travail est garanti.',
                'Votre place est prête. Il ne reste plus qu\'à créer votre compte.',
            )
            ->action('Créer mon compte', '/auth/inscription')
            ->secondaryAction('Découvrir la plateforme', '/')
            ->steps([
                'Créez votre compte prestataire.',
                'Déposez vos pièces (registre de commerce, NINEA, attestation d\'assurance) : c\'est ce qui déclenche la vérification.',
                'Une fois votre dossier vérifié, votre profil devient visible et les missions peuvent commencer à arriver.',
            ], 'Vos trois prochaines étapes')
            ->outro('Le sceau Kaikun Pro se mérite — et c\'est précisément ce qui en fait la valeur, pour vous comme pour vos futurs clients.');
    }

    /**
     * ----------------------------------------------------------- TEAM BUILDING
     */
    private function forTeamBuilding(string $prenom): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Votre compte entreprise peut être créé')
            ->preheader('Votre inscription est traitée : créez votre compte entreprise dès maintenant.')
            ->eyebrow('Team building')
            ->heading("{$prenom}, à vous de jouer.")
            ->intro(
                'Organiser un séminaire ou une sortie d\'équipe est un sujet où le temps perdu en coordination coûte souvent plus cher que la prestation elle-même. Kaikun 360 vous donne un point d\'entrée unique : hébergement, restauration, transport et animation assemblés dans un seul devis.',
                'Votre inscription est traitée. Votre compte entreprise peut maintenant être créé.',
            )
            ->action('Créer mon compte', '/auth/inscription')
            ->secondaryAction('Découvrir la plateforme', '/')
            ->steps([
                'Créez votre compte entreprise.',
                'Décrivez votre besoin : dates, nombre de participants, ville, budget indicatif.',
                'Un agent Kaikun dédié vous adresse un devis chiffré, modifiable jusqu\'à votre validation.',
            ], 'Vos trois prochaines étapes')
            ->outro('Nous avons hâte de préparer votre prochaine sortie d\'équipe.');
    }

    /**
     * ------------------------------------------------------- PROPRIÉTAIRE
     */
    private function forProprietaire(string $prenom): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Votre espace propriétaire peut être ouvert')
            ->preheader('Votre inscription est traitée : créez votre compte dès maintenant.')
            ->eyebrow('Espace propriétaire')
            ->heading("{$prenom}, votre bien peut trouver preneur.")
            ->intro(
                'Un bien qui dort coûte de l\'argent ; un bien mal géré en coûte davantage. Kaikun 360 vous donne les deux bouts de la chaîne : la visibilité auprès de locataires et d\'acheteurs vérifiés, et la gestion quotidienne si vous ne souhaitez pas vous en charger.',
                'Votre inscription est traitée. Vous pouvez créer votre compte dès maintenant et déposer votre premier bien.',
            )
            ->action('Créer mon compte', '/auth/inscription')
            ->secondaryAction('Découvrir la plateforme', '/')
            ->steps([
                'Créez votre compte propriétaire.',
                'Déposez vos pièces (identité, titre de propriété) : c\'est ce qui déclenche la vérification.',
                'Créez votre première fiche — photos, localisation, conditions — puis notre équipe la contrôle et la publie.',
            ], 'Vos trois prochaines étapes')
            ->note('Un conseil qui change tout : des photos nettes et lumineuses, et une description honnête. Les annonces les plus transparentes sont, de loin, celles qui se concrétisent le plus vite.');
    }

    /**
     * Prénom du prospect, pour une adresse personnelle plutôt qu'un « Bonjour, »
     * anonyme. On retient le premier mot du nom laissé sur le formulaire.
     */
    private function firstName(): string
    {
        $name = trim($this->entry->name);

        if ($name === '') {
            return 'Bonjour';
        }

        return explode(' ', $name)[0];
    }
}
