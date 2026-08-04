<?php

namespace App\Modules\Admin\Enums;

/**
 * Catalogue des permissions du back-office et de leur DÉLÉGATION (F7.1.b).
 *
 * Depuis F7.1.b, le back-office suit le principe du « grant pur par personne » :
 * le rôle `agent_kaikun` n'ouvre QUE l'accès (`consulter:dashboard-admin`), et
 * chaque dossier qu'un sous-admin a le droit de traiter lui est **délégué
 * individuellement** par le super administrateur (permissions directes Spatie).
 *
 * Cet enum est la source de vérité du vocabulaire des permissions : libellé
 * lisible, regroupement pour l'affichage de la matrice, et distinction entre
 * permissions **opérationnelles** (délégables par un admin) et de
 * **gouvernance** (délégables uniquement par un super_admin — garde-fou
 * d'escalade). `consulter:dashboard-admin` est l'accès de base porté par le
 * rôle : il n'est donc PAS délégable (il n'apparaît pas dans les cases).
 *
 * ⚠️ **F8.12.b — une seconde permission a rejoint le rôle** :
 * `repondre:messages`. Ce n'est pas un relâchement du principe, c'est sa limite :
 * le grant pur sert à cloisonner des LEVIERS (l'argent, les comptes, la
 * validation) ; répondre à un client qui écrit est le métier de base d'un agent.
 * Les deux forment `carriedByRole()`, exclu de la matrice de délégation.
 */
enum AdminPermission: string
{
    // Accès de base au back-office (porté par le rôle, non délégable).
    case CONSULTER_DASHBOARD = 'consulter:dashboard-admin';

    // Validation des ressources soumises à approbation (opérationnel).
    case VALIDER_BIEN = 'valider:bien';
    case VALIDER_VEHICULE = 'valider:vehicule';
    case VALIDER_EXPERIENCE = 'valider:experience';
    case VALIDER_PRESTATAIRE = 'valider:prestataire';

    // Exploitation quotidienne des dossiers (opérationnel).
    case GERER_GESTION_LOCATIVE = 'gerer:gestion-locative';
    case GERER_CHANTIERS = 'gerer:chantiers';
    case GERER_NUITEES = 'gerer:nuitees';
    case TRAITER_DEMANDES = 'traiter:demandes';
    case MODERER_AVIS = 'moderer:avis';
    // Support de la messagerie interne (F8.12). Deux particularités :
    //   1. elle définit aussi le VIVIER des agents à qui le serveur assigne un
    //      nouveau fil (cf. SupportAssignmentService) ;
    //   2. depuis F8.12.b elle est **portée par le rôle** `agent_kaikun`, comme
    //      l'accès au tableau de bord — répondre aux clients est le métier de
    //      base d'un agent, pas un levier sensible. Elle n'apparaît donc PAS
    //      dans la matrice de délégation (`delegable()`) : y montrer une case
    //      décochée alors que le droit est effectif serait un mensonge d'écran.
    case REPONDRE_MESSAGES = 'repondre:messages';

    // Gouvernance de la plateforme (sensible → délégable par super_admin seul).
    case GERER_UTILISATEURS = 'gerer:utilisateurs';
    case GERER_PAIEMENTS = 'gerer:paiements';
    case GERER_PARAMETRES = 'gerer:parametres';

    /**
     * Libellé lisible (français), affiché dans la matrice de délégation.
     */
    public function label(): string
    {
        return match ($this) {
            self::CONSULTER_DASHBOARD => 'Accès au tableau de bord',
            self::VALIDER_BIEN => 'Valider les biens immobiliers',
            self::VALIDER_VEHICULE => 'Valider les véhicules',
            self::VALIDER_EXPERIENCE => 'Valider les circuits & expériences',
            self::VALIDER_PRESTATAIRE => 'Valider les prestataires',
            self::GERER_GESTION_LOCATIVE => 'Gérer la gestion locative',
            self::GERER_CHANTIERS => 'Suivre les chantiers',
            self::GERER_NUITEES => 'Exploiter les nuitées',
            self::TRAITER_DEMANDES => 'Traiter les demandes',
            self::MODERER_AVIS => 'Modérer les avis',
            self::REPONDRE_MESSAGES => 'Répondre aux messages du support',
            self::GERER_UTILISATEURS => 'Gérer les utilisateurs & l’équipe',
            self::GERER_PAIEMENTS => 'Gérer les paiements',
            self::GERER_PARAMETRES => 'Gérer les paramètres & le contenu',
        };
    }

