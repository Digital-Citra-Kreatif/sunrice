<?php

declare(strict_types=1);

return [

    // The role whose users may do everything (Gate::before). Created by
    // `php artisan sunrice:sync-permissions`; give it with `sunrice:make-admin`.
    'super_admin_role' => 'super-admin',

    'auth' => [
        // The password broker for "Forgot password?" (config/auth.php
        // passwords.*); null for the app's default broker.
        'passwords' => null,

        // Brute-force protection for the panel's login. Wrong passwords are
        // counted three ways; the first limit reached makes the person wait.
        'throttle' => [
            // The same email from the same IP.
            'attempts' => 5,
            'decay_seconds' => 60,
            // One IP trying any emails (password spraying).
            'per_ip' => 20,
            'per_ip_decay_seconds' => 60,
            // One account from any IPs (a botnet): it is locked for a while.
            'per_account' => 30,
            'account_lock_seconds' => 900,
            // Forgot / reset password requests per IP per minute.
            'password_resets_per_minute' => 5,
        ],
    ],

];
