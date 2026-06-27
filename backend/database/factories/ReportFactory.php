<?php

namespace Database\Factories;

use App\Models\Report;
use App\Modules\Build\Enums\ReportType;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory du modèle transversal Report.
 *
 * Par défaut, le rapport cible une demande de construction ; on peut viser une
 * autre entité polymorphe via `->for($model, 'reportable')`.
 *
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RPT-'.Str::upper(Str::random(8)),
            'reportable_type' => ConstructionRequest::class,
            'reportable_id' => ConstructionRequest::factory(),
            'type' => ReportType::PHOTO->value,
            'photos' => ['suivi/photo1.jpg', 'suivi/photo2.jpg'],
            'comment' => fake()->sentence(10),
            'reported_at' => now()->toDateString(),
        ];
    }

    /**
     * Rapport vidéo.
     */
    public function video(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::VIDEO->value,
            'photos' => null,
            'video_url' => 'https://videos.kaikun360.test/'.Str::random(10),
        ]);
    }
}
