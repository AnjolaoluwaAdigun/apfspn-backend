<?php

namespace Tests\Feature\AdminMembers;

use App\Models\MemberIdSequence;
use App\Models\Membership;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concurrency\RunsConcurrentWorkers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

#[Group('concurrency')]
class MemberIdConcurrencyTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, RunsConcurrentWorkers;

    private const WORKER = 'tests/Concurrency/approve-member.php';

    public function test_concurrent_approvals_issue_unique_sequential_member_ids(): void
    {
        try {
            $workerEnv = $this->prepareConcurrencyDatabase();

            $lagos = array_map(fn () => $this->pendingMember(['state_code' => 'LA'])->uuid, range(1, 4));
            $kano = array_map(fn () => $this->pendingMember(['state_code' => 'KN'])->uuid, range(1, 2));
            MemberIdSequence::where('state_code', 'KN')->delete();

            $uuids = [...$lagos, ...$kano, $lagos[0], $lagos[0]];
            $results = $this->runWorkers(self::WORKER, array_map(fn (string $uuid) => [$uuid], $uuids), $workerEnv);

            foreach ($results as $result) {
                $this->assertTrue($result['ok'] ?? false, 'Worker failed: '.json_encode($result));
            }

            $issued = Membership::whereNotNull('member_id')->pluck('member_id', 'user_uuid');

            $lagosIds = array_map(fn (string $uuid) => $issued[$uuid], $lagos);
            sort($lagosIds);
            $kanoIds = array_map(fn (string $uuid) => $issued[$uuid], $kano);
            sort($kanoIds);

            $this->assertSame(['APFSPNLA001', 'APFSPNLA002', 'APFSPNLA003', 'APFSPNLA004'], $lagosIds);
            $this->assertSame(['APFSPNKN001', 'APFSPNKN002'], $kanoIds);
            $this->assertCount(6, $issued->unique());

            foreach (array_filter($results, fn (array $result) => $result['member_uuid'] === $lagos[0]) as $result) {
                $this->assertSame($issued[$lagos[0]], $result['member_id']);
            }

            $this->assertSame(4, MemberIdSequence::where('state_code', 'LA')->value('last_sequence'));
            $this->assertSame(2, MemberIdSequence::where('state_code', 'KN')->value('last_sequence'));
        } finally {
            $this->cleanUpWorkers();
        }
    }
}
