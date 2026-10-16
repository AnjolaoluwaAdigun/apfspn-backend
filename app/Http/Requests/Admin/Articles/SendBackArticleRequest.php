<?php

namespace App\Http\Requests\Admin\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ArticleRules;

class SendBackArticleRequest extends ApiFormRequest
{
    use ArticleRules;

    public function rules(): array
    {
        return $this->workflowRules(['review_note' => ['required', 'string', 'min:3', 'max:2000']]);
    }
}
