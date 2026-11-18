<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * BHMS user. Differences from a default Laravel user:
 *  - the name column is `full_name`
 *  - the password column is `password_hash` (see getAuthPassword)
 *  - roles: admin, landlord, tenant, staff
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Laravel's auth guard reads the password from this method. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** Plain array used by the services (same shape the old Auth::user() returned). */
    public function asArray(): array
    {
        return [
            'id'        => (int) $this->id,
            'full_name' => $this->full_name,
            'email'     => $this->email,
            'role'      => $this->role,
            'phone'     => $this->phone,
            'is_active' => (int) $this->is_active,
        ];
    }
}
