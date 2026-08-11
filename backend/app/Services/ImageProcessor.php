<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Traitement et stockage des images (couche transversale, B12.1).
 *
 * Redimensionne (borne la largeur), recompresse en JPEG puis stocke sur le
 * disque public. La logique est isolée ici pour pouvoir, le jour où le volume
 * l'exige, la déplacer telle quelle dans un Job de queue (B16) sans toucher aux
 * contrôleurs.
 */
class ImageProcessor
{
    /** Largeur maximale conservée (les images plus larges sont réduites). */
    public const MAX_WIDTH = 1600;

    /** Qualité JPEG de sortie (compromis poids/qualité). */
    public const JPEG_QUALITY = 80;

    /**
     * Largeur maximale d'une image de FOND plein écran (bandeaux F12).
     *
     * ⚠️ Ne pas confondre avec `MAX_WIDTH`. Une photo d'annonce est vue dans une
     * vignette ou une galerie de 600 à 900 px : 1600 px lui laissent déjà de la
     * marge. Un bandeau, lui, est étiré sur TOUTE la largeur de l'écran — sur un
     * moniteur courant (1920 px, et 2560 px sur les grands), une image de
     * 1600 px est agrandie par le navigateur, et un agrandissement ne peut que
     * flouter. D'où une borne franchement plus haute, doublée d'une compression
     * plus douce : les artefacts JPEG, invisibles sur une vignette, deviennent
     * du grain bien visible une fois l'image affichée en 2 m de large.
     */
    public const BACKGROUND_MAX_WIDTH = 2560;

    /** Qualité JPEG des images de fond (voir `BACKGROUND_MAX_WIDTH`). */
    public const BACKGROUND_JPEG_QUALITY = 88;

    private ImageManager $manager;

    public function __construct()
    {
        // Pilote GD (extension présente sur la machine ; Imagick non requis).
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Compresse une image téléversée et la stocke sur le disque `public`.
     *
     * @param  string  $directory  dossier de destination (ex. "media")
     * @param  int|null  $maxWidth  borne de largeur ; par défaut celle des photos d'annonce
     * @param  int|null  $quality  qualité JPEG ; par défaut celle des photos d'annonce
     * @return array{path: string, size_bytes: int} chemin relatif + poids final
     */
    public function storeCompressed(
        UploadedFile $file,
        string $directory = 'media',
        ?int $maxWidth = null,
        ?int $quality = null,
    ): array {
        $image = $this->manager->decodePath($file->getRealPath());

        // On ne fait que RÉDUIRE : une image déjà plus petite n'est pas agrandie.
        // Conséquence à connaître côté back-office : déposer une photo de
        // 1200 px pour un bandeau donnera un bandeau flou, et aucun réglage ici
        // n'y changera rien — c'est pourquoi l'écran annonce la taille attendue.
        $image->scaleDown(width: $maxWidth ?? self::MAX_WIDTH);

        $encoded = $image->encode(new JpegEncoder(quality: $quality ?? self::JPEG_QUALITY));

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($path, (string) $encoded);

        return [
            'path' => $path,
            'size_bytes' => strlen((string) $encoded),
        ];
    }
}
