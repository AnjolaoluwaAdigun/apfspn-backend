<?php

namespace App\Http\Requests\Admin\Members;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\MembershipActionRules;

class RejectMemberRequest extends ApiFormRequest
{
    use MembershipActionRules;

    public function rules(): array
    {
        return array_merge($this->prohibitedMembershipRules(), $this->reasonRules());
    }
}
