<?php

use Illuminate\Support\Facades\Schedule;

// Invoices, overdue marking and lease alerts. Run `php artisan schedule:work` (dev) or add the
// Laravel scheduler to Task Scheduler / cron. The command is idempotent, so running it daily is safe.
Schedule::command('bhms:monthly-billing')->dailyAt('01:00');
