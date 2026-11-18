<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;

/** Dashboard metrics and report queries. One method per role-specific dashboard. */
final class DashboardService
{
    /** Admin / landlord overview. */
    public static function overview(): array
    {
        BillingService::markOverdue();

        $rooms     = RoomService::counts();
        $totalRooms = array_sum($rooms);
        $occupancy = $totalRooms > 0 ? round($rooms['occupied'] / $totalRooms * 100, 1) : 0.0;

        $unpaid = Db::fetchOne(
            "SELECT COUNT(*) AS bills, COALESCE(SUM(total_amount - amount_paid),0) AS amount
             FROM invoices WHERE status IN ('pending','partial','overdue')"
        );

        return [
            'total_revenue'      => (float) Db::fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'confirmed'"),
            'revenue_this_month' => (float) Db::fetchValue(
                "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'confirmed'
                 AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'occupancy_rate'     => $occupancy,
            'rooms'              => $rooms,
            'pending_tickets'    => (int) Db::fetchValue("SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('pending','in_progress')"),
            'unpaid_bills'       => (int) $unpaid['bills'],
            'unpaid_amount'      => (float) $unpaid['amount'],
            'overdue_bills'      => (int) Db::fetchValue("SELECT COUNT(*) FROM invoices WHERE status = 'overdue'"),
            'pending_payments'   => (int) Db::fetchValue("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
            'expiring_leases'    => Db::fetchAll(
                "SELECT l.id, l.end_date, r.room_number, u.full_name AS tenant_name,
                        DATEDIFF(l.end_date, CURDATE()) AS days_left
                 FROM leases l JOIN rooms r ON r.id = l.room_id
                 JOIN tenants t ON t.id = l.tenant_id JOIN users u ON u.id = t.user_id
                 WHERE l.status = 'active' AND l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                 ORDER BY l.end_date", [(int) config('bhms.billing.lease_alert_days', 30)]),
            'monthly_revenue'    => self::monthlyRevenue(6),
            'recent_tickets'     => Db::fetchAll(
                "SELECT m.ticket_no, m.title, m.priority, m.status, r.room_number
                 FROM maintenance_requests m JOIN rooms r ON r.id = m.room_id
                 ORDER BY m.created_at DESC LIMIT 5"),
        ];
    }

    /** Last N months, zero-filled so charts have no gaps. @return array<int,array{month:string,revenue:float}> */
    public static function monthlyRevenue(int $months = 6): array
    {
        $rows = Db::fetchAll(
            "SELECT month_key, revenue FROM v_monthly_revenue
             WHERE month_key >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m')", [$months - 1]);
        $map = array_column($rows, 'revenue', 'month_key');

        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key   = date('Y-m', strtotime("first day of -$i month"));
            $out[] = ['month' => date('M Y', strtotime($key . '-01')), 'revenue' => (float) ($map[$key] ?? 0)];
        }
        return $out;
    }

    /** Tenant's own summary. */
    public static function forTenant(int $userId): array
    {
        BillingService::markOverdue();
        $lease = Db::fetchOne(
            "SELECT l.id, l.start_date, l.end_date, l.monthly_rent, l.security_deposit, l.deposit_status,
                    r.room_number, r.room_type, DATEDIFF(l.end_date, CURDATE()) AS days_left
             FROM leases l JOIN tenants t ON t.id = l.tenant_id JOIN rooms r ON r.id = l.room_id
             WHERE t.user_id = ? AND l.status = 'active' ORDER BY l.start_date DESC LIMIT 1", [$userId]);

        $balance = (float) Db::fetchValue(
            "SELECT COALESCE(SUM(i.total_amount - i.amount_paid),0) FROM invoices i
             JOIN leases l ON l.id = i.lease_id JOIN tenants t ON t.id = l.tenant_id
             WHERE t.user_id = ? AND i.status IN ('pending','partial','overdue')", [$userId]);

        return [
            'lease'        => $lease,
            'balance'      => $balance,
            'open_tickets' => (int) Db::fetchValue(
                "SELECT COUNT(*) FROM maintenance_requests m JOIN tenants t ON t.id = m.tenant_id
                 WHERE t.user_id = ? AND m.status IN ('pending','in_progress')", [$userId]),
            'invoices'     => array_slice(PaymentService::invoicesFor(['id' => $userId, 'role' => 'tenant']), 0, 6),
        ];
    }

    /** Maintenance staff summary. */
    public static function forStaff(int $userId): array
    {
        $rows = Db::fetchAll(
            'SELECT status, COUNT(*) AS c FROM maintenance_requests WHERE assigned_to = ? GROUP BY status', [$userId]);
        $out = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'cancelled' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** Report: financial summary for a date range (inclusive). */
    public static function financialSummary(string $from, string $to): array
    {
        return [
            'billed'    => (float) Db::fetchValue('SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE billing_month BETWEEN ? AND ?', [$from, $to]),
            'collected' => (float) Db::fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'confirmed' AND DATE(paid_at) BETWEEN ? AND ?", [$from, $to]),
            'by_method' => Db::fetchAll("SELECT method, SUM(amount) AS total FROM payments WHERE status = 'confirmed' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY method", [$from, $to]),
        ];
    }

    /** Report: who occupies which room (occupancy log). */
    public static function occupancyLog(): array
    {
        return Db::fetchAll(
            "SELECT r.room_number, r.room_type, u.full_name AS tenant_name, l.start_date, l.end_date, l.move_in_date, l.move_out_date, l.status
             FROM leases l JOIN rooms r ON r.id = l.room_id JOIN tenants t ON t.id = l.tenant_id JOIN users u ON u.id = t.user_id
             ORDER BY r.room_number, l.start_date DESC");
    }
}
