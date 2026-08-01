<?php

namespace App\Support\Mail;

use App\Modules\Core\Enums\ProfileType;

/**
 * Résout le bon lien d'espace privé selon le PROFIL du destinataire.
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
     * Le repli sur `/mon-espace` couvre les cas limites (profil non chargé,
     * compte sans profil) : mieux vaut le tableau de bord client, qui existe
     * toujours, qu'un lien cassé.
     */
    public static function base(object $notifiable): string
    {
        $type = $notifiable->profile?->type ?? null;

        return match ($type) {
            ProfileType::PROPRIETAIRE => '/espace-proprietaire',
            ProfileType::PRESTATAIRE => '/espace-prestataire',
            ProfileType::ENTREPRISE => '/espace-entreprise',
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
        $type = $notifiable->profile?->type ?? null;

        return match ($type) {
            ProfileType::ENTREPRISE => '/espace-entreprise/demandes',
            ProfileType::PROPRIETAIRE, ProfileType::PRESTATAIRE => self::base($notifiable),
            default => '/mon-espace/demandes',
        };
    }
}
