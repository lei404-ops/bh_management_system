<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;

/** Room model: listing with filters/pagination, creation, status changes. */
final class RoomService
{
    public const TYPES    = ['single', 'double', 'dormitory'];
    public const STATUSES = ['available', 'occupied', 'maintenance'];

    /**
     * @param array{type?:string,status?:string,q?:string,page?:int,per_page?:int} $f
     * @return array{items:array,total:int,page:int,per_page:int}
     */
    public static function search(array $f = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($f['type']) && in_array($f['type'], self::TYPES, true)) {
            $where[]  = 'r.room_type = ?';
            $params[] = $f['type'];
        }
        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $where[]  = 'r.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['q'])) {
            $where[]  = '(r.room_number LIKE ? OR r.description LIKE ?)';
            $like     = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $perPage = max(1, min(50, (int) ($f['per_page'] ?? 10)));
        $page    = max(1, (int) ($f['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Db::fetchValue("SELECT COUNT(*) FROM rooms r $whereSql", $params);

        // LIMIT/OFFSET are cast to int above, so interpolating them is safe.
        $items = Db::fetchAll(
            "SELECT r.id, r.room_number, r.room_type, r.monthly_rate, r.capacity, r.status,
                    r.floor_no, r.description,
                    COUNT(l.id) AS occupant_count,
                    GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ') AS occupants
             FROM rooms r
             LEFT JOIN leases  l ON l.room_id = r.id AND l.status = 'active'
             LEFT JOIN tenants t ON t.id = l.tenant_id
             LEFT JOIN users   u ON u.id = t.user_id
             $whereSql
             GROUP BY r.id
             ORDER BY r.room_number
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function find(int $id): ?array
    {
        return Db::fetchOne('SELECT * FROM rooms WHERE id = ?', [$id]);
    }

    public static function create(array $d): int
    {
        Db::execute(
            'INSERT INTO rooms (room_number, room_type, monthly_rate, capacity, floor_no, description)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$d['room_number'], $d['room_type'], $d['monthly_rate'], $d['capacity'], $d['floor_no'], $d['description'] ?: null]
        );
        return Db::lastInsertId();
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        return Db::execute('UPDATE rooms SET status = ? WHERE id = ?', [$status, $id]) > 0;
    }

    public static function counts(): array
    {
        $rows = Db::fetchAll('SELECT status, COUNT(*) AS c FROM rooms GROUP BY status');
        $out  = ['available' => 0, 'occupied' => 0, 'maintenance' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }
}
