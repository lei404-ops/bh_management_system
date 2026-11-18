<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;

/** In-app notifications (shown as a bell/list in the UI; extend later with email/SMS). */
final class NotificationService
{
    public static function send(int $userId, string $type, string $message, ?string $link = null): void
    {
        Db::execute(
            'INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)',
            [$userId, $type, mb_substr($message, 0, 255), $link]
        );
    }

    /** Notify every active user that has one of the given roles. */
    public static function sendToRoles(array $roles, string $type, string $message, ?string $link = null): void
    {
        if ($roles === []) {
            return;
        }
        $in = implode(',', array_fill(0, count($roles), '?'));
        $ids = Db::fetchAll("SELECT id FROM users WHERE is_active = 1 AND role IN ($in)", array_values($roles));
        foreach ($ids as $row) {
            self::send((int) $row['id'], $type, $message, $link);
        }
    }

    public static function forUser(int $userId, int $limit = 8): array
    {
        return Db::fetchAll(
            'SELECT id, type, message, link, is_read, created_at FROM notifications
             WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }
}
