<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use DomainException;
use InvalidArgumentException;

/**
 * Maintenance ticketing: create, list (scoped by role), assign, change status, audit log.
 * Business rules live here so every entry point (API, future UI) enforces the same thing.
 */
final class MaintenanceService
{
    public const CATEGORIES = ['plumbing', 'electrical', 'carpentry', 'appliance', 'internet', 'other'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const STATUSES   = ['pending', 'in_progress', 'resolved', 'cancelled'];

    /** current status => statuses it may move to */
    private const TRANSITIONS = [
        'pending'     => ['in_progress', 'cancelled'],
        'in_progress' => ['resolved', 'pending', 'cancelled'],
        'resolved'    => [],
        'cancelled'   => [],
    ];

    /** Tenant submits a ticket for the room they currently rent. */
    public static function create(int $tenantId, array $d, ?string $photo, int $userId): array
    {
        $roomId = Db::fetchValue(
            "SELECT room_id FROM leases WHERE tenant_id = ? AND status = 'active' ORDER BY start_date DESC LIMIT 1",
            [$tenantId]
        );
        if ($roomId === null) {
            throw new DomainException('You need an active lease before you can submit a maintenance request.');
        }

        return Db::transaction(function () use ($tenantId, $roomId, $d, $photo, $userId) {
            Db::execute(
                'INSERT INTO maintenance_requests (ticket_no, tenant_id, room_id, category, title, description, photo, priority)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                ['TMP' . bin2hex(random_bytes(6)), $tenantId, $roomId, $d['category'], $d['title'],
                 $d['description'], $photo, $d['priority']]
            );
            $id       = Db::lastInsertId();
            $ticketNo = 'MT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            Db::execute('UPDATE maintenance_requests SET ticket_no = ? WHERE id = ?', [$ticketNo, $id]);
            self::log($id, $userId, 'created', null, 'pending', 'Ticket submitted by tenant');

            NotificationService::sendToRoles(['admin', 'landlord'], 'ticket_new',
                "New {$d['priority']} ticket $ticketNo: {$d['title']}", 'maintenance');
            return ['id' => $id, 'ticket_no' => $ticketNo];
        });
    }

