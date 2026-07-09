# Laravel Cloud Backup Addon

Installable Laravel addon for scheduled website and MySQL database backups to Google Drive or Microsoft OneDrive.

This package intentionally does not include UpdraftPlus licensing, update checks, hosted OAuth broker URLs, shop links, or external validation callbacks.

## Install

```bash
composer require church-tools/laravel-cloud-backup-addon
php artisan cloud-backup:install
php artisan migrate
```

Set OAuth credentials in the host app:

```env
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=https://your-domain.test/admin/cloud-backups/oauth/google/callback

ONEDRIVE_CLIENT_ID=
ONEDRIVE_CLIENT_SECRET=
ONEDRIVE_REDIRECT_URI=https://your-domain.test/admin/cloud-backups/oauth/onedrive/callback
```

The default admin route is:

```text
/admin/cloud-backups
```

The route is protected by `web` and `auth` middleware by default. Change `route_prefix` or `route_middleware` in `config/cloud-backup.php` to fit your existing admin.

## Scheduler

The service provider registers `cloud-backup:run-due` every minute when `CLOUD_BACKUP_AUTO_SCHEDULE=true`.

For older Laravel apps or custom schedulers, disable auto scheduling and add:

```php
$schedule->command('cloud-backup:run-due')->everyMinute()->withoutOverlapping();
```

Run workers for queued backups:

```bash
php artisan queue:work --queue=default --timeout=7200
```

## OAuth Apps

Google Drive:

- Redirect URI: `/admin/cloud-backups/oauth/google/callback`
- Scopes: `openid`, `email`, `profile`, `https://www.googleapis.com/auth/drive.file`

Microsoft OneDrive:

- Redirect URI: `/admin/cloud-backups/oauth/onedrive/callback`
- Scopes: `offline_access`, `Files.ReadWrite`, `User.Read`

## Security Notes

- OAuth tokens are encrypted using Laravel `Crypt` and the host app key.
- Local backup artifacts are deleted after upload by default.
- Backup routes should stay behind trusted admin middleware.
- Database dumps use `mysqldump` with `MYSQL_PWD` environment injection instead of logging passwords in command arguments.
- Do not expose the backup admin to public, non-admin users.

## Current Scope

Included:

- One-click Google Drive and OneDrive connection
- Folder creation
- Scheduled queued backup jobs
- Website file ZIP archive
- MySQL database dump
- Resumable/chunked cloud upload
- Basic retention pruning

Not included:

- WordPress restore/migration
- Licensing/update validation
- UpdraftPlus hosted auth broker
- Non-MySQL database dump engines
