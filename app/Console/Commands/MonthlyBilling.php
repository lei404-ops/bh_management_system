<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Replaces cron/monthly_billing.php of the plain-PHP version.
 *   php artisan bhms:monthly-billing            (current month)
 *   php artisan bhms:monthly-billing 2026-11    (specific month)
 * Scheduled daily in routes/console.php. It is idempotent:
 *   1. generates the month's invoices (rent + utilities) once per lease
 *   2. marks past-due invoices as overdue
 *   3. sends lease-expiry alerts and expires finished leases
 */
class MonthlyBilling extends Command
{
    protected $signature = 'bhms:monthly-billing {month? : Billing month as YYYY-MM (default: current month)}';

    protected $description = 'Generate monthly invoices, mark overdue invoices, send lease-expiry alerts';

    public function handle(): int
    {
        $month = (string) ($this->argument('month') ?? date('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month . '-01' : date('Y-m-01');

        $created = BillingService::generateMonthly($month);
        $overdue = BillingService::markOverdue();
        $alerts  = BillingService::notifyExpiringLeases();

        $this->info("Invoices created: $created | marked overdue: $overdue | lease alerts: $alerts");
        return self::SUCCESS;
    }
}