    /** List tickets visible to this user. Role scoping is enforced here, not in the browser. */
    public static function listFor(array $user, array $f = []): array
    {
        $where  = [];
        $params = [];

        switch ($user['role']) {
            case 'tenant':
                $where[]  = 'm.tenant_id = (SELECT id FROM tenants WHERE user_id = ?)';
                $params[] = $user['id'];
                break;
            case 'staff':
                $where[]  = 'm.assigned_to = ?';
                $params[] = $user['id'];
                break;
            default: // admin, landlord see everything
        }
        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $where[]  = 'm.status = ?';
            $params[] = $f['status'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return Db::fetchAll(
            "SELECT m.id, m.ticket_no, m.category, m.title, m.description, m.priority, m.status,
                    (m.photo IS NOT NULL) AS has_photo, m.created_at, m.resolved_at,
                    r.room_number, tu.full_name AS tenant_name,
                    m.assigned_to, su.full_name AS staff_name
             FROM maintenance_requests m
             JOIN rooms   r  ON r.id = m.room_id
             JOIN tenants t  ON t.id = m.tenant_id
             JOIN users   tu ON tu.id = t.user_id
             LEFT JOIN users su ON su.id = m.assigned_to
             $whereSql
             ORDER BY FIELD(m.status,'pending','in_progress','resolved','cancelled'),
                      FIELD(m.priority,'urgent','high','medium','low'), m.created_at DESC
             LIMIT 200",
            $params
        );
    }

    public static function find(int $id): ?array
    {
        return Db::fetchOne(
            'SELECT m.*, t.user_id AS tenant_user_id FROM maintenance_requests m
             JOIN tenants t ON t.id = m.tenant_id WHERE m.id = ?',
            [$id]
        );
    }

    /** Can this user see/open this ticket (and its photo)? */
    public static function canView(array $user, array $ticket): bool
    {
        return match ($user['role']) {
            'admin', 'landlord' => true,
            'tenant'            => (int) $ticket['tenant_user_id'] === (int) $user['id'],
            'staff'             => (int) $ticket['assigned_to'] === (int) $user['id'],
            default             => false,
        };
    }

    /** Change status, enforcing who may do what and which transitions are legal. */
    public static function changeStatus(int $id, string $newStatus, array $user, string $note = ''): void
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        $ticket = self::find($id);
        if (!$ticket || !self::canView($user, $ticket)) {
            throw new DomainException('Ticket not found.');
        }

        $old = $ticket['status'];
        if (!in_array($newStatus, self::TRANSITIONS[$old], true)) {
            throw new DomainException("A ticket that is '$old' cannot be changed to '$newStatus'.");
        }
        // Role-specific limits
        if ($user['role'] === 'tenant' && !($newStatus === 'cancelled' && $old === 'pending')) {
            throw new DomainException('Tenants can only cancel their own pending requests.');
        }
        if ($user['role'] === 'staff' && !in_array($newStatus, ['in_progress', 'resolved'], true)) {
            throw new DomainException('Staff can only start or resolve assigned tickets.');
        }

        Db::transaction(function () use ($id, $old, $newStatus, $user, $note, $ticket) {
            Db::execute(
                'UPDATE maintenance_requests
                 SET status = ?, resolved_at = ' . ($newStatus === 'resolved' ? 'NOW()' : 'NULL') . '
                 WHERE id = ?',
                [$newStatus, $id]
            );
            self::log($id, (int) $user['id'], 'status_changed', $old, $newStatus, $note ?: null);
            if ($user['role'] !== 'tenant') {
                NotificationService::send((int) $ticket['tenant_user_id'], 'ticket_update',
                    "Your ticket {$ticket['ticket_no']} is now " . str_replace('_', ' ', $newStatus) . '.', 'maintenance');
            }
        });
    }

    public static function assign(int $id, int $staffId, array $user): void
    {
        if (!in_array($user['role'], ['admin', 'landlord'], true)) {
            throw new DomainException('Only the admin or landlord can assign tickets.');
        }
        $staff = Db::fetchOne("SELECT id, full_name FROM users WHERE id = ? AND role = 'staff' AND is_active = 1", [$staffId]);
        $ticket = self::find($id);
        if (!$staff || !$ticket) {
            throw new DomainException('Ticket or staff member not found.');
        }
        if (in_array($ticket['status'], ['resolved', 'cancelled'], true)) {
            throw new DomainException('Closed tickets cannot be reassigned.');
        }

        Db::transaction(function () use ($id, $staff, $user, $ticket) {
            Db::execute('UPDATE maintenance_requests SET assigned_to = ? WHERE id = ?', [$staff['id'], $id]);
            self::log($id, (int) $user['id'], 'assigned', $ticket['status'], $ticket['status'], 'Assigned to ' . $staff['full_name']);
            NotificationService::send((int) $staff['id'], 'ticket_assigned', "You were assigned ticket {$ticket['ticket_no']}.", 'maintenance');
        });
    }

    public static function staffList(): array
    {
        return Db::fetchAll("SELECT id, full_name FROM users WHERE role = 'staff' AND is_active = 1 ORDER BY full_name");
    }

    public static function logFor(int $id): array
    {
        return Db::fetchAll(
            'SELECT l.action, l.from_status, l.to_status, l.note, l.logged_at, u.full_name AS by_name
             FROM maintenance_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE l.request_id = ? ORDER BY l.logged_at, l.id',
            [$id]
        );
    }

    private static function log(int $id, int $userId, string $action, ?string $from, ?string $to, ?string $note): void
    {
        Db::execute(
            'INSERT INTO maintenance_logs (request_id, user_id, action, from_status, to_status, note) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $userId, $action, $from, $to, $note]
        );
    }
}
