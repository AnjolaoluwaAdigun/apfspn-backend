<?php

namespace Database\Factories;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Article;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'excerpt' => fake()->sentence(15),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'category' => ArticleCategory::News,
            'tags' => ['hospitality'],
            'status' => ArticleStatus::Draft,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['status' => ArticleStatus::InReview]);
    }

    public function changesRequested(): static
    {
        return $this->state(fn () => ['status' => ArticleStatus::ChangesRequested, 'review_note' => 'Please add a source.']);
    }

    public function scheduled(?DateTimeInterface $at = null): static
    {
        return $this->state(fn () => ['status' => ArticleStatus::Scheduled, 'publish_at' => $at ?? now()->addDay()]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ArticleStatus::Published, 'published_at' => now()->subHour()]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ArticleStatus::Archived,
            'published_at' => now()->subWeek(),
            'archived_at' => now()->subDay(),
        ]);
    }
}
