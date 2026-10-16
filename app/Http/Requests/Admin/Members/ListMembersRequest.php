<?php

namespace App\Http\Requests\Admin\Members;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\MemberFilterRules;

class ListMembersRequest extends ApiFormRequest
{
    use MemberFilterRules;

    public function rules(): array
    {
        return array_merge($this->filterRules(), $this->paginationRules());
    }
}
