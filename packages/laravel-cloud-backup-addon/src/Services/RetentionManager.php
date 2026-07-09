<?php

namespace ChurchTools\CloudBackup\Services;

use ChurchTools\CloudBackup\Contracts\CloudProvider;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;

class RetentionManager
{
    public function prune(CloudProvider $provider, CloudBackupConnection $connection, string $folderId, int $keep, string $prefix = 'backup_'): int
    {
        if ($keep < 1) {
            return 0;
        }

        $sets = collect($provider->listBackupFiles($connection, $folderId, $prefix))
            ->groupBy(fn (array $file): string => $this->backupSetKey($file['name']))
            ->sortByDesc(fn ($files, string $setKey): string => $setKey)
            ->values();

        $deleted = 0;

        foreach ($sets->slice($keep) as $files) {
            foreach ($files as $file) {
                $provider->deleteFile($connection, $file['id']);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function backupSetKey(string $filename): string
    {
        if (preg_match('/^(backup_\d{8}_\d{6}_run\d+_.+)_(files|database)\.(zip|sql)$/', $filename, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(backup_\d{8}_\d{6})_/', $filename, $matches)) {
            return $matches[1];
        }

        return $filename;
    }
}
