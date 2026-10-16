<?php

namespace App\Http\Requests\Admin\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ArticleRules;

class ArchiveArticleRequest extends ApiFormRequest
{
    use ArticleRules;

    public function rules(): array
    {
        return $this->workflowRules(['archived_at' => ['prohibited']]);
    }
}
