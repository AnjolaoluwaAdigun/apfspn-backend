<?php

use App\Services\Membership\MemberApprovalService;
use Illuminate\Contracts\Console\Kernel;

[, $memberUuid, $startSignal] = $argv;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

touch($startSignal.'.ready.'.getmypid());

$deadline = microtime(true) + 30;

while (! file_exists($startSignal) && microtime(true) < $deadline) {
    usleep(500);
}

try {
    $membership = $app->make(MemberApprovalService::class)->approve($memberUuid);

    echo json_encode(['ok' => true, 'member_uuid' => $memberUuid, 'member_id' => $membership->member_id]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'member_uuid' => $memberUuid, 'error' => $e::class.': '.$e->getMessage()]);

    exit(1);
}
