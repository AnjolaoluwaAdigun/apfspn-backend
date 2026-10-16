<?php

use App\Services\Membership\MemberApprovalService;

[, $startSignal, $memberUuid] = $argv;

$app = require __DIR__.'/bootstrap-worker.php';

try {
    $membership = $app->make(MemberApprovalService::class)->approve($memberUuid);

    echo json_encode(['ok' => true, 'member_uuid' => $memberUuid, 'member_id' => $membership->member_id]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'member_uuid' => $memberUuid, 'error' => $e::class.': '.$e->getMessage()]);

    exit(1);
}
