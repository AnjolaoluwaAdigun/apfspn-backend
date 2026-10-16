<?php

namespace App\Services\Membership;

use App\Exceptions\ApiException;
use App\Repositories\Contracts\MemberIdSequenceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class MemberIdGenerator
{
    private const PAD_LENGTH = 3;

    public function __construct(private readonly MemberIdSequenceRepositoryInterface $sequences) {}

    public function next(string $stateCode): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Member IDs must be generated inside a database transaction.');
        }

        if (! array_key_exists($stateCode, config('apfspn.states'))) {
            throw new ApiException('The membership state is not recognised.', 422, ['code' => 'invalid_state_code']);
        }

        $sequence = $this->sequences->findForUpdate($stateCode);

        if (! $sequence) {
            $this->sequences->ensureExists($stateCode);
            $sequence = $this->sequences->findForUpdate($stateCode);
        }

        $number = $this->sequences->increment($sequence);

        return config('apfspn.member_id_prefix').$stateCode.str_pad((string) $number, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }
}
