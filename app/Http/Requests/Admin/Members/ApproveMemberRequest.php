<?php

namespace App\Http\Requests\Admin\Members;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\MembershipActionRules;

class ApproveMemberRequest extends ApiFormRequest
{
    use MembershipActionRules;

    public function rules(): array
    {
        return $this->prohibitedMembershipRules();
    }
}
