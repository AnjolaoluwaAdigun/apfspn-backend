<?php

namespace App\Http\Requests\Admin\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PaginationRules;

class ListArticleRevisionsRequest extends ApiFormRequest
{
    use PaginationRules;

    public function rules(): array
    {
        return $this->paginationRules();
    }
}
