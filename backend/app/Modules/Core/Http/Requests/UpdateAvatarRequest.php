<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du dépôt d'une photo de profil / d'un logo d'entreprise
 * (POST /api/v1/users/me/avatar).
 *
 * Plus strict que le dépôt de pièce justificative, et pour deux raisons :
 *   - c'est une IMAGE et rien d'autre (`image` en plus de `mimes`, pour que le
 *     contenu réel soit vérifié et pas seulement l'extension) ;
 *   - elle est stockée sur le disque PUBLIC : un PDF ou un SVG déposé là serait
 *     servi tel quel par le serveur web. D'où l'absence volontaire de `svg`,
 *     qui peut embarquer du script.
 */
class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                // 2 Mo (valeur en kilo-octets) : largement assez pour un
                // avatar, et ça évite qu'un original d'appareil photo de 12 Mo
                // parte à chaque affichage de page.
                'max:2048',
                // Garde-fou sur les extrêmes : une image de 20 px est
                // illisible, une de 6 000 px n'apporte rien à un avatar.
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Aucune image n’a été envoyée.',
            'avatar.image' => 'Le fichier doit être une image.',
            'avatar.mimes' => 'L’image doit être au format JPG, PNG ou WEBP.',
            'avatar.max' => 'L’image ne doit pas dépasser 2 Mo.',
            'avatar.dimensions' => 'L’image doit faire au moins 100 × 100 pixels (et au plus 4000 × 4000).',
        ];
    }
}
