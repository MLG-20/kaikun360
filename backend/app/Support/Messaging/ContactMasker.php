<?php

namespace App\Support\Messaging;

/**
 * Masquage des coordonnées écrites dans un message (F8.12.c).
 *
 * Quand l'agent fait entrer un propriétaire ou un prestataire dans un fil, les
 * deux parties se parlent pour la première fois. Le premier réflexe est
 * d'échanger un numéro et de poursuivre sur WhatsApp — hors de la plateforme,
 * où il n'y a plus ni paiement sécurisé, ni caution, ni recours, ni trace en cas
 * de litige. On masque donc **les coordonnées, pas les personnes** : le prénom
 * et le rôle restent visibles, le numéro et l'e-mail deviennent « ••• ».
 *
 * ⚠️ **Limite assumée, à ne pas oublier en la relisant** : ceci réduit la
 * friction, cela ne VERROUILLE rien. « zéro sept huit… », un numéro sur une
 * photo, ou simplement « appelez-moi, on s'arrangera » passeront toujours. La
 * vraie protection contre la désintermédiation est **contractuelle et
 * pratique** (paiement sécurisé, recours, caution, trace écrite), pas technique.
 * Ce filtre sert à ce que le geste ne soit pas *machinal* — pas à l'empêcher.
 *
 * ⚠️ **L'équipe voit le texte entier** (cf. `MessageResource`) : sans cela, un
 * agent ne pourrait ni comprendre un litige (« il m'a demandé de l'appeler
 * au… ») ni sanctionner une désintermédiation manifeste. Le masquage ne
 * s'applique qu'entre non-staff.
 */
final class ContactMasker
{
    /** Ce qu'on met à la place. Assez visible pour que la coupe soit comprise. */
    private const REMPLACEMENT = '•••';

    /**
     * Masque les e-mails et les numéros de téléphone d'un texte libre.
     */
    public static function mask(?string $texte): ?string
    {
        if ($texte === null || $texte === '') {
            return $texte;
        }

        // 1) Adresses e-mail.
        $texte = preg_replace(
            '/[\p{L}0-9._%+-]+@[\p{L}0-9.-]+\.[\p{L}]{2,}/u',
            self::REMPLACEMENT,
            $texte,
        );

        // 2) Numéros de téléphone. On vise large plutôt que juste : un numéro
        //    sénégalais s'écrit « 77 123 45 67 », « 771234567 », « +221 77… »,
        //    parfois avec des points ou des tirets. La règle retenue : au moins
        //    **7 chiffres** au total dans une suite de chiffres, espaces,
        //    points, tirets ou parenthèses, éventuellement précédée d'un `+`.
        //    ⚠️ Sept chiffres, pas six : un prix (« 250 000 »), une date ou une
        //    référence de dossier ne doivent pas être hachés — un message
        //    illisible ferait plus de dégâts qu'un numéro qui passe.
        $texte = preg_replace_callback(
            '/\+?[0-9][0-9\s.\-()]{5,}[0-9]/',
            static function (array $trouve): string {
                $chiffres = preg_replace('/\D/', '', $trouve[0]);

                return strlen((string) $chiffres) >= 7 ? self::REMPLACEMENT : $trouve[0];
            },
            (string) $texte,
        );

        return $texte;
    }
}
