<?php

namespace ChurchTools\CloudBackup\Services\Cloud;

use ChurchTools\CloudBackup\Contracts\CloudProvider;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Models\CloudBackupOAuthSetting;
use ChurchTools\CloudBackup\Services\Cloud\Concerns\ManagesTokens;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GoogleDriveProvider implements CloudProvider
{
    use ManagesTokens;

    public function __construct(private readonly Client $http)
    {
    }

    public function providerKey(): string
    {
        return 'google';
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->config('scopes')),
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'false',
            'prompt' => 'select_account consent',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        $response = $this->postForm('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        $token = $this->normalizeToken($response);

        return [
            'token' => $token,
            'account' => $this->accountFromToken($token),
        ];
    }

    public function refreshToken(array $token): array
    {
        if (empty($token['refresh_token'])) {
            throw new \RuntimeException('Google Drive refresh token is missing.');
        }

        $response = $this->postForm('https://oauth2.googleapis.com/token', [
            'refresh_token' => $token['refresh_token'],
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'grant_type' => 'refresh_token',
        ]);

        return $this->normalizeToken($response, $token['refresh_token']);
    }

    public function ensureFolder(CloudBackupConnection $connection, string $folderPath): string
    {
        $parentId = 'root';
        $segments = $this->safeSegments($folderPath);

        foreach ($segments as $segment) {
            $existing = $this->findFolder($connection, $parentId, $segment);
            $parentId = $existing ?: $this->createFolder($connection, $parentId, $segment);
        }

        return $parentId;
    }

    public function uploadFile(CloudBackupConnection $connection, string $localPath, string $remoteFilename, string $folderId): array
    {
        $size = filesize($localPath);
        if ($size === false) {
            throw new \RuntimeException("Could not read file size for {$remoteFilename}.");
        }

        $metadata = [
            'name' => $remoteFilename,
            'parents' => [$folderId],
        ];

        $session = $this->http->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size', [
            'headers' => array_merge($this->bearerHeaders($connection), [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => 'application/octet-stream',
                'X-Upload-Content-Length' => (string) $size,
            ]),
            'json' => $metadata,
        ]);

        $uploadUrl = $session->getHeaderLine('Location');
        if ($uploadUrl === '') {
            throw new \RuntimeException('Google Drive did not return a resumable upload URL.');
        }

        $remoteId = $this->putChunks($uploadUrl, $localPath, $size, 8 * 1024 * 1024);

        return [
            'id' => $remoteId,
            'path' => trim(($connection->folder_path ?: '').'/'.$remoteFilename, '/'),
            'size' => $size,
        ];
    }

    public function listBackupFiles(CloudBackupConnection $connection, string $folderId, string $prefix): array
    {
        $query = sprintf(
            "'%s' in parents and name contains '%s' and trashed = false",
            addslashes($folderId),
            addslashes($prefix)
        );

        $response = $this->getJson('https://www.googleapis.com/drive/v3/files', $connection, [
            'q' => $query,
            'fields' => 'files(id,name,createdTime)',
            'pageSize' => 1000,
        ]);

        return array_map(fn (array $file): array => [
            'id' => $file['id'],
            'name' => $file['name'],
            'created_at' => $file['createdTime'] ?? null,
        ], $response['files'] ?? []);
    }

    public function deleteFile(CloudBackupConnection $connection, string $fileId): void
    {
        try {
            $this->http->delete("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'headers' => $this->bearerHeaders($connection),
            ]);
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() !== 404) {
                throw $exception;
            }
        }
    }

    private function findFolder(CloudBackupConnection $connection, string $parentId, string $name): ?string
    {
        $query = sprintf(
            "'%s' in parents and mimeType = 'application/vnd.google-apps.folder' and name = '%s' and trashed = false",
            addslashes($parentId),
            addslashes($name)
        );

        $response = $this->getJson('https://www.googleapis.com/drive/v3/files', $connection, [
            'q' => $query,
            'fields' => 'files(id,name)',
            'pageSize' => 10,
        ]);

        return $response['files'][0]['id'] ?? null;
    }

    private function createFolder(CloudBackupConnection $connection, string $parentId, string $name): string
    {
        $response = $this->http->post('https://www.googleapis.com/drive/v3/files?fields=id', [
            'headers' => array_merge($this->bearerHeaders($connection), ['Content-Type' => 'application/json']),
            'json' => [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ],
        ]);

        return Arr::get(json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR), 'id');
    }

    private function putChunks(string $uploadUrl, string $localPath, int $size, int $chunkSize): ?string
    {
        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open artifact for upload.');
        }

        try {
            $offset = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new \RuntimeException('Could not read artifact chunk.');
                }

                $length = strlen($chunk);
                if ($length === 0) {
                    break;
                }

                $end = $offset + $length - 1;
                $response = $this->http->put($uploadUrl, [
                    'headers' => [
                        'Content-Length' => (string) $length,
                        'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                    ],
                    'body' => $chunk,
                    'http_errors' => false,
                ]);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $body = json_decode((string) $response->getBody(), true) ?: [];
                    return $body['id'] ?? null;
                }

                if ($response->getStatusCode() !== 308) {
                    throw new \RuntimeException('Google Drive chunk upload failed with HTTP '.$response->getStatusCode().'.');
                }

                $offset += $length;
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function accountFromToken(array $token): array
    {
        $response = $this->http->get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
        ]);

        $account = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'name' => $account['name'] ?? null,
            'email' => $account['email'] ?? null,
        ];
    }

    private function getJson(string $url, CloudBackupConnection $connection, array $query = []): array
    {
        $response = $this->http->get($url, [
            'headers' => $this->bearerHeaders($connection),
            'query' => $query,
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function postForm(string $url, array $form): array
    {
        try {
            $response = $this->http->post($url, ['form_params' => $form]);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('Google Drive OAuth request failed.', 0, $exception);
        }

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function redirectUri(): string
    {
        return $this->config('redirect_uri') ?: route('cloud-backup.oauth.callback', ['provider' => 'google']);
    }

    private function safeSegments(string $path): array
    {
        return collect(explode('/', str_replace('\\', '/', $path)))
            ->map(fn (string $segment): string => trim($segment))
            ->filter(fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..')
            ->map(fn (string $segment): string => Str::limit($segment, 120, ''))
            ->values()
            ->all();
    }

    private function config(string $key): mixed
    {
        $value = CloudBackupOAuthSetting::valueFor('google', $key, config("cloud-backup.oauth.google.{$key}"));

        if (($key === 'client_id' || $key === 'client_secret') && empty($value)) {
            throw new \RuntimeException("Google Drive OAuth {$key} is not configured.");
        }

        return $value;
    }
}
