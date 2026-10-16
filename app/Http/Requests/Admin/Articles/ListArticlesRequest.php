<?php

namespace App\Http\Requests\Admin\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ArticleRules;
use App\Http\Requests\Concerns\PaginationRules;

class ListArticlesRequest extends ApiFormRequest
{
    use ArticleRules, PaginationRules;

    public function rules(): array
    {
        return array_merge($this->articleFilterRules(), $this->paginationRules());
    }
}
