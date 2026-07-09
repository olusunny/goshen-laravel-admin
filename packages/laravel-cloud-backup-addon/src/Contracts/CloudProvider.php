<?php

namespace ChurchTools\CloudBackup\Contracts;

use ChurchTools\CloudBackup\Models\CloudBackupConnection;

interface CloudProvider
{
    public function providerKey(): string;

    public function authorizationUrl(string $state): string;

    /**
     * @return array{token: array<string, mixed>, account: array<string, mixed>}
     */
    public function exchangeCode(string $code): array;

    /**
     * @param array<string, mixed> $token
     * @return array<string, mixed>
     */
    public function refreshToken(array $token): array;

    public function ensureFolder(CloudBackupConnection $connection, string $folderPath): string;

    /**
     * @return array{id: string|null, path: string, size: int}
     */
    public function uploadFile(CloudBackupConnection $connection, string $localPath, string $remoteFilename, string $folderId): array;

    /**
     * @return array<int, array{id: string, name: string, created_at: string|null}>
     */
    public function listBackupFiles(CloudBackupConnection $connection, string $folderId, string $prefix): array;

    public function deleteFile(CloudBackupConnection $connection, string $fileId): void;
}
