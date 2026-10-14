<?php

namespace App\Console\Commands;

use App\Services\Auth\ChallengeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneAuthChallenges extends Command
{
    protected $signature = 'auth:prune-challenges {--dry-run : Report how many challenges would be deleted without deleting them}';

    protected $description = 'Delete auth challenges that expired or were used more than a day ago';

    public function handle(ChallengeService $challenges): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $challenges->prune($dryRun);
        $message = $dryRun ? "Would delete {$count} auth challenges." : "Deleted {$count} auth challenges.";

        $this->info($message);
        Log::info('auth:prune-challenges', ['dry_run' => $dryRun, 'count' => $count]);

        return self::SUCCESS;
    }
}
