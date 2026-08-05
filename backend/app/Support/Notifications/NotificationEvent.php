<?php

namespace App\Support\Notifications;

/**
 * Catalogue des ÉVÉNEMENTS notifiables pilotables depuis le back-office
 * (F7.2.l — CDC §6, module *Paramètres* : « … pages, FAQ, notifications »).
 *
 * Chaque classe de notification d'exploitation déclare l'événement dont elle
 * relève ; l'équipe peut alors couper cet événement depuis l'écran Paramètres
 * sans redéploiement (voir {@see NotificationSettings}).
 *
 * ⚠️ **Les notifications de SÉCURITÉ n'y figurent pas volontairement.** Les
 * codes de vérification et la double authentification
 * ({@see \App\Modules\Core\Notifications\VerificationCodeNotification}) ne sont
 * PAS désactivables : les couper condamnerait l'accès au back-office et
 * casserait l'inscription. Un réglage capable de verrouiller la plateforme
 * n'a pas sa place dans une interface d'administration.
 *
 * Un événement ABSENT de la configuration enregistrée est considéré comme
 * ACTIF : ajouter une notification au code ne l'éteint jamais par surprise.
 */
enum NotificationEvent: string
{
    // ---- Destinataire : le client / le propriétaire -----------------------
    case ACCOUNT_WELCOME = 'account_welcome';
    case BOOKING_CONFIRMED = 'booking_confirmed';
    case QUOTE_RECEIVED = 'quote_received';
    case DOCUMENT_REQUIRED = 'document_required';
    case REQUEST_STATUS_CHANGED = 'request_status_changed';
    case NEW_MESSAGE = 'new_message';
    case RESOURCE_VALIDATED = 'resource_validated';
    case TEAM_BUILDING_QUOTE = 'team_building_quote';
    case TEAM_BUILDING_QUOTE_ACCEPTED = 'team_building_quote_accepted';

    // ---- Destinataire : l'équipe Kaikun -----------------------------------
    case NEW_REQUEST_TO_HANDLE = 'new_request_to_handle';
    case RESOURCE_TO_VALIDATE = 'resource_to_validate';
    case TEAM_BUILDING_REQUEST = 'team_building_request';
    case CONSTRUCTION_REQUEST = 'construction_request';
    case CONTACT_MESSAGE = 'contact_message';
    case QUOTE_ANSWERED = 'quote_answered';

    /**
     * Libellé lisible, affiché dans la liste des interrupteurs.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACCOUNT_WELCOME => 'E-mail de bienvenue',
            self::BOOKING_CONFIRMED => 'Confirmation de réservation',
            self::QUOTE_RECEIVED => 'Devis reçu',
            self::DOCUMENT_REQUIRED => 'Pièce justificative demandée',
            self::REQUEST_STATUS_CHANGED => 'Changement de statut d’une demande',
            self::NEW_MESSAGE => 'Nouveau message',
            self::RESOURCE_VALIDATED => 'Offre validée (bien, véhicule)',
            self::TEAM_BUILDING_QUOTE => 'Devis team building envoyé',
            self::TEAM_BUILDING_QUOTE_ACCEPTED => 'Devis team building accepté (à régler)',
            self::NEW_REQUEST_TO_HANDLE => 'Nouvelle demande à traiter',
            self::RESOURCE_TO_VALIDATE => 'Nouvelle offre à valider',
            self::TEAM_BUILDING_REQUEST => 'Nouvelle demande team building',
            self::CONSTRUCTION_REQUEST => 'Nouvelle demande de chantier',
            self::CONTACT_MESSAGE => 'Nouveau message depuis la page Contact',
            self::QUOTE_ANSWERED => 'Réponse du client à un devis',
        };
    }

    /**
     * Précision affichée sous le libellé : à qui part la notification et quand.
     */
    public function description(): string
    {
        return match ($this) {
            self::ACCOUNT_WELCOME => 'Au nouvel inscrit, une seule fois, quand son compte devient actif. Le contenu s’adapte au profil (client, propriétaire, prestataire, entreprise, diaspora).',
            self::BOOKING_CONFIRMED => 'Au client, dès que le paiement de sa réservation est encaissé.',
            self::QUOTE_RECEIVED => 'Au client, quand un devis lui est adressé.',
            self::DOCUMENT_REQUIRED => 'À l’utilisateur, quand l’équipe réclame une pièce à son dossier.',
            self::REQUEST_STATUS_CHANGED => 'Au demandeur, à chaque étape de sa demande (reçue, en vérification, confirmée…).',
            self::NEW_MESSAGE => 'Au destinataire d’un message dans la messagerie interne.',
            self::RESOURCE_VALIDATED => 'Au propriétaire ou au prestataire, quand son offre est approuvée et publiée.',
            self::TEAM_BUILDING_QUOTE => 'À l’entreprise, quand son devis pack lui est envoyé.',
            self::TEAM_BUILDING_QUOTE_ACCEPTED => 'À l’entreprise, dès qu’elle accepte son devis : la réservation est créée et le montant devient exigible. Sans cet e-mail, l’accord resterait sans suite visible.',
            self::NEW_REQUEST_TO_HANDLE => 'À l’équipe, à l’arrivée d’une demande de service.',
            self::RESOURCE_TO_VALIDATE => 'À l’équipe, quand un bien ou un véhicule est déposé et attend une décision.',
            self::TEAM_BUILDING_REQUEST => 'À l’équipe, à l’arrivée d’une demande d’entreprise.',
            self::CONSTRUCTION_REQUEST => 'À l’équipe, à l’arrivée d’une demande de construction ou de rénovation, avec son estimation automatique.',
            self::CONTACT_MESSAGE => 'À l’équipe, à chaque message déposé sur la page Contact. L’e-mail porte le message entier : la plupart se règlent d’une réponse directe. Le dépôt étant ouvert à tous, c’est l’alerte à éteindre en premier si le volume gêne.',
            self::QUOTE_ANSWERED => 'À l’agent qui a chiffré le devis — lui seul, pas toute l’équipe — quand son client l’accepte ou le refuse.',
        };
    }

    /**
     * Public visé — sert à regrouper les interrupteurs en deux blocs à l'écran.
     */
    public function audience(): string
    {
        return match ($this) {
            self::NEW_REQUEST_TO_HANDLE,
            self::RESOURCE_TO_VALIDATE,
            self::TEAM_BUILDING_REQUEST,
            self::CONSTRUCTION_REQUEST,
            self::CONTACT_MESSAGE,
            self::QUOTE_ANSWERED => 'Équipe Kaikun',
            default => 'Clients & partenaires',
        };
    }

    /**
     * Catalogue complet pour le back-office : `[{ value, label, description,
     * audience, enabled }]`, l'état actif venant des réglages enregistrés.
     *
     * @return list<array{value: string, label: string, description: string, audience: string, enabled: bool}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $event) => [
            'value' => $event->value,
            'label' => $event->label(),
            'description' => $event->description(),
            'audience' => $event->audience(),
            'enabled' => NotificationSettings::eventEnabled($event),
        ], self::cases());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event) => $event->value, self::cases());
    }
}
