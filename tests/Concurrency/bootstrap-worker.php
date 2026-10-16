<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

touch($startSignal.'.ready.'.getmypid());

$deadline = microtime(true) + 30;

while (! file_exists($startSignal) && microtime(true) < $deadline) {
    usleep(500);
}

return $app;
