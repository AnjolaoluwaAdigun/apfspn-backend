<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleRevision>
 */
class ArticleRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_uuid' => Article::factory(),
            'title' => fake()->sentence(6),
            'excerpt' => fake()->sentence(15),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
