<?php

namespace App\Http\Requests\Public\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ArticleRules;
use App\Http\Requests\Concerns\PaginationRules;

class ListPublicArticlesRequest extends ApiFormRequest
{
    use ArticleRules, PaginationRules;

    public function rules(): array
    {
        return array_merge($this->publicArticleFilterRules(), $this->paginationRules());
    }
}
