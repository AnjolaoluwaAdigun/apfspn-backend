<?php

namespace App\Http\Requests\Admin\Members;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\MemberFilterRules;

class ExportMembersRequest extends ApiFormRequest
{
    use MemberFilterRules;

    public function rules(): array
    {
        return $this->filterRules();
    }
}
