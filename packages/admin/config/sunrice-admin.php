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

    'media' => [
        // Where uploads are stored: a Laravel disk (config/filesystems.php)
        // and a folder in it. Files are stored as {directory}/{Y}/{m}/{random}-{name}.{ext}.
        'disk' => 'public',
        'directory' => 'media',

        // The largest upload in kilobytes (PHP's upload_max_filesize and
        // post_max_size cap it too).
        'max_upload_kb' => 20480,

        // File types that may be uploaded. Scripts, executables and HTML are
        // always refused, whatever this list says.
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico',
            'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf',
            'zip', 'mp4', 'webm', 'mov', 'm4v', 'mp3', 'wav', 'ogg', 'm4a', 'woff', 'woff2', 'json',
        ],

        // Smaller copies made of every image: name => [width, height, mode].
        // 'crop' fills the box (cropping the middle), 'fit' scales down to fit
        // (null leaves that side free). Run `php artisan sunrice:media-sizes`
        // after changing them.
        'image_sizes' => [
            'thumbnail' => [300, 300, 'crop'],
            'medium' => [800, null, 'fit'],
            'large' => [1600, null, 'fit'],
        ],

        // Make the sizes on the queue instead of during the upload.
        'queue' => false,

        // Images larger than this (width x height) get no sizes: decoding
        // them could run out of memory.
        'max_pixels' => 40_000_000,
    ],

    'activity' => [
        // Record who changed what in the panel (and security events).
        'enabled' => true,
        // Delete lines older than this many days (daily, with the scheduler);
        // null keeps them forever.
        'keep_days' => 365,
    ],

    'security' => [
        // Also write security events (logins, failed and blocked logins, blocked
        // uploads …) to this log channel, e.g. 'daily', for fail2ban or a log
        // service. null: only the activity log.
        'log_channel' => env('SUNRICE_SECURITY_LOG_CHANNEL'),
    ],

];
