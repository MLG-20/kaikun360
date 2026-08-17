<?php

namespace Database\Factories;

use App\Models\NewsArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    protected $model = NewsArticle::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraphs(2, true),
            'image_path' => 'news/'.fake()->uuid().'.jpg',
            'video_path' => null,
            'video_url' => null,
            'is_published' => false,
            'position' => 0,
        ];
    }
}
