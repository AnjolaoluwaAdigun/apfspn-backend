<?php

namespace Tests\Feature\Articles;

use App\Models\Admin;
use App\Models\Article;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithArticles
{
    private array $adminTokenCache = [];

    protected function adminWithRole(string $role): Admin
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(Admin::factory()->create())->assignRole($role);
    }

    protected function tokenFor(Admin $admin): string
    {
        return $this->adminTokenCache[$admin->uuid] ??= $this->adminTokens($admin)['access_token'];
    }

    protected function articleUrl(Article $article, string $suffix = ''): string
    {
        return '/api/v1/admin/articles/'.$article->uuid.($suffix !== '' ? '/'.$suffix : '');
    }

    protected function articlePayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'APFSPN Welcomes New Members in Lagos',
            'excerpt' => 'A warm welcome to the newest members of the association.',
            'body' => '<h2>Welcome</h2><p>The association welcomed <strong>twenty</strong> new members this month.</p>',
            'category' => 'news',
            'tags' => ['Membership', ' lagos ', 'membership'],
        ];
    }
}
