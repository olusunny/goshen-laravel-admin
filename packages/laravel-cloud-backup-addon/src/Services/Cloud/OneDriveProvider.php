<?php

namespace ChurchTools\CloudBackup\Services\Cloud;

use ChurchTools\CloudBackup\Contracts\CloudProvider;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Models\CloudBackupOAuthSetting;
use ChurchTools\CloudBackup\Services\Cloud\Concerns\ManagesTokens;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OneDriveProvider implements CloudProvider
{
    use ManagesTokens;

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public function __construct(private readonly Client $http)
    {
    }

    public function providerKey(): string
    {
        return 'onedrive';
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->config('client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => implode(' ', $this->config('scopes')),
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return $this->oauthBase().'/authorize?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        $response = $this->postForm($this->oauthBase().'/token', [
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'code' => $code,
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
            throw new \RuntimeException('OneDrive refresh token is missing.');
        }

        $response = $this->postForm($this->oauthBase().'/token', [
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'refresh_token' => $token['refresh_token'],
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'refresh_token',
        ]);

        return $this->normalizeToken($response, $token['refresh_token']);
    }

    public function ensureFolder(CloudBackupConnection $connection, string $folderPath): string
    {
        $parentId = 'root';

        foreach ($this->safeSegments($folderPath) as $segment) {
            $existing = $this->findChildFolder($connection, $parentId, $segment);
            $parentId = $existing ?: $this->createChildFolder($connection, $parentId, $segment);
        }

        return $parentId;
    }

    public function uploadFile(CloudBackupConnection $connection, string $localPath, string $remoteFilename, string $folderId): array
    {
        $size = filesize($localPath);
        if ($size === false) {
            throw new \RuntimeException("Could not read file size for {$remoteFilename}.");
        }

        $session = $this->postJson(
            self::GRAPH."/me/drive/items/{$folderId}:/".rawurlencode($remoteFilename).':/createUploadSession',
            $connection,
            ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']]
        );

        $uploadUrl = $session['uploadUrl'] ?? null;
        if (!$uploadUrl) {
            throw new \RuntimeException('OneDrive did not return an upload session URL.');
        }

        $remoteId = $this->putChunks($uploadUrl, $localPath, $size, 3276800)
            ?: $this->findChildFile($connection, $folderId, $remoteFilename);

        return [
            'id' => $remoteId,
            'path' => trim(($connection->folder_path ?: '').'/'.$remoteFilename, '/'),
            'size' => $size,
        ];
    }

    public function listBackupFiles(CloudBackupConnection $connection, string $folderId, string $prefix): array
    {
        $items = $this->getJson(self::GRAPH."/me/drive/items/{$folderId}/children", $connection);

        return collect($items['value'] ?? [])
            ->filter(fn (array $item): bool => empty($item['folder']) && str_starts_with($item['name'] ?? '', $prefix))
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'name' => $item['name'],
                'created_at' => $item['createdDateTime'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function deleteFile(CloudBackupConnection $connection, string $fileId): void
    {
        try {
            $this->http->delete(self::GRAPH."/me/drive/items/{$fileId}", [
                'headers' => $this->bearerHeaders($connection),
            ]);
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() !== 404) {
                throw $exception;
            }
        }
    }

    private function findChildFolder(CloudBackupConnection $connection, string $parentId, string $name): ?string
    {
        $items = $this->getJson(self::GRAPH."/me/drive/items/{$parentId}/children", $connection);

        foreach ($items['value'] ?? [] as $item) {
            if (!empty($item['folder']) && strcasecmp($item['name'] ?? '', $name) === 0) {
                return $item['id'];
            }
        }

        return null;
    }

    private function findChildFile(CloudBackupConnection $connection, string $parentId, string $name): ?string
    {
        $items = $this->getJson(self::GRAPH."/me/drive/items/{$parentId}/children", $connection);

        foreach ($items['value'] ?? [] as $item) {
            if (empty($item['folder']) && strcasecmp($item['name'] ?? '', $name) === 0) {
                return $item['id'] ?? null;
            }
        }

        return null;
    }

    private function createChildFolder(CloudBackupConnection $connection, string $parentId, string $name): string
    {
        $folder = $this->postJson(self::GRAPH."/me/drive/items/{$parentId}/children", $connection, [
            'name' => $name,
            'folder' => new \stdClass(),
            '@microsoft.graph.conflictBehavior' => 'rename',
        ]);

        return Arr::get($folder, 'id');
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

                if ($response->getStatusCode() === 202) {
                    $offset += $length;

                    continue;
                }

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $body = json_decode((string) $response->getBody(), true) ?: [];
                    return $body['id'] ?? null;
                }

                throw new \RuntimeException('OneDrive chunk upload failed with HTTP '.$response->getStatusCode().'.');
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function accountFromToken(array $token): array
    {
        $response = $this->http->get(self::GRAPH.'/me', [
            'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
        ]);

        $account = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'name' => $account['displayName'] ?? null,
            'email' => $account['mail'] ?? $account['userPrincipalName'] ?? null,
        ];
    }

    private function getJson(string $url, CloudBackupConnection $connection): array
    {
        $response = $this->http->get($url, [
            'headers' => $this->bearerHeaders($connection),
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function postJson(string $url, CloudBackupConnection $connection, array $json): array
    {
        $response = $this->http->post($url, [
            'headers' => array_merge($this->bearerHeaders($connection), ['Content-Type' => 'application/json']),
            'json' => $json,
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function postForm(string $url, array $form): array
    {
        $response = $this->http->post($url, ['form_params' => $form]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function oauthBase(): string
    {
        return 'https://login.microsoftonline.com/'.$this->config('tenant').'/oauth2/v2.0';
    }

    private function redirectUri(): string
    {
        return $this->config('redirect_uri') ?: route('cloud-backup.oauth.callback', ['provider' => 'onedrive']);
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
        $value = CloudBackupOAuthSetting::valueFor('onedrive', $key, config("cloud-backup.oauth.onedrive.{$key}"));

        if (($key === 'client_id' || $key === 'client_secret') && empty($value)) {
            throw new \RuntimeException("OneDrive OAuth {$key} is not configured.");
        }

        return $value;
    }
}
