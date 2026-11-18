<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use DomainException;
use InvalidArgumentException;

/**
 * Billing engine:
 *  - utility rates + sub-meter readings (water, electricity; internet is a flat monthly fee)
 *  - monthly invoice generation (rent + utilities on one statement)
 *  - overdue marking and lease-expiry notifications (called by cron/monthly_billing.php)
 */
final class BillingService
{
    public static function rateFor(string $type, string $date): ?array
    {
        return Db::fetchOne(
            'SELECT rate_per_unit, unit_label FROM utility_rates
             WHERE utility_type = ? AND effective_from <= ? ORDER BY effective_from DESC LIMIT 1',
            [$type, $date]
        );
    }

    /**
     * Save a sub-meter reading and compute its cost from the configured unit rate.
     * $month is any date inside the billing month; it is normalised to the 1st.
     */
    public static function recordReading(int $roomId, string $type, string $month, float $current, int $userId): array
    {
        if (!in_array($type, ['water', 'electricity'], true)) {
            throw new InvalidArgumentException('Meter readings are for water or electricity only.');
        }
        $monthStart = date('Y-m-01', strtotime($month) ?: time());

        $previous = Db::fetchValue(
            'SELECT current_reading FROM meter_readings
             WHERE room_id = ? AND utility_type = ? AND billing_month < ?
             ORDER BY billing_month DESC LIMIT 1',
            [$roomId, $type, $monthStart]
        );
        $previous = $previous === null ? 0.0 : (float) $previous;

        if ($current < $previous) {
            throw new InvalidArgumentException("Current reading ($current) cannot be lower than the previous reading ($previous).");
        }
        $rate = self::rateFor($type, $monthStart);
        if (!$rate) {
            throw new DomainException("No $type rate is configured yet.");
        }

        $rateValue = (float) $rate['rate_per_unit'];
        $amount    = round(($current - $previous) * $rateValue, 2);

        Db::execute(
            'INSERT INTO meter_readings (room_id, utility_type, billing_month, previous_reading, current_reading, rate_used, amount, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE previous_reading = VALUES(previous_reading), current_reading = VALUES(current_reading),
                                     rate_used = VALUES(rate_used), amount = VALUES(amount), recorded_by = VALUES(recorded_by)',
            [$roomId, $type, $monthStart, $previous, $current, $rateValue, $amount, $userId]
        );
        return ['previous' => $previous, 'current' => $current, 'consumption' => $current - $previous, 'amount' => $amount];
    }

    /**
     * Create one invoice per active lease for the billing month (idempotent: the UNIQUE key
     * on (lease_id, billing_month) means running it twice never double-bills).
     * Utility costs are split equally between active leases sharing a room (dormitories).
     * @return int number of invoices created
     */
    public static function generateMonthly(string $month): int
    {
        $monthStart = date('Y-m-01', strtotime($month) ?: time());
        $ym         = date('Ym', strtotime($monthStart));
        $created    = 0;

        $leases = Db::fetchAll(
            "SELECT l.id, l.room_id, l.monthly_rent, l.billing_day, t.user_id,
                    (SELECT COUNT(*) FROM leases x WHERE x.room_id = l.room_id AND x.status = 'active') AS sharers
             FROM leases l JOIN tenants t ON t.id = l.tenant_id
             WHERE l.status = 'active' AND l.start_date <= LAST_DAY(?) AND l.end_date >= ?",
            [$monthStart, $monthStart]
        );
        $internet = self::rateFor('internet', $monthStart);

        foreach ($leases as $lease) {
            $exists = Db::fetchValue('SELECT id FROM invoices WHERE lease_id = ? AND billing_month = ?', [$lease['id'], $monthStart]);
            if ($exists) {
                continue;
            }
            $label  = date('M Y', strtotime($monthStart));
            $share  = max(1, (int) $lease['sharers']);
            $items  = [['rent', "Room rent - $label", (float) $lease['monthly_rent']]];

            if ($internet) {
                $items[] = ['internet', "Internet - $label", round((float) $internet['rate_per_unit'] / $share, 2)];
            }
            $readings = Db::fetchAll(
                'SELECT utility_type, consumption, amount FROM meter_readings WHERE room_id = ? AND billing_month = ?',
                [$lease['room_id'], $monthStart]
            );
            foreach ($readings as $r) {
                $unit    = $r['utility_type'] === 'water' ? 'm3' : 'kWh';
                $items[] = [$r['utility_type'], ucfirst($r['utility_type']) . " - $label ({$r['consumption']} $unit)",
                            round((float) $r['amount'] / $share, 2)];
            }
            $total   = array_sum(array_column($items, 2));
            $lastDay = (int) date('t', strtotime($monthStart));
            $due     = date('Y-m-', strtotime($monthStart)) . str_pad((string) min((int) $lease['billing_day'], $lastDay), 2, '0', STR_PAD_LEFT);

            Db::transaction(function () use ($lease, $monthStart, $ym, $due, $total, $items) {
                Db::execute(
                    'INSERT INTO invoices (invoice_no, lease_id, billing_month, due_date, total_amount) VALUES (?, ?, ?, ?, ?)',
                    ['TMP' . bin2hex(random_bytes(6)), $lease['id'], $monthStart, $due, $total]
                );
                $id = Db::lastInsertId();
                Db::execute('UPDATE invoices SET invoice_no = ? WHERE id = ?',
                    ["INV-$ym-" . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
                foreach ($items as [$type, $desc, $amt]) {
                    Db::execute('INSERT INTO invoice_items (invoice_id, item_type, description, amount) VALUES (?, ?, ?, ?)',
                        [$id, $type, $desc, $amt]);
                }
                NotificationService::send((int) $lease['user_id'], 'invoice_new', 'Your statement for ' . date('F Y', strtotime($monthStart)) . ' is ready.', 'dashboard');
            });
            $created++;
        }
        return $created;
    }

    /** Flip unpaid invoices past their due date to 'overdue'. */
    public static function markOverdue(): int
    {
        return Db::execute(
            "UPDATE invoices SET status = 'overdue'
             WHERE status IN ('pending','partial') AND due_date < CURDATE()"
        );
    }

    /** Notify landlord/admin (and the tenant) about leases ending soon; expire finished leases. */
    public static function notifyExpiringLeases(): int
    {
        $days = (int) config('bhms.billing.lease_alert_days', 30);

        $soon = Db::fetchAll(
            "SELECT l.id, l.end_date, r.room_number, tu.id AS tenant_user_id, tu.full_name
             FROM leases l
             JOIN rooms r ON r.id = l.room_id
             JOIN tenants t ON t.id = l.tenant_id
             JOIN users tu ON tu.id = t.user_id
             WHERE l.status = 'active' AND l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)",
            [$days]
        );
        foreach ($soon as $l) {
            // Avoid spamming: one alert per lease per day.
            $msg = "Lease for room {$l['room_number']} ({$l['full_name']}) ends on {$l['end_date']}.";
            $dup = Db::fetchValue("SELECT id FROM notifications WHERE type = 'lease_expiring' AND message = ? AND DATE(created_at) = CURDATE() LIMIT 1", [$msg]);
            if (!$dup) {
                NotificationService::sendToRoles(['admin', 'landlord'], 'lease_expiring', $msg, 'rooms');
                NotificationService::send((int) $l['tenant_user_id'], 'lease_expiring', "Your lease ends on {$l['end_date']}. Please contact the landlord to renew.", 'dashboard');
            }
        }
        Db::execute("UPDATE leases SET status = 'expired' WHERE status = 'active' AND end_date < CURDATE()");
        return count($soon);
    }
}
