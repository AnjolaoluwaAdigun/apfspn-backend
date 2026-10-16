<?php

namespace App\Policies;

use App\Enums\ePermission;
use App\Models\Admin;
use App\Models\Article;

class ArticlePolicy
{
    public function update(Admin $admin, Article $article): bool
    {
        if (! $admin->hasPermissionTo(ePermission::ArticlesUpdate->value)) {
            return false;
        }

        if ($admin->hasPermissionTo(ePermission::ArticlesPublish->value)) {
            return true;
        }

        return $article->isAuthoredBy($admin) && $article->status->isEditableByAuthor();
    }

    public function submit(Admin $admin, Article $article): bool
    {
        if (! $admin->hasPermissionTo(ePermission::ArticlesSubmit->value)) {
            return false;
        }

        return $admin->hasPermissionTo(ePermission::ArticlesPublish->value) || $article->isAuthoredBy($admin);
    }
}
