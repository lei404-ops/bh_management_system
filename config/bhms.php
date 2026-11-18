<?php

/**
 * BHMS settings (replaces the old config/config.php of the plain-PHP version).
 * Read them with config('bhms.security.max_login_attempts'), etc.
 */
return [
    'security' => [
        'max_login_attempts' => 5,         // per email + IP
        'lockout_minutes'    => 15,
    ],
    'upload' => [
        'max_bytes' => 2 * 1024 * 1024,    // 2 MB
        'allowed'   => [                   // REAL mime type (finfo) => extension; the client file name is ignored
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ],
    ],
    'billing' => [
        'lease_alert_days' => 30,          // notify when a lease ends within N days
    ],
];
