<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant model: lets admin/landlord create a tenant login (users row) + profile (tenants row)
 * and, optionally, assign a room and open a lease in ONE transaction.
 */
final class TenantService
{
    /** Characters without look-alikes (no 0/O, 1/l/I) so passwords are easy to read out loud. */
    private const PW_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    public static function generatePassword(int $length = 10): string
    {
        $max = strlen(self::PW_ALPHABET) - 1;
        $pw  = '';
        for ($i = 0; $i < $length; $i++) {
            $pw .= self::PW_ALPHABET[random_int(0, $max)];
        }
        return $pw . random_int(10, 99);   // guarantees at least one digit
    }

    /** @return array{items:array,total:int,page:int,per_page:int} */
    public static function search(array $f = []): array
    {
        $where  = [];
        $params = [];
        if (!empty($f['q'])) {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR r.room_number LIKE ?)';
            $like    = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $perPage = max(1, min(50, (int) ($f['per_page'] ?? 10)));
        $page    = max(1, (int) ($f['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $from = "FROM tenants t
                 JOIN users u ON u.id = t.user_id
                 LEFT JOIN leases l ON l.id = (SELECT MAX(x.id) FROM leases x WHERE x.tenant_id = t.id AND x.status = 'active')
                 LEFT JOIN rooms  r ON r.id = l.room_id";

        $total = (int) Db::fetchValue("SELECT COUNT(*) $from $whereSql", $params);
        $items = Db::fetchAll(
            "SELECT t.id, u.id AS user_id, u.full_name, u.email, u.phone, u.is_active, u.last_login_at,
                    t.occupation, t.address, r.room_number, l.end_date
             $from $whereSql
             ORDER BY u.full_name
             LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** Rooms that can still take another tenant (not under maintenance, not full). */
    public static function assignableRooms(): array
    {
        return Db::fetchAll(
            "SELECT r.id, r.room_number, r.room_type, r.monthly_rate, r.capacity,
                    (SELECT COUNT(*) FROM leases l WHERE l.room_id = r.id AND l.status = 'active') AS occupants
             FROM rooms r
             WHERE r.status <> 'maintenance'
             HAVING occupants < r.capacity
             ORDER BY r.room_number"
        );
    }

    /**
     * Create the login + profile (+ lease when $d['room_id'] is set).
     * @return array{user_id:int,tenant_id:int,lease_id:?int}
     */
    public static function create(array $d, int $createdBy): array
    {
        return Db::transaction(function () use ($d, $createdBy) {
            Db::execute(
                "INSERT INTO users (full_name, email, password_hash, role, phone) VALUES (?, ?, ?, 'tenant', ?)",
                [$d['full_name'], $d['email'], Hash::make($d['password']), $d['phone'] ?: null]
            );
            $userId = Db::lastInsertId();

            Db::execute(
                'INSERT INTO tenants (user_id, birth_date, address, occupation, id_type,
                                      emergency_name, emergency_phone, emergency_relation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $d['birth_date'] ?: null, $d['address'] ?: null, $d['occupation'] ?: null, $d['id_type'] ?: null,
                    $d['emergency_name'] ?: null, $d['emergency_phone'] ?: null, $d['emergency_relation'] ?: null,
                ]
            );
            $tenantId = Db::lastInsertId();

            $leaseId = null;
            if (!empty($d['room_id'])) {
                // Lock the room row so two admins cannot fill the last bed at the same time.
                $room = Db::fetchOne(
                    'SELECT id, room_number, monthly_rate, capacity, status FROM rooms WHERE id = ? FOR UPDATE',
                    [(int) $d['room_id']]
                );
                if (!$room) {
                    throw new DomainException('Selected room does not exist.');
                }
                if ($room['status'] === 'maintenance') {
                    throw new DomainException("Room {$room['room_number']} is under maintenance.");
                }
                $occupants = (int) Db::fetchValue(
                    "SELECT COUNT(*) FROM leases WHERE room_id = ? AND status = 'active'", [$room['id']]
                );
                if ($occupants >= (int) $room['capacity']) {
                    throw new DomainException("Room {$room['room_number']} is already full.");
                }

                $deposit = (float) $d['security_deposit'];
                Db::execute(
                    "INSERT INTO leases (tenant_id, room_id, start_date, end_date, monthly_rent, security_deposit,
                                         deposit_status, billing_day, status, move_in_date, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)",
                    [
                        $tenantId, $room['id'], $d['start_date'], $d['end_date'], $room['monthly_rate'],
                        $deposit, ($deposit > 0 && !empty($d['deposit_received'])) ? 'held' : 'unpaid',
                        (int) $d['billing_day'], $d['start_date'], $createdBy,
                    ]
                );
                $leaseId = Db::lastInsertId();

                if ($occupants + 1 >= (int) $room['capacity']) {
                    Db::execute("UPDATE rooms SET status = 'occupied' WHERE id = ?", [$room['id']]);
                }
            }

            return ['user_id' => $userId, 'tenant_id' => $tenantId, 'lease_id' => $leaseId];
        });
    }

    /** Enable/disable a tenant login. Only touches users whose role is 'tenant'. */
    public static function setActive(int $userId, bool $active): bool
    {
        return Db::execute(
            "UPDATE users SET is_active = ? WHERE id = ? AND role = 'tenant'", [$active ? 1 : 0, $userId]
        ) > 0;
    }

    /** Give a tenant a new temporary password (returned once, never stored in plain text). */
    public static function resetPassword(int $userId): string
    {
        $pw = self::generatePassword();
        $ok = Db::execute(
            "UPDATE users SET password_hash = ? WHERE id = ? AND role = 'tenant'",
            [Hash::make($pw), $userId]
        );
        if ($ok === 0) {
            throw new DomainException('Tenant not found.');
        }
        Db::execute('DELETE FROM login_attempts WHERE email = (SELECT email FROM users WHERE id = ?)', [$userId]);
        return $pw;
    }
}
