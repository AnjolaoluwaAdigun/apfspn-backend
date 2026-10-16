<?php

namespace App\Console\Commands;

use App\Services\Articles\ArticleWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledArticles extends Command
{
    protected $signature = 'articles:publish-scheduled {--dry-run : Report how many articles would be published without changing them}';

    protected $description = 'Publish scheduled articles whose publish time has passed';

    public function handle(ArticleWorkflowService $workflow): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $workflow->publishDue($dryRun);
        $message = $dryRun ? "Would publish {$count} articles." : "Published {$count} articles.";

        $this->info($message);
        Log::info('articles:publish-scheduled', ['dry_run' => $dryRun, 'count' => $count]);

        return self::SUCCESS;
    }
}
