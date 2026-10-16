<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:prune-challenges')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('events:complete-past')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
