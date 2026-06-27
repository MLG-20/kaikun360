<?php

namespace App\Modules\Build\Enums;

/**
 * Type d'un rapport de suivi de chantier (colonne `reports.type`).
 */
enum ReportType: string
{
    case PHOTO = 'photo';
    case VIDEO = 'video';
    case MIXTE = 'mixte';

    public function label(): string
    {
        return match ($this) {
            self::PHOTO => 'Photos',
            self::VIDEO => 'Vidéo',
            self::MIXTE => 'Photos et vidéo',
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
