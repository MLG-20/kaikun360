<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle Media.
 *
 * Cible par défaut un véhicule (léger à instancier) ; les tests peuvent
 * surcharger `mediable_type`/`mediable_id` pour n'importe quelle ressource.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'MED-'.Str::upper(Str::random(8)),
            'mediable_type' => Vehicle::class,
            'mediable_id' => Vehicle::factory(),
            'uploaded_by' => null,
            'type' => MediaType::IMAGE->value,
            'disk' => 'public',
            'path' => 'media/'.fake()->uuid().'.jpg',
            'url' => null,
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(20_000, 2_000_000),
            'is_primary' => false,
            'position' => 0,
            'status' => MediaStatus::ACTIF->value,
        ];
    }

    /**
     * Image de une (principale).
     */
    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    /**
     * Vidéo externe (URL) plutôt qu'un fichier stocké.
     */
    public function video(): static
    {
        return $this->state(fn () => [
            'type' => MediaType::VIDEO->value,
            'disk' => 'public',
            'path' => null,
            'url' => 'https://www.youtube.com/watch?v='.Str::random(11),
            'original_name' => null,
            'mime_type' => null,
        ]);
    }

    /**
     * Média masqué par la modération.
     */
    public function hidden(): static
    {
        return $this->state(fn () => ['status' => MediaStatus::MASQUE->value]);
    }
}
