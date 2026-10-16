<?php

namespace Tests\Feature\Articles;

use App\Enums\ePermission;
use App\Models\Article;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class ArticlePermissionsTest extends TestCase
{
    use InteractsWithArticles, InteractsWithAuth, RefreshDatabase;

    private const ENDPOINTS = [
        'list' => ['GET', '', ePermission::ArticlesView],
        'show' => ['GET', '{article}', ePermission::ArticlesView],
        'revisions' => ['GET', '{article}/revisions', ePermission::ArticlesView],
        'create' => ['POST', '', ePermission::ArticlesCreate],
        'update' => ['PUT', '{article}', ePermission::ArticlesUpdate],
        'restore' => ['POST', '{article}/revisions/{revision}/restore', ePermission::ArticlesUpdate],
        'delete' => ['DELETE', '{article}', ePermission::ArticlesDelete],
        'submit' => ['POST', '{article}/submit', ePermission::ArticlesSubmit],
        'publish' => ['POST', '{article}/publish', ePermission::ArticlesPublish],
        'schedule' => ['POST', '{article}/schedule', ePermission::ArticlesPublish],
        'unschedule' => ['POST', '{article}/unschedule', ePermission::ArticlesPublish],
        'send back' => ['POST', '{article}/send-back', ePermission::ArticlesPublish],
        'archive' => ['POST', '{article}/archive', ePermission::ArticlesArchive],
        'unarchive' => ['POST', '{article}/unarchive', ePermission::ArticlesArchive],
    ];

    public static function forbiddenCases(): array
    {
        $cases = [];

        foreach (RolesAndPermissionsSeeder::roleMap() as $role => $permissions) {
            foreach (self::ENDPOINTS as $name => [$method, $path, $permission]) {
                if (! in_array($permission, $permissions, true)) {
                    $cases[$role.' cannot '.$name] = [$role, $method, $path];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('forbiddenCases')]
    public function test_roles_without_the_permission_get_403(string $role, string $method, string $path): void
    {
        Queue::fake();
        $admin = $this->adminWithRole($role);
        $article = Article::factory()->create(['author_admin_uuid' => $admin->uuid]);
        $revision = $article->revisions()->create(['title' => $article->title, 'body' => $article->body]);
        $url = '/api/v1/admin/articles/'.str_replace(['{article}', '{revision}'], [$article->uuid, $revision->uuid], $path);

        $this->api($method, rtrim($url, '/'), [], $this->tokenFor($admin))
            ->assertForbidden()
            ->assertJsonPath('error', true)
            ->assertJsonPath('message', 'You are not allowed to perform this action.');
    }

    public function test_guests_are_refused(): void
    {
        $this->api('GET', '/api/v1/admin/articles')->assertUnauthorized();
    }
}
