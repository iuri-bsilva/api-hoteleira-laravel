<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hotels:import')->hourly()->withoutOverlapping()->appendOutputTo(storage_path('logs/import.log'));