    /**
     * Groupe d'affichage de la permission (colonnes de la matrice back-office).
     */
    public function group(): string
    {
        return match ($this) {
            self::CONSULTER_DASHBOARD => 'Accès',
            self::VALIDER_BIEN,
            self::VALIDER_VEHICULE,
            self::VALIDER_EXPERIENCE,
            self::VALIDER_PRESTATAIRE => 'Validation',
            self::GERER_GESTION_LOCATIVE,
            self::GERER_CHANTIERS,
            self::GERER_NUITEES,
            self::TRAITER_DEMANDES,
            self::MODERER_AVIS,
            self::REPONDRE_MESSAGES => 'Exploitation',
            self::GERER_UTILISATEURS,
            self::GERER_PAIEMENTS,
            self::GERER_PARAMETRES => 'Gouvernance',
        };
    }

    /**
     * Une permission de gouvernance ne peut être déléguée que par un super_admin
     * (garde-fou d'escalade : un admin ne « fabrique » pas un agent aussi
     * puissant que lui sur les leviers sensibles).
     */
    public function isGovernance(): bool
    {
        return $this->group() === 'Gouvernance';
    }

    /**
     * Les permissions DÉLÉGABLES : tout sauf celles que le **rôle** back-office
     * porte déjà (cf. `carriedByRole()`).
     *
     * @return array<int, string>
     */
    public static function delegable(): array
    {
        return array_values(array_map(
            fn (self $p) => $p->value,
            array_filter(self::cases(), fn (self $p) => ! in_array($p, self::carriedByRole(), true)),
        ));
    }

    /**
     * Permissions **portées par le rôle** `agent_kaikun` : acquises en devenant
     * membre de l'équipe, donc ni à cocher ni à décocher dans la matrice.
     *
     * L'accès au tableau de bord depuis F7.1.b ; la réponse aux messages du
     * support depuis F8.12.b — répondre aux clients est le métier de base d'un
     * agent, et un droit qu'il faudrait penser à accorder laisserait les fils
     * s'entasser dans « Non assignés » à chaque arrivée dans l'équipe.
     *
     * @return array<int, self>
     */
    public static function carriedByRole(): array
    {
        return [self::CONSULTER_DASHBOARD, self::REPONDRE_MESSAGES];
    }

    /**
     * Permissions de gouvernance (délégables par super_admin uniquement).
     *
     * @return array<int, string>
     */
    public static function governance(): array
    {
        return array_values(array_map(
            fn (self $p) => $p->value,
            array_filter(self::cases(), fn (self $p) => $p->isGovernance()),
        ));
    }

    /**
     * Permissions OPÉRATIONNELLES : le socle « agent pleinement outillé »
     * (délégable par un admin). Sert notamment aux tests qui ont besoin d'un
     * agent opérationnel après l'allègement du rôle (grant pur, F7.1.b).
     *
     * @return array<int, string>
     */
    public static function operational(): array
    {
        return array_values(array_diff(self::delegable(), self::governance()));
    }

    /**
     * Catalogue prêt pour l'API/frontend : chaque permission délégable avec son
     * libellé, son groupe et l'exigence super_admin éventuelle.
     *
     * @return array<int, array{value: string, label: string, group: string, requires_super_admin: bool}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'group' => $p->group(),
            'requires_super_admin' => $p->isGovernance(),
        ], array_values(array_filter(
            self::cases(),
            fn (self $p) => ! in_array($p, self::carriedByRole(), true),
        )));
    }
}
