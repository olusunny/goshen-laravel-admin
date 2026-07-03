<?php

namespace ChurchTools\CloudBackup\Tests\Unit;

use ChurchTools\CloudBackup\Contracts\CloudProvider;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Services\RetentionManager;
use PHPUnit\Framework\TestCase;

class RetentionManagerTest extends TestCase
{
    public function test_it_prunes_whole_backup_sets(): void
    {
        $provider = new class implements CloudProvider {
            public array $deleted = [];

            public function providerKey(): string { return 'fake'; }
            public function authorizationUrl(string $state): string { return ''; }
            public function exchangeCode(string $code): array { return []; }
            public function refreshToken(array $token): array { return []; }
            public function ensureFolder(CloudBackupConnection $connection, string $folderPath): string { return 'folder'; }
            public function uploadFile(CloudBackupConnection $connection, string $localPath, string $remoteFilename, string $folderId): array { return []; }
            public function deleteFile(CloudBackupConnection $connection, string $fileId): void { $this->deleted[] = $fileId; }

            public function listBackupFiles(CloudBackupConnection $connection, string $folderId, string $prefix): array
            {
                return [
                    ['id' => '1a', 'name' => 'backup_20260103_010000_site_files.zip', 'created_at' => '2026-01-03T01:00:00Z'],
                    ['id' => '1b', 'name' => 'backup_20260103_010000_site_database.sql', 'created_at' => '2026-01-03T01:00:01Z'],
                    ['id' => '2a', 'name' => 'backup_20260102_010000_site_files.zip', 'created_at' => '2026-01-02T01:00:00Z'],
                    ['id' => '2b', 'name' => 'backup_20260102_010000_site_database.sql', 'created_at' => '2026-01-02T01:00:01Z'],
                    ['id' => '3a', 'name' => 'backup_20260101_010000_site_files.zip', 'created_at' => '2026-01-01T01:00:00Z'],
                    ['id' => '3b', 'name' => 'backup_20260101_010000_site_database.sql', 'created_at' => '2026-01-01T01:00:01Z'],
                ];
            }
        };

        $deleted = (new RetentionManager())->prune($provider, new CloudBackupConnection(), 'folder', 2);

        $this->assertSame(2, $deleted);
        $this->assertSame(['3a', '3b'], $provider->deleted);
    }
}
