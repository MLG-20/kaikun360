<?php

namespace App\Support\Mail;

use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;

/**
 * Résout le bon lien d'espace privé selon le PROFIL du destinataire (et, à
 * défaut, selon son RÔLE).
 *
 * POURQUOI ? Le frontend Angular n'a pas un espace connecté, mais QUATRE, à des
 * adresses différentes : `/mon-espace` (client et diaspora),
 * `/espace-proprietaire`, `/espace-prestataire`, `/espace-entreprise`.
 *
 * Une notification comme « une pièce manque à votre dossier » peut viser
 * n'importe lequel de ces profils. Coder « /mon-espace/documents » en dur y
 * enverrait le prestataire sur une page inexistante : un lien mort dans un
 * e-mail, c'est exactement le genre de détail qui fait douter de la solidité
 * d'une plateforme. Cette classe garantit que chaque destinataire reçoit
 * l'adresse qui existe RÉELLEMENT pour lui.
 *
 * ⚠️ Les chemins ci-dessous doivent rester alignés sur les fichiers de routes
 * Angular (`frontend/src/app/features/*​/*.routes.ts`).
 */
class SpaceLink
{
    /**
     * Préfixe de l'espace connecté du destinataire.
     *
     * Deux sources, dans cet ordre : le **profil** (il porte le sens métier),
     * puis le **rôle** si le profil manque. Le dernier repli reste
     * `/mon-espace`, la seule adresse qui existe pour tout le monde.
     */
    public static function base(object $notifiable): string
    {
        $type = $notifiable->profile?->type ?? null;

        return match ($type) {
            ProfileType::PROPRIETAIRE => '/espace-proprietaire',
            ProfileType::PRESTATAIRE => '/espace-prestataire',
            ProfileType::ENTREPRISE => '/espace-entreprise',
            // Profil absent (compte importé, jeu d'essai, création hors du
            // parcours d'inscription) : le RÔLE dit la même chose et il est,
            // lui, indispensable — c'est sur lui que les espaces sont
            // cloisonnés côté Angular. Sans ce second recours (F8.14), le repli
            // envoyait vers `/mon-espace`, une adresse gardée par le rôle
            // `client` : le destinataire y aurait été refoulé.
            default => self::fromRole($notifiable),
        };
    }

    /**
     * Repli sur le rôle de sécurité quand le profil manque.
     *
     * L'ordre est celui de la spécificité : un compte multi-rôles (rare, mais
     * possible côté back-office) sera dirigé vers l'espace le plus spécifique
     * qu'il possède, et `/mon-espace` reste le dernier mot — il existe toujours.
     */
    private static function fromRole(object $notifiable): string
    {
        if (! method_exists($notifiable, 'hasRole')) {
            return '/mon-espace';
        }

        return match (true) {
            $notifiable->hasRole(UserRole::ENTREPRISE->value) => '/espace-entreprise',
            $notifiable->hasRole(UserRole::PRESTATAIRE->value) => '/espace-prestataire',
            $notifiable->hasRole(UserRole::PROPRIETAIRE->value) => '/espace-proprietaire',
            default => '/mon-espace',
        };
    }

    /**
     * Chemin d'une page de l'espace du destinataire : `to($user, 'profil')`.
     */
    public static function to(object $notifiable, string $path = ''): string
    {
        $path = trim($path, '/');

        return $path === ''
            ? self::base($notifiable)
            : self::base($notifiable).'/'.$path;
    }

    /**
     * Page où DÉPOSER une pièce justificative.
     *
     * Seul l'espace propriétaire dispose d'un écran « Documents » dédié ; pour
     * les autres profils, le dépôt se fait depuis la page de profil.
     */
    public static function documents(object $notifiable): string
    {
        $type = $notifiable->profile?->type ?? null;

        return $type === ProfileType::PROPRIETAIRE
            ? '/espace-proprietaire/documents'
            : self::to($notifiable, 'profil');
    }

    /**
     * Page listant les demandes du destinataire.
     *
     * Client, diaspora et entreprise suivent leurs demandes ; propriétaire et
     * prestataire n'ont pas cet écran et sont dirigés vers leur tableau de bord.
     */
    public static function requests(object $notifiable): string
    {
        // Dérivé de `base()` pour hériter du repli par rôle (F8.14) : un compte
        // entreprise sans profil doit atterrir sur SES demandes, pas sur celles
        // d'un espace client auquel son rôle interdit l'accès.
        $base = self::base($notifiable);

        return match ($base) {
            '/espace-proprietaire', '/espace-prestataire' => $base,
            default => $base.'/demandes',
        };
    }
}
