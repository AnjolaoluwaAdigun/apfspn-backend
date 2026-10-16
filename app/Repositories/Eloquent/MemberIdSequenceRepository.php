<?php

namespace App\Repositories\Eloquent;

use App\Models\MemberIdSequence;
use App\Repositories\Contracts\MemberIdSequenceRepositoryInterface;
use Illuminate\Support\Str;

class MemberIdSequenceRepository implements MemberIdSequenceRepositoryInterface
{
    public function findForUpdate(string $stateCode): ?MemberIdSequence
    {
        return MemberIdSequence::where('state_code', $stateCode)->lockForUpdate()->first();
    }

    public function ensureExists(string $stateCode): void
    {
        MemberIdSequence::query()->insertOrIgnore([
            'uuid' => (string) Str::uuid7(),
            'state_code' => $stateCode,
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function increment(MemberIdSequence $sequence): int
    {
        $sequence->last_sequence++;
        $sequence->save();

        return $sequence->last_sequence;
    }
}
