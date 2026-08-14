<?php

namespace App\Support\Mail;

use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Models\Profile;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Modules\Core\Notifications\WelcomeNotification;
use App\Modules\Core\Services\VerificationService;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use App\Models\ContactMessage;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistEntryProcessedNotification;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\DocumentRequiredNotification;
use App\Notifications\NewRequestToHandleNotification;
use App\Notifications\QuoteReceivedNotification;
use App\Notifications\RequestStatusChangedNotification;

/**
 * PRÉVISUALISATION DES E-MAILS en environnement local.
 *
 * Relire un e-mail dans un fichier de log est impraticable : on ne juge ni la
 * mise en page, ni la lisibilité sur mobile, ni le rendu en mode sombre. Cette
 * classe rejoue chaque notification avec des données FICTIVES et renvoie le HTML
 * final, affichable directement dans le navigateur (voir routes/web.php).
 *
 * ⚠️ Rien n'est enregistré en base : tous les objets sont construits en mémoire
 * (`new Model([...])`, jamais `create()`), et aucun e-mail n'est envoyé.
 * La route associée est fermée hors environnement local.
 */
class MailPreview
{
    /**
     * Catalogue des aperçus : clé d'URL => libellé affiché dans le sommaire.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'bienvenue-client' => 'Bienvenue — Client',
            'bienvenue-diaspora' => 'Bienvenue — Diaspora',
            'bienvenue-proprietaire' => 'Bienvenue — Propriétaire',
            'bienvenue-prestataire' => 'Bienvenue — Prestataire',
            'bienvenue-entreprise' => 'Bienvenue — Entreprise',
            'code-verification' => 'Code de vérification (inscription)',
            'code-reinitialisation' => 'Code de réinitialisation du mot de passe',
            'code-double-authentification' => 'Code de connexion back-office (2FA)',
            'reservation-confirmee' => 'Réservation confirmée',
            'devis-recu' => 'Devis reçu',
            'devis-construction' => 'Devis de chantier',
            'devis-team-building' => 'Devis team building',
            'document-requis' => 'Pièce justificative demandée',
            'demande-avancee' => 'Demande : changement de statut',
            'bien-publie' => 'Bien validé et publié',
            'vehicule-publie' => 'Véhicule validé et publié',
            'interne-nouvelle-demande' => 'Interne — Nouvelle demande à traiter',
            'interne-bien-a-valider' => 'Interne — Bien à valider',
            'interne-vehicule-a-valider' => 'Interne — Véhicule à valider',
            'interne-team-building' => 'Interne — Demande team building',
            'interne-chantier' => 'Interne — Nouvelle demande de chantier',
            'interne-contact' => 'Interne — Message depuis la page Contact',
            'liste-attente-proprietaire' => 'Liste d’attente — Invitation propriétaire',
            'liste-attente-prestataire' => 'Liste d’attente — Invitation prestataire',
            'liste-attente-client' => 'Liste d’attente — Invitation client',
            'liste-attente-team-building' => 'Liste d’attente — Invitation team building',
            'liste-attente-diaspora' => 'Liste d’attente — Invitation diaspora',
        ];
    }

    /**
     * Rend l'aperçu demandé.
     *
     * @param  string  $key     clé du catalogue
     * @param  bool    $text    true pour obtenir la version texte brut
     * @return string           HTML (ou texte) prêt à afficher
     */
    public static function render(string $key, bool $text = false): string
    {
        $message = self::message($key);

        // ->view([html, texte]) : le premier gabarit est le HTML, le second le texte.
        [$htmlView, $textView] = $message->view;

        return view($text ? $textView : $htmlView, $message->viewData)->render();
    }

    /**
     * Le MailMessage que produirait la notification, avec ses données fictives.
     *
     * Sert à la fois à l'aperçu navigateur ({@see self::render()}) et à l'envoi
     * réel de contrôle (`php artisan mail:apercu`) : les deux passent donc par
     * exactement le même message, sans risque de diverger.
     */
    public static function message(string $key): \Illuminate\Notifications\Messages\MailMessage
    {
        return self::notification($key)->toMail(self::recipient($key));
    }

