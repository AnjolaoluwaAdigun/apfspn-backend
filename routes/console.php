<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:prune-challenges')->daily()->withoutOverlapping()->onOneServer();
