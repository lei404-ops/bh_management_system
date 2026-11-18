<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Db;
use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Login checks, brute-force lockout and password change.
 * (The session itself - login, logout, idle timeout - is handled by Laravel's auth guard.)
 */
final class AuthService
{
    /** A valid bcrypt hash used for unknown emails, so response time does not reveal whether an email exists. */
    private const DUMMY_HASH = '$2y$10$iKc02F1XQqcNkNivEB35Xe..YIiFbyZOow4LsqzsSK1YYZ1U.vDvq';

    /**
     * Check credentials. Returns [?User, string message].
     * Generic error text on purpose: never reveal whether the email exists.
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email   = mb_strtolower(trim($email));
        $generic = [null, 'Invalid email or password.'];

        if (self::isLockedOut($email, $ip)) {
            return [null, 'Too many failed attempts. Please try again in '
                . (int) config('bhms.security.lockout_minutes') . ' minutes.'];
        }

        $user = User::query()->where('email', $email)->first();

        // Always run the hash check (even for unknown emails) to keep timing similar.
        $valid = Hash::check($password, $user->password_hash ?? self::DUMMY_HASH);

        if (!$user || !$valid || !(int) $user->is_active) {
            Db::execute('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)', [$email, $ip]);
            return $generic;
        }

        // Upgrade the hash transparently if the algorithm/cost changed.
        if (Hash::needsRehash($user->password_hash)) {
            Db::execute('UPDATE users SET password_hash = ? WHERE id = ?', [Hash::make($password), $user->id]);
        }

        Db::execute('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?', [$email, $ip]);
        Db::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user->id]);

        return [$user, 'OK'];
    }

    /**
     * Change the logged-in user's own password.
     * Throws DomainException with a user-safe message on any problem.
     */
    public static function changePassword(int $userId, string $current, string $new, string $ip): void
    {
        $row = Db::fetchOne('SELECT email, password_hash FROM users WHERE id = ? AND is_active = 1', [$userId]);
        if (!$row) {
            throw new DomainException('Account not found.');
        }
        $email = mb_strtolower($row['email']);

        // Same brute-force limit as the login form (guessing the current password is just as dangerous).
        if (self::isLockedOut($email, $ip)) {
            throw new DomainException('Too many failed attempts. Please try again in '
                . (int) config('bhms.security.lockout_minutes') . ' minutes.');
        }
        if (!Hash::check($current, $row['password_hash'])) {
            Db::execute('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)', [$email, $ip]);
            throw new DomainException('Your current password is incorrect.');
        }
        if (hash_equals($current, $new)) {
            throw new DomainException('Your new password must be different from the current one.');
        }

        Db::execute('UPDATE users SET password_hash = ? WHERE id = ?', [Hash::make($new), $userId]);
        Db::execute('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?', [$email, $ip]);
    }

    private static function isLockedOut(string $email, string $ip): bool
    {
        $count = (int) Db::fetchValue(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip_address = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
            [$email, $ip, (int) config('bhms.security.lockout_minutes')]
        );
        return $count >= (int) config('bhms.security.max_login_attempts');
    }

    /** The tenants.id row for a tenant user (null for other roles / no profile). */
    public static function tenantIdFor(int $userId): ?int
    {
        $id = Db::fetchValue('SELECT id FROM tenants WHERE user_id = ?', [$userId]);
        return $id === null ? null : (int) $id;
    }
}
