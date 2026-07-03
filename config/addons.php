<?php

return [
    'enabled' => env('ADDONS_ENABLED', true),

    'install_path' => env('ADDONS_INSTALL_PATH', 'addons'),

    'runtime_cache_path' => env('ADDONS_RUNTIME_CACHE_PATH', storage_path('app/addons/active-addons.json')),

    'storage' => [
        'disk' => env('ADDONS_STORAGE_DISK', 'local'),
        'uploads_path' => env('ADDONS_UPLOADS_PATH', 'addons/uploads'),
        'staging_path' => env('ADDONS_STAGING_PATH', 'addons/staging'),
        'backups_path' => env('ADDONS_BACKUPS_PATH', 'addons/backups'),
    ],

    'zip' => [
        'max_size_kb' => (int) env('ADDONS_MAX_ZIP_SIZE_KB', 51200),
        'max_files' => (int) env('ADDONS_MAX_ZIP_FILES', 1000),
        'max_entry_size_kb' => (int) env('ADDONS_MAX_ZIP_ENTRY_SIZE_KB', 10240),
        'max_uncompressed_size_kb' => (int) env('ADDONS_MAX_ZIP_UNCOMPRESSED_SIZE_KB', 102400),
        'max_compression_ratio' => (float) env('ADDONS_MAX_ZIP_COMPRESSION_RATIO', 100),
        'allowed_extensions' => ['zip'],
        'nested_archive_extensions' => ['zip', 'phar', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
        'allowed_mimes' => [
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'multipart/x-zip',
        ],
    ],

    'signatures' => [
        'required' => filter_var(env('ADDONS_REQUIRE_SIGNATURES', '1'), FILTER_VALIDATE_BOOLEAN),
        'signature_entry' => env('ADDONS_SIGNATURE_ENTRY', 'addon.sig'),
        'trusted_checksums' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADDONS_TRUSTED_CHECKSUMS', ''))))),
        'public_key_paths' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADDONS_PUBLIC_KEY_PATHS', ''))))),
        'public_keys' => [],
    ],

    'compatibility' => [
        'minimum_php' => '8.2',
        'minimum_laravel' => '10.0',
    ],

    'artisan_allowlist' => [
        'migrate',
        'db:seed',
        'vendor:publish',
        'optimize:clear',
        'config:clear',
        'route:clear',
        'view:clear',
        'cache:clear',
    ],
];
