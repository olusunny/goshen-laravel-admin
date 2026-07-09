<?php

use App\Http\Middleware\AuthorizeCloudBackup;

return [
    'route_prefix' => env('CLOUD_BACKUP_ROUTE_PREFIX', 'admin/cloud-backups'),
    'route_middleware' => ['web', 'auth', AuthorizeCloudBackup::class],

    'storage_disk' => env('CLOUD_BACKUP_STORAGE_DISK', 'local'),
    'staging_path' => env('CLOUD_BACKUP_STAGING_PATH', 'cloud-backups/staging'),

    'auto_schedule' => env('CLOUD_BACKUP_AUTO_SCHEDULE', true),
    'queue' => env('CLOUD_BACKUP_QUEUE', 'default'),
    'http_timeout' => env('CLOUD_BACKUP_HTTP_TIMEOUT', 120),

    'default_source_path' => base_path(),
    'exclude_paths' => [
        '.env',
        '.env.*',
        'auth.json',
        '*.pem',
        '*.key',
        '.git',
        '.github',
        'node_modules',
        'storage/app/cloud-backups',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        'storage/logs',
    ],

    'archive' => [
        'chunk_size' => 8 * 1024 * 1024,
        'skip_symlinks' => true,
        'cleanup_local_after_upload' => true,
    ],

    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
        'mysqldump_path' => env('CLOUD_BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'extra_options' => ['--single-transaction', '--quick', '--routines', '--triggers'],
    ],

    'oauth' => [
        'state_ttl_seconds' => 600,

        'google' => [
            'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI', env('APP_URL').'/admin/cloud-backups/oauth/google/callback'),
            'scopes' => [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/drive.file',
            ],
        ],

        'onedrive' => [
            'client_id' => env('ONEDRIVE_CLIENT_ID'),
            'client_secret' => env('ONEDRIVE_CLIENT_SECRET'),
            'tenant' => env('ONEDRIVE_TENANT', 'common'),
            'redirect_uri' => env('ONEDRIVE_REDIRECT_URI', env('APP_URL').'/admin/cloud-backups/oauth/onedrive/callback'),
            'scopes' => ['offline_access', 'Files.ReadWrite', 'User.Read'],
        ],
    ],
];
