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
     * @return array{path: string, size_bytes: int} chemin relatif + poids final
     */
    public function storeCompressed(UploadedFile $file, string $directory = 'media'): array
    {
        $image = $this->manager->decodePath($file->getRealPath());

        // On ne fait que RÉDUIRE : une image déjà plus petite n'est pas agrandie.
        $image->scaleDown(width: self::MAX_WIDTH);

        $encoded = $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($path, (string) $encoded);

        return [
            'path' => $path,
            'size_bytes' => strlen((string) $encoded),
        ];
    }
}
