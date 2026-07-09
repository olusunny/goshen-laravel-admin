<?php

namespace App\Services\Addons;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class AddonZipInspector
{
    public function __construct(private readonly AddonManifestValidator $manifests) {}

    /**
     * @return array{manifest: array<string, mixed>, checksum: string, root: string|null}
     */
    public function inspect(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('The uploaded add-on ZIP could not be found.');
        }

        $this->assertUploadedZipSize($zipPath);

        $zip = $this->open($zipPath);
        $root = null;
        $manifestPayload = null;
        $fileCount = 0;
        $totalUncompressedSize = 0;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $this->entryName($entry);
                $this->assertSafeEntry($name, $entry);

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $fileCount++;
                $totalUncompressedSize += $this->entrySize($entry);
                $this->assertArchiveLimits($name, $entry, $fileCount, $totalUncompressedSize);
                $this->assertNotNestedArchive($name);

                if ($name === 'addon.json' || str_ends_with($name, '/addon.json')) {
                    $root = $name === 'addon.json' ? null : substr($name, 0, -strlen('/addon.json'));
                    $manifestPayload = $zip->getFromIndex($index);
                }
            }
        } finally {
            $zip->close();
        }

        if (! is_string($manifestPayload) || $manifestPayload === '') {
            throw new RuntimeException('The ZIP must contain addon.json at the package root.');
        }

        $decoded = json_decode($manifestPayload, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('addon.json is not valid JSON.');
        }

        return [
            'manifest' => $this->manifests->validate($decoded),
            'checksum' => hash_file('sha256', $zipPath) ?: '',
            'root' => $root,
        ];
    }

    /**
     * @return array{manifest: array<string, mixed>, checksum: string, root: string|null, path: string}
     */
    public function extractToStaging(string $zipPath, string $stagingPath): array
    {
        $inspection = $this->inspect($zipPath);
        File::ensureDirectoryExists($stagingPath);
        File::cleanDirectory($stagingPath);

        $zip = $this->open($zipPath);

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $this->entryName($entry);
                $this->assertSafeEntry($name, $entry);

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $this->assertNotNestedArchive($name);

                $relative = $this->stripRoot($name, $inspection['root']);
                if ($relative === null || $relative === '') {
                    continue;
                }

                $target = $stagingPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $this->assertTargetInside($stagingPath, $target);
                File::ensureDirectoryExists(dirname($target));

                $stream = $zip->getStream($name);
                if (! $stream) {
                    throw new RuntimeException("Unable to read ZIP entry [{$name}].");
                }

                $targetHandle = fopen($target, 'wb');
                if (! $targetHandle) {
                    fclose($stream);
                    throw new RuntimeException("Unable to write ZIP entry [{$name}].");
                }

                try {
                    $this->copyStreamWithLimit($stream, $targetHandle, $this->entrySize($entry), $name);
                } finally {
                    fclose($stream);
                    fclose($targetHandle);
                }
            }
        } finally {
            $zip->close();
        }

        $this->assertRequiredFilesExist($stagingPath, $inspection['manifest']);

        return [...$inspection, 'path' => $stagingPath];
    }

    private function open(string $zipPath): ZipArchive
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new RuntimeException('The uploaded file is not a readable ZIP archive.');
        }

        return $zip;
    }

    /**
     * @param array<string, mixed>|false $entry
     */
    private function entryName(array|false $entry): string
    {
        if (! is_array($entry) || ! is_string($entry['name'] ?? null)) {
            throw new RuntimeException('The ZIP contains an unreadable entry.');
        }

        return str_replace('\\', '/', $entry['name']);
    }

    /**
     * @param array<string, mixed>|false $entry
     */
    private function assertSafeEntry(string $name, array|false $entry): void
    {
        if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)) {
            throw new RuntimeException("The ZIP contains an unsafe absolute path [{$name}].");
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException("The ZIP contains an unsafe traversal path [{$name}].");
            }
        }

        $attributes = is_array($entry) ? (int) ($entry['external_attributes'] ?? 0) : 0;
        $fileType = ($attributes >> 16) & 0170000;
        if ($fileType === 0120000) {
            throw new RuntimeException("The ZIP contains an unsafe symlink [{$name}].");
        }
    }

    private function assertUploadedZipSize(string $zipPath): void
    {
        $maxBytes = max(1, (int) config('addons.zip.max_size_kb', 51200)) * 1024;
        $size = filesize($zipPath);

        if ($size !== false && $size > $maxBytes) {
            throw new RuntimeException('The uploaded add-on ZIP is larger than the configured limit.');
        }
    }

    /**
     * @param array<string, mixed>|false $entry
     */
    private function assertArchiveLimits(string $name, array|false $entry, int $fileCount, int $totalUncompressedSize): void
    {
        $maxFiles = max(1, (int) config('addons.zip.max_files', 1000));
        if ($fileCount > $maxFiles) {
            throw new RuntimeException("The ZIP contains too many files. The configured limit is {$maxFiles}.");
        }

        $entrySize = $this->entrySize($entry);
        $maxEntryBytes = max(1, (int) config('addons.zip.max_entry_size_kb', 10240)) * 1024;
        if ($entrySize > $maxEntryBytes) {
            throw new RuntimeException("The ZIP entry [{$name}] is larger than the configured per-file limit.");
        }

        $maxTotalBytes = max(1, (int) config('addons.zip.max_uncompressed_size_kb', 102400)) * 1024;
        if ($totalUncompressedSize > $maxTotalBytes) {
            throw new RuntimeException('The ZIP uncompressed size is larger than the configured limit.');
        }

        $compressedSize = $this->entryCompressedSize($entry);
        $maxRatio = max(1.0, (float) config('addons.zip.max_compression_ratio', 100));
        if ($entrySize > 0 && $compressedSize <= 0) {
            throw new RuntimeException("The ZIP entry [{$name}] has an unsafe compression profile.");
        }

        if ($entrySize > 0 && $compressedSize > 0 && ($entrySize / $compressedSize) > $maxRatio) {
            throw new RuntimeException("The ZIP entry [{$name}] exceeds the configured compression ratio limit.");
        }
    }

    private function assertNotNestedArchive(string $name): void
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $blocked = array_map(
            fn (mixed $value): string => strtolower(ltrim((string) $value, '.')),
            (array) config('addons.zip.nested_archive_extensions', ['zip']),
        );

        if ($extension !== '' && in_array($extension, $blocked, true)) {
            throw new RuntimeException("Nested archive files are not allowed inside add-on ZIPs [{$name}].");
        }
    }

    /**
     * @param array<string, mixed>|false $entry
     */
    private function entrySize(array|false $entry): int
    {
        return is_array($entry) ? max(0, (int) ($entry['size'] ?? 0)) : 0;
    }

    /**
     * @param array<string, mixed>|false $entry
     */
    private function entryCompressedSize(array|false $entry): int
    {
        return is_array($entry) ? max(0, (int) ($entry['comp_size'] ?? 0)) : 0;
    }

    /**
     * @param resource $source
     * @param resource $target
     */
    private function copyStreamWithLimit($source, $target, int $expectedBytes, string $name): void
    {
        $maxEntryBytes = max(1, (int) config('addons.zip.max_entry_size_kb', 10240)) * 1024;
        $copied = 0;

        while (! feof($source)) {
            $chunk = fread($source, 1024 * 1024);
            if ($chunk === false) {
                throw new RuntimeException("Unable to read ZIP entry [{$name}].");
            }

            if ($chunk === '') {
                continue;
            }

            $copied += strlen($chunk);
            if ($copied > $maxEntryBytes || ($expectedBytes > 0 && $copied > $expectedBytes)) {
                throw new RuntimeException("The ZIP entry [{$name}] exceeded the configured extraction limit.");
            }

            if (fwrite($target, $chunk) === false) {
                throw new RuntimeException("Unable to write ZIP entry [{$name}].");
            }
        }
    }

    private function stripRoot(string $name, ?string $root): ?string
    {
        if ($root === null || $root === '') {
            return $name;
        }

        return str_starts_with($name, $root.'/')
            ? substr($name, strlen($root) + 1)
            : null;
    }

    private function assertTargetInside(string $root, string $target): void
    {
        $rootReal = realpath($root) ?: $root;
        $targetDir = dirname($target);
        File::ensureDirectoryExists($targetDir);
        $targetReal = realpath($targetDir) ?: $targetDir;

        if (! str_starts_with($targetReal, $rootReal)) {
            throw new RuntimeException('The ZIP extraction target escaped the staging directory.');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function assertRequiredFilesExist(string $stagingPath, array $manifest): void
    {
        foreach (['addon.json', 'composer.json'] as $file) {
            if (! is_file($stagingPath.DIRECTORY_SEPARATOR.$file)) {
                throw new RuntimeException("The add-on package is missing [{$file}].");
            }
        }

        foreach (($manifest['autoload_psr4'] ?? []) as $path) {
            if (! is_dir($stagingPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $path))) {
                throw new RuntimeException("The add-on package is missing autoload directory [{$path}].");
            }
        }
    }
}
