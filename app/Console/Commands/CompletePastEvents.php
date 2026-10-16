<?php

namespace App\Console\Commands;

use App\Services\Events\EventCompletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompletePastEvents extends Command
{
    protected $signature = 'events:complete-past {--dry-run : Report how many events would be completed without changing them}';

    protected $description = 'Mark published events that have ended as completed';

    public function handle(EventCompletionService $completion): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $completion->completePast($dryRun);
        $message = $dryRun ? "Would complete {$count} events." : "Completed {$count} events.";

        $this->info($message);
        Log::info('events:complete-past', ['dry_run' => $dryRun, 'count' => $count]);

        return self::SUCCESS;
    }
}
