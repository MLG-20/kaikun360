<?php

namespace App\Enums;

/**
 * Type d'un média (colonne `media.type`), couche transversale B12.
 *
 * Un média est soit une image (stockée sur le disque), soit une vidéo
 * (généralement une URL externe : YouTube, Vimeo…).
 */
enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::VIDEO => 'Vidéo',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
