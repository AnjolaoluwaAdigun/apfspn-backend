<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:prune-challenges')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('events:complete-past')->hourly()->withoutOverlapping()->onOneServer();
