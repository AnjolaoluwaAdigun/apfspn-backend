<?php

namespace App\Http\Requests\Admin\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ArticleRules;

class StoreArticleRequest extends ApiFormRequest
{
    use ArticleRules;

    public function rules(): array
    {
        return $this->articleRules();
    }
}
