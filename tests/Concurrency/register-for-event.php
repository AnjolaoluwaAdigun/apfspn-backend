<?php

use App\Models\User;
use App\Services\Events\EventRegistrationService;

[, $startSignal, $eventUuid, $memberUuid] = $argv;

$app = require __DIR__.'/bootstrap-worker.php';

try {
    $registration = $app->make(EventRegistrationService::class)->register(User::findOrFail($memberUuid), $eventUuid);

    echo json_encode([
        'ok' => true,
        'member_uuid' => $memberUuid,
        'registration_uuid' => $registration->uuid,
        'status' => $registration->status->value,
        'waitlist_position' => $registration->waitlist_position,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'member_uuid' => $memberUuid, 'error' => $e::class.': '.$e->getMessage()]);

    exit(1);
}
