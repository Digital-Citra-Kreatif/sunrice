<?php

declare(strict_types=1);

return [

    // The panel's languages (its own words, not the website's content
    // languages): each user picks one under their name in the sidebar.
    // The words are Laravel JSON translations (lang/{code}.json in each
    // package; your app's lang/{code}.json overrides and adds to them).
    'locales' => ['en' => 'English', 'id' => 'Bahasa Indonesia'],

    // The panel's language for those who haven't picked one (and on the
    // sign-in page, until someone does on this browser).
    'locale' => env('SUNRICE_ADMIN_LOCALE', 'en'),

    // The role whose users may do everything (Gate::before). Created by
    // `php artisan sunrice:sync-permissions`; give it with `sunrice:make-admin`.
    'super_admin_role' => 'super-admin',

    // The panel's typeface: Instrument Sans, built into the panel's assets.
    // `stylesheet` is loaded on every admin page (null loads none); `family`
    // names the font the panel uses instead (the CSS variable --sunrice-font).
    // Set both to use another web font, or family alone for a system font.
    'font' => [
        'stylesheet' => null,
        'family' => null,
    ],

    'auth' => [
        // The password broker for "Forgot password?" (config/auth.php
        // passwords.*); null for the app's default broker.
        'passwords' => null,

        // Two-factor login (a code emailed after the password) is switched on
        // in Settings → Security. true or false here forces it, whatever the
        // setting says; null follows the setting.
        'two_factor' => null,

        // Put the login code in the email's subject too ("Your … login code: 123456"),
        // handy but readable on locked phones' notifications. Off: only in the body.
        'two_factor_code_in_subject' => env('SUNRICE_LOGIN_CODE_IN_SUBJECT', false),

        // Strong passwords (Settings → Security, on by default): every password
        // the panel sets needs password_min characters (never fewer than 8),
        // upper and lower case letters, a number and a symbol, and mustn't be
        // the person's email or name. true or false here forces it, whatever
        // the setting says; null follows the setting. Off, the panel uses your
        // app's Password::defaults().
        'strong_passwords' => null,
        'password_min' => 12,
        // Also refuse passwords found in known data leaks (Have I Been Pwned,
        // by k-anonymity: only 5 characters of a hash leave the server). When
        // the service can't be reached, passwords are accepted (a warning is logged).
        'password_uncompromised' => env('SUNRICE_PASSWORD_UNCOMPROMISED', true),

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
            // Forgot / reset password requests per IP per minute, and per hour.
            'password_resets_per_minute' => 5,
            'password_resets_per_hour' => 20,
        ],
    ],

    'media' => [
        // Where uploads are stored: a Laravel disk (config/filesystems.php)
        // and a folder in it. Files are stored as {directory}/{Y}/{m}/{random}-{name}.{ext}.
        'disk' => 'public',
        'directory' => 'media',

        // Where files in the trash are kept, off the public disk, so their
        // links stop working until they're restored: a private disk and a
        // folder in it ({trash_directory}/{the file's path}). The default
        // `local` disk (storage/app/private) is never served by URL.
        'trash_disk' => 'local',
        'trash_directory' => 'sunrice-media-trash',

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

        // Make the sizes on the queue instead of during the upload: true,
        // false, or null to queue them whenever the default queue connection
        // isn't sync (a file keeps only its original until the job ran).
        'queue' => null,

        // Images larger than this (width x height) get no sizes: decoding
        // them could run out of memory.
        'max_pixels' => 40_000_000,

        // Defaults of `php artisan sunrice:optimize-images` (each one has an
        // option too): images larger than max_width x max_height are scaled
        // down to fit, and JPEG, WebP and AVIF are compressed at quality.
        // The first original of each is kept on backup_disk (null: the
        // file's own disk) under backup_directory; `--restore` puts it back.
        'optimize' => [
            'max_width' => 2560,
            'max_height' => 2560,
            'quality' => 82,
            'backup' => true,
            'backup_disk' => 'local',
            'backup_directory' => 'sunrice-originals',
        ],
    ],

    'locks' => [
        // The cache store edit locks live in (config/cache.php); null for the
        // default. Every server must share it.
        'store' => null,
    ],

    'activity' => [
        // Record who changed what in the panel (and security events).
        'enabled' => true,
        // Delete lines older than this many days (daily, with the scheduler);
        // null keeps them forever.
        'keep_days' => 365,
    ],

    // A captcha for public forms (Cloudflare Turnstile, Google reCAPTCHA v2
    // or hCaptcha), which plugins ask for with Security\Captcha. Off until
    // both keys are set.
    'captcha' => [
        'provider' => env('SUNRICE_CAPTCHA_PROVIDER', 'turnstile'),
        'site_key' => env('SUNRICE_CAPTCHA_SITE_KEY'),
        'secret_key' => env('SUNRICE_CAPTCHA_SECRET_KEY'),
    ],

    'security' => [
        // Headers on every panel response. X-Frame-Options: DENY, Referrer-Policy:
        // same-origin and X-Content-Type-Options: nosniff are always sent; a
        // Content-Security-Policy only when set here (Inertia and Vite setups
        // vary, see packages/admin/docs/security.md#security-headers).
        'headers' => [
            'csp' => env('SUNRICE_CSP'),
        ],

        // Also write security events (logins, failed and blocked logins, blocked
        // uploads …) to this log channel, e.g. 'daily', for fail2ban or a log
        // service. null: only the activity log.
        'log_channel' => env('SUNRICE_SECURITY_LOG_CHANNEL'),

        // Emails when something may be a break-in (see packages/admin/docs/security.md).
        // Sent to super admins, users with these roles, and these addresses.
        'alerts' => [
            'enabled' => env('SUNRICE_SECURITY_ALERTS', true),
            'roles' => [],
            'emails' => [],
            // The same alert (rule and account) at most this often.
            'cooldown_minutes' => 60,
            // A login after this many days without one.
            'dormant_days' => 90,
            // CSV exports by one person in an hour.
            'exports' => 5,
            // Deletes, trashes and unpublishes by one person (or AI agent) in 10 minutes.
            'mass_deletes' => 20,
            // Pages refused (403) to one signed-in account in 10 minutes; also
            // how many "page refused" events are recorded per account in that time.
            'forbidden' => 15,
        ],
    ],

    'ai' => [
        // The MCP endpoint AI agents (Claude, ChatGPT, Cursor …) connect to with
        // an access token from the panel's AI access page.
        'enabled' => true,
        'path' => 'mcp',
        // Requests per minute per token.
        'requests_per_minute' => 120,
        // The user an agent on the server itself acts as, over stdio
        // (`php artisan mcp:start sunrice`), by email. Unset: refused.
        'local_user' => env('SUNRICE_AI_USER'),
        // read_error_log: recent errors from the Laravel log, secrets masked
        // (with the "sunrice.ai.error-log" permission). The logs' directory:
        // null for storage/logs.
        'error_log' => env('SUNRICE_AI_ERROR_LOG', true),
        'log_path' => env('SUNRICE_AI_LOG_PATH'),
        // run_command: the artisan commands agents may run (with the
        // "sunrice.ai.run-commands" permission); anything else is refused. A
        // name takes any of the command's own options; `name => [options]`
        // only those ("--days", "--force").
        'commands' => [
            'sunrice:activity-prune',
            'sunrice:sync-permissions',
            'sunrice:media-sizes',
            'sunrice:optimize-images',
            'cache:clear',
            'view:clear',
            'config:clear',
            'route:clear',
            'optimize:clear',
        ],
    ],

];