    /**
     * Le DESTINATAIRE fictif, choisi selon l'aperçu : le profil détermine les
     * liens d'espace privé, il faut donc qu'il corresponde au scénario.
     */
    private static function recipient(string $key): User
    {
        $type = match (true) {
            str_contains($key, 'proprietaire'), $key === 'bien-publie' => ProfileType::PROPRIETAIRE,
            str_contains($key, 'prestataire'), $key === 'vehicule-publie' => ProfileType::PRESTATAIRE,
            str_contains($key, 'entreprise'), $key === 'devis-team-building' => ProfileType::ENTREPRISE,
            str_contains($key, 'diaspora') => ProfileType::DIASPORA,
            default => ProfileType::CLIENT,
        };

        $user = new User([
            'name' => 'Awa Ndiaye',
            'email' => 'awa.ndiaye@example.sn',
        ]);

        // Relation renseignée « à la main » : l'objet n'existe pas en base, mais
        // SpaceLink n'a besoin que du type de profil pour calculer les liens.
        $user->setRelation('profile', new Profile(['type' => $type->value]));

        return $user;
    }

    /**
     * Fabrique la notification correspondant à la clé, avec des données fictives
     * mais RÉALISTES : des montants et des dates crédibles permettent de juger
     * la mise en page comme elle sera vue en production.
     */
    private static function notification(string $key): object
    {
        return match ($key) {
            'bienvenue-client' => new WelcomeNotification(ProfileType::CLIENT),
            'bienvenue-diaspora' => new WelcomeNotification(ProfileType::DIASPORA),
            'bienvenue-proprietaire' => new WelcomeNotification(ProfileType::PROPRIETAIRE),
            'bienvenue-prestataire' => new WelcomeNotification(ProfileType::PRESTATAIRE),
            'bienvenue-entreprise' => new WelcomeNotification(ProfileType::ENTREPRISE),

            'code-verification' => new VerificationCodeNotification('418 903', VerificationService::PURPOSE_ACCOUNT, 'email'),
            'code-reinitialisation' => new VerificationCodeNotification('276145', VerificationService::PURPOSE_PASSWORD_RESET, 'email'),
            'code-double-authentification' => new VerificationCodeNotification('530872', VerificationService::PURPOSE_TWO_FACTOR, 'email'),

            'reservation-confirmee' => new BookingConfirmedNotification(new Booking([
                'reference' => 'BK-2026-0731',
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-21',
                'guests' => 4,
                'amount_xof' => 840000,
                'caution_xof' => 150000,
            ])),

            'devis-recu' => new QuoteReceivedNotification(new Quote([
                'reference' => 'DV-2026-0412',
                'amount_xof' => 1250000,
            ])),

            'devis-construction' => new \App\Modules\Build\Notifications\ConstructionQuoteSentNotification(new ConstructionQuote([
                'reference' => 'CH-2026-0087',
                'subtotal_xof' => 18400000,
                'total_xof' => 21160000,
                'valid_until' => '2026-09-30',
            ])),

            'devis-team-building' => new \App\Modules\TeamBuilding\Notifications\TeamBuildingQuoteSentNotification(new TeamBuildingQuote([
                'reference' => 'TB-2026-0034',
                'subtotal_xof' => 4200000,
                'total_xof' => 4830000,
            ])),

            'document-requis' => new DocumentRequiredNotification(
                'Titre foncier ou bail emphytéotique',
                'Le document transmis était illisible sur les deux dernières pages. Une photo nette, prise à plat et en lumière du jour, suffira.',
            ),

            'demande-avancee' => new RequestStatusChangedNotification(new ServiceRequest([
                'reference' => 'DM-2026-1180',
                'service_type' => ServiceType::cases()[0]->value,
                'status' => RequestStatus::cases()[1]->value,
            ])),

            'bien-publie' => new \App\Modules\Immo\Notifications\PropertyValidatedNotification(new Property([
                'title' => 'Villa 4 chambres avec piscine — Saly',
                'price_xof' => 95000000,
                'published_at' => now(),
            ])),

            'vehicule-publie' => new \App\Modules\Mobility\Notifications\VehicleValidatedNotification(new Vehicle([
                'reference' => 'VH-2026-0261',
            ])),

            'interne-nouvelle-demande' => new NewRequestToHandleNotification(new ServiceRequest([
                'reference' => 'DM-2026-1194',
                'service_type' => ServiceType::cases()[0]->value,
                'status' => RequestStatus::cases()[0]->value,
                'city' => 'Thiès',
                'budget_xof' => 3500000,
            ])),

            'interne-bien-a-valider' => new \App\Modules\Immo\Notifications\NewPropertyToValidateNotification(new Property([
                'title' => 'Appartement F3 — Mermoz, Dakar',
                'price_xof' => 42000000,
            ])),

            'interne-vehicule-a-valider' => new \App\Modules\Mobility\Notifications\NewVehicleToValidateNotification(new Vehicle([
                'reference' => 'VH-2026-0263',
            ])),

            'interne-team-building' => new \App\Modules\TeamBuilding\Notifications\NewTeamBuildingRequestNotification(new TeamBuildingRequest([
                'reference' => 'TB-2026-0041',
                'participants' => 38,
                'city' => 'Saly',
                'start_date' => '2026-11-06',
                'end_date' => '2026-11-08',
                'budget_xof' => 6000000,
            ])),

            // F8.15.b — l'alerte de chantier n'existait pas : le dépôt était
            // muet, faute d'écran public qui alimente `construction_requests`.
            'interne-chantier' => new \App\Modules\Build\Notifications\NewConstructionRequestNotification(new ConstructionRequest([
                'reference' => 'CST-2026-0117',
                'objective' => ConstructionObjective::CONSTRUCTION_NEUVE,
                'surface_m2' => 120,
                'finish_level' => FinishLevel::STANDARD,
                'city' => 'Thiès',
                'budget_xof' => 30000000,
                'estimated_cost_xof' => 33000000,
            ])),

            // F8.15.c bis — l'arrivée d'un message de contact n'alertait
            // personne : le webhook n8n était le seul relais, et il dort.
            'interne-contact' => new \App\Notifications\NewContactMessageNotification(new ContactMessage([
                'name' => 'Awa Diop',
                'email' => 'awa.diop@example.com',
                'subject' => 'Villa à Saly pour août',
                'message' => "Bonjour,\n\nJe cherche une villa à Saly pour la première quinzaine d'août, 6 personnes. Est-ce encore disponible ? Quel est le tarif à la semaine ?\n\nMerci d'avance.",
            ])),

            // 2026-08-14 — Invitation envoyée au PROSPECT (pas à l'équipe) quand
            // un agent marque son inscription « traitée ». Une entrée par
            // catégorie : le contenu diffère entièrement d'une catégorie à
            // l'autre (voir WaitlistEntryProcessedNotification::toMail()).
            'liste-attente-proprietaire' => new WaitlistEntryProcessedNotification(new WaitlistEntry([
                'name' => 'Awa Diop',
                'category' => 'proprietaire',
                'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            ])),
            'liste-attente-prestataire' => new WaitlistEntryProcessedNotification(new WaitlistEntry([
                'name' => 'Moussa Ba',
                'category' => 'prestataire',
                'details' => ['type_service' => 'mobilite'],
            ])),
            'liste-attente-client' => new WaitlistEntryProcessedNotification(new WaitlistEntry([
                'name' => 'Fatou Sy',
                'category' => 'client',
                'details' => ['univers' => 'sejours'],
            ])),
            'liste-attente-team-building' => new WaitlistEntryProcessedNotification(new WaitlistEntry([
                'name' => 'Cheikh Diallo',
                'category' => 'team_building',
                'details' => ['taille_equipe' => 25, 'budget_xof' => 3000000],
            ])),
            'liste-attente-diaspora' => new WaitlistEntryProcessedNotification(new WaitlistEntry([
                'name' => 'Ibrahima Fall',
                'category' => 'diaspora',
                'details' => ['pays_residence' => 'France', 'type_projet' => 'construction'],
            ])),

            default => abort(404, 'Aperçu inconnu.'),
        };
    }
}
