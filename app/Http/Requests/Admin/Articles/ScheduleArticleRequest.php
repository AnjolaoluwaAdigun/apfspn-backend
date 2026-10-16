<?php

namespace App\Http\Requests\Admin\Articles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ArticleRules;

class ScheduleArticleRequest extends ApiFormRequest
{
    use ArticleRules;

    public function rules(): array
    {
        return $this->workflowRules(['publish_at' => ['required', 'date', 'after:now']]);
    }
}
