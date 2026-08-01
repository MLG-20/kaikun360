<?php

namespace App\Modules\Core\Notifications;

use App\Modules\Core\Enums\ProfileType;
use App\Support\Mail\BrandedMail;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-MAIL DE BIENVENUE — le premier vrai message reçu par un nouvel inscrit.
 *
 * QUAND ? Une seule fois, au moment où le compte devient ACTIF :
 *   · inscription classique → juste après la saisie du code de vérification ;
 *   · inscription Google    → immédiatement (l'e-mail est déjà vérifié).
 * Il ne part donc JAMAIS en même temps que le code de vérification : deux
 * e-mails simultanés à la seconde zéro noient le message utile (le code) et
 * font mauvaise impression.
 *
 * POURQUOI CE SOIN ? La confiance est le point de friction n°1 du secteur visé
 * (immobilier et construction à distance, diaspora en tête). Un accueil précis,
 * qui annonce les étapes suivantes et rappelle nos garanties vérifiables, fait
 * davantage pour la crédibilité de la plateforme que n'importe quel argumentaire
 * commercial.
 *
 * CONTENU ADAPTÉ AU PROFIL : un propriétaire, un prestataire, une entreprise et
 * un client ne viennent pas chercher la même chose. Chacun reçoit donc ses
 * propres promesses, ses propres étapes et son propre bouton d'action — même
 * habillage, message différent.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProfileType $profileType) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Pas de SMS : le message est long et le SMS coûte cher — on réserve ce
        // canal aux informations urgentes et courtes (code, confirmation).
        return NotificationSettings::channels(
            NotificationEvent::ACCOUNT_WELCOME,
            $notifiable,
            ['mail', 'database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = $this->firstName($notifiable);

        // Chaque profil définit son propre message ; le reste (habillage,
        // bandeau de confiance, pied de page) est commun.
        $mail = match ($this->profileType) {
            ProfileType::PROPRIETAIRE => $this->forProprietaire($firstName),
            ProfileType::PRESTATAIRE => $this->forPrestataire($firstName),
            ProfileType::ENTREPRISE => $this->forEntreprise($firstName),
            ProfileType::DIASPORA => $this->forDiaspora($firstName),
            ProfileType::CLIENT => $this->forClient($firstName),
        };

        return $mail
            // Le bandeau « protocole de confiance » n'apparaît que sur cet
            // e-mail : c'est le moment où l'engagement doit être posé.
            ->trust()
            ->forRecipient($notifiable)
            ->reason('Vous recevez cet e-mail car votre compte Kaikun 360 vient d\'être activé.')
            ->toMailMessage();
    }

    /**
     * ---------------------------------------------------------------- CLIENT
     * Il vient chercher un logement, un séjour, un chantier ou un véhicule.
     * L'enjeu : lui montrer qu'il ne s'engage pas à l'aveugle.
     */
    private function forClient(string $firstName): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Bienvenue chez Kaikun 360')
            ->preheader('Votre compte est actif. Voici comment tirer le meilleur de la plateforme.')
            ->eyebrow('Bienvenue')
            ->heading("Bonjour {$firstName}, votre compte est actif.")
            ->intro(
                'Merci de nous rejoindre. Kaikun 360 réunit en un seul endroit ce qui demandait jusqu\'ici une dizaine d\'interlocuteurs : trouver un logement, réserver un séjour, faire construire, louer un véhicule ou confier la gestion d\'un bien.',
                'Vous n\'avez rien d\'autre à faire pour l\'instant : votre espace est ouvert.',
            )
            ->action('Découvrir mon espace', '/mon-espace')
            ->highlights([
                ['title' => 'Des offres vérifiées, pas des annonces', 'body' => 'Chaque bien, véhicule et prestataire est contrôlé par notre équipe avant publication. Le sceau doré signale un dossier vérifié.'],
                ['title' => 'Un interlocuteur, pas un standard', 'body' => 'Vos demandes sont suivies par un agent Kaikun identifié, joignable depuis la messagerie de votre espace.'],
                ['title' => 'Un paiement encadré', 'body' => 'Wave, Orange Money, Free Money, carte ou virement — chaque paiement laisse un reçu et un historique consultable.'],
            ], 'Ce que vous pouvez faire dès maintenant')
            ->steps([
                'Complétez votre profil : cela nous permet de vous proposer des offres réellement pertinentes.',
                'Lancez une demande depuis l\'accueil — logement, séjour, construction, transport ou service.',
                'Suivez son avancement en temps réel : chaque demande reçoit un numéro de suivi unique.',
            ], 'Pour bien commencer')
            ->outro('Si quelque chose vous semble flou ou anormal, dites-le nous. Nous préférons une question de trop à un doute qui s\'installe.');
    }

    /**
     * -------------------------------------------------------------- DIASPORA
     * Même métier que le client, mais un problème en plus : il est loin, et il
     * a souvent déjà été échaudé. On parle donc PREUVE, pas promesse.
     */
    private function forDiaspora(string $firstName): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Bienvenue chez Kaikun 360')
            ->preheader('Investir au pays depuis l\'étranger, avec des preuves à chaque étape.')
            ->eyebrow('Bienvenue')
            ->heading("Bonjour {$firstName}, bienvenue chez vous.")
            ->intro(
                'Vous connaissez le problème : à distance, tout repose sur la parole de quelqu\'un. Un cousin, un intermédiaire, une photo envoyée par messagerie. Nous avons construit Kaikun 360 pour remplacer cette parole par des preuves.',
                'Votre compte est actif, et votre espace est prêt.',
            )
            ->action('Accéder à mon espace diaspora', '/mon-espace/diaspora')
            ->highlights([
                ['title' => 'Vous voyez, vous ne croyez pas', 'body' => 'Visites filmées et datées, photos horodatées, avancement de chantier documenté. Tout est archivé dans votre dossier.'],
                ['title' => 'Des documents vérifiés en amont', 'body' => 'Titres fonciers, actes et identités contrôlés avec notaire et géomètre avant qu\'une offre ne vous soit proposée.'],
                ['title' => 'Un rapport de suivi régulier', 'body' => 'Vous recevez l\'état de votre projet sans avoir à le réclamer, où que vous soyez et quel que soit le fuseau horaire.'],
            ], 'Ce que change Kaikun 360 pour vous')
            ->steps([
                'Complétez votre profil et indiquez votre pays de résidence.',
                'Décrivez votre projet : achat, construction, gestion locative ou suivi d\'un bien existant.',
                'Un agent Kaikun vous rappelle et devient votre interlocuteur unique jusqu\'à la fin du projet.',
            ], 'Pour bien commencer')
            ->outro('Nous savons ce que représente un investissement fait depuis l\'étranger. Nous le traitons avec ce sérieux-là.');
    }

    /**
     * --------------------------------------------------------- PROPRIÉTAIRE
     * Il a un bien et veut le rentabiliser sans y passer ses journées.
     * L'enjeu : lui montrer que la mise en ligne est simple et encadrée.
     */
    private function forProprietaire(string $firstName): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Bienvenue chez Kaikun 360')
            ->preheader('Votre espace propriétaire est ouvert : déposez votre premier bien.')
            ->eyebrow('Espace propriétaire')
            ->heading("Bonjour {$firstName}, votre espace propriétaire est ouvert.")
            ->intro(
                'Un bien qui dort coûte de l\'argent ; un bien mal géré en coûte davantage. Kaikun 360 vous donne les deux bouts de la chaîne : la visibilité auprès de locataires et d\'acheteurs vérifiés, et la gestion quotidienne si vous ne souhaitez pas vous en charger.',
                'Vous pouvez déposer votre premier bien dès maintenant.',
            )
            ->action('Déposer un bien', '/espace-proprietaire/biens/nouveau')
            ->highlights([
                ['title' => 'Une mise en ligne encadrée', 'body' => 'Notre équipe vérifie votre dossier avant publication. Cette exigence protège votre annonce autant que nos utilisateurs : un bien vérifié se loue plus vite.'],
                ['title' => 'Gestion locative optionnelle', 'body' => 'Recherche de locataire, états des lieux, encaissement des loyers, entretien : vous déléguez ce que vous voulez, vous gardez le reste.'],
                ['title' => 'Des revenus traçables', 'body' => 'Loyers, commissions et versements sont détaillés dans votre espace. Aucun montant n\'apparaît sans justificatif.'],
            ], 'Ce que vous obtenez')
            ->steps([
                'Complétez votre profil et déposez vos pièces (identité, titre de propriété) : c\'est ce qui déclenche la vérification.',
                'Créez votre première fiche : photos, localisation, conditions. Comptez dix minutes.',
                'Notre équipe la contrôle, puis la publie. Vous êtes prévenu par e-mail dès sa mise en ligne.',
            ], 'Vos trois prochaines étapes')
            ->note('Un conseil qui change tout : des photos nettes et lumineuses, et une description honnête des défauts. Les annonces les plus transparentes sont, de loin, celles qui se concrétisent le plus vite.');
    }

    /**
     * ---------------------------------------------------------- PRESTATAIRE
     * Artisan, agence, transporteur, restaurateur… Il veut des missions.
     * L'enjeu : être clair sur la condition d'accès (dossier vérifié).
     */
    private function forPrestataire(string $firstName): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Bienvenue dans le réseau Kaikun Pro')
            ->preheader('Votre compte prestataire est actif. Dernière étape : la vérification de votre dossier.')
            ->eyebrow('Kaikun Pro')
            ->heading("Bonjour {$firstName}, bienvenue dans le réseau Kaikun Pro.")
            ->intro(
                'Vous rejoignez un réseau de professionnels sélectionnés — bâtiment, transport, hébergement, restauration, services. Nos clients ne cherchent pas le prestataire le moins cher : ils cherchent celui dont le travail est garanti. C\'est exactement le positionnement que nous défendons pour vous.',
                'Votre compte est actif. Il reste une étape avant de recevoir des missions.',
            )
            ->action('Compléter mon dossier professionnel', '/espace-prestataire/profil')
            ->highlights([
                ['title' => 'Des missions qualifiées', 'body' => 'Vous ne recevez que des demandes correspondant à votre métier, votre zone d\'intervention et votre disponibilité.'],
                ['title' => 'Un paiement sécurisé', 'body' => 'Les montants sont convenus au devis et versés selon un calendrier connu d\'avance. Pas de relance, pas d\'impayé à courir après.'],
                ['title' => 'Une réputation qui vous appartient', 'body' => 'Chaque mission terminée alimente votre profil public. Un bon travail se voit, et se transforme en missions suivantes.'],
            ], 'Ce que le réseau vous apporte')
            ->steps([
                'Déposez vos pièces : registre de commerce, NINEA, attestation d\'assurance, pièce d\'identité du gérant.',
                'Renseignez vos métiers, vos zones d\'intervention et vos tarifs indicatifs.',
                'Notre équipe vérifie votre dossier, puis active votre profil public. Les premières missions peuvent alors vous être adressées.',
            ], 'Pour être visible et recevoir des missions')
            ->note('Tant que votre dossier n\'est pas vérifié, votre profil reste invisible pour les clients. C\'est cette exigence qui fait la valeur du sceau Kaikun Pro — et donc la vôtre.');
    }

    /**
     * ------------------------------------------------------------ ENTREPRISE
     * Compte B2B : séminaires, team building, besoins récurrents, logement de
     * collaborateurs. L'enjeu : interlocuteur dédié et devis.
     */
    private function forEntreprise(string $firstName): BrandedMail
    {
        return BrandedMail::make()
            ->subject('Bienvenue chez Kaikun 360')
            ->preheader('Votre compte entreprise est actif. Un interlocuteur dédié vous est attribué.')
            ->eyebrow('Compte entreprise')
            ->heading("Bonjour {$firstName}, votre compte entreprise est actif.")
            ->intro(
                'Organiser un séminaire, loger des collaborateurs, déplacer une équipe ou faire construire : ce sont des sujets où le temps perdu en coordination coûte plus cher que la prestation elle-même. Kaikun 360 vous donne un point d\'entrée unique et une facturation claire.',
                'Votre compte est ouvert, et un interlocuteur dédié vous est attribué dès votre première demande.',
            )
            ->action('Décrire mon besoin', '/espace-entreprise/demandes/nouvelle')
            ->highlights([
                ['title' => 'Un devis unique, plusieurs prestataires', 'body' => 'Hébergement, restauration, transport, animation : nous assemblons l\'ensemble et vous adressons un seul devis, détaillé ligne par ligne.'],
                ['title' => 'Un interlocuteur dédié', 'body' => 'Un agent Kaikun suit votre compte, connaît vos contraintes et vous répond directement — pas un formulaire, pas un standard.'],
                ['title' => 'Une facturation en règle', 'body' => 'Factures conformes, justificatifs par poste de dépense, historique consultable. Votre comptabilité et vos audits y trouvent leur compte.'],
            ], 'Ce que nous prenons en charge')
            ->steps([
                'Complétez la fiche de votre société (raison sociale, NINEA, contact facturation).',
                'Décrivez votre besoin : dates, nombre de participants, ville, budget indicatif.',
                'Vous recevez un devis assemblé et chiffré, modifiable jusqu\'à votre validation.',
            ], 'Vos trois prochaines étapes')
            ->outro('Un besoin récurrent ou un volume important ? Répondez à cet e-mail : nous étudions une convention adaptée à votre rythme.');
    }

    /**
     * Prénom de l'utilisateur, pour une adresse personnelle plutôt qu'un
     * « Bonjour, » anonyme. On retient le premier mot du nom complet ; si le
     * champ est vide, on retombe sur une formule neutre mais correcte.
     */
    private function firstName(object $notifiable): string
    {
        $name = trim((string) ($notifiable->name ?? ''));

        if ($name === '') {
            return 'et bienvenue';
        }

        return explode(' ', $name)[0];
    }

    /**
     * Charge utile du canal `database` (cloche de notifications dans l'espace).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'compte',
            'title' => 'Bienvenue chez Kaikun 360',
            'body' => 'Votre compte est actif. Complétez votre profil pour tirer le meilleur de la plateforme.',
            'action_url' => '/mon-espace',
        ];
    }
}
