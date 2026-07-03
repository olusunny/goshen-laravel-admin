<?php

namespace ChurchTools\CloudBackup\Services;

use ChurchTools\CloudBackup\Models\CloudBackupRun;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class FileArchiveService
{
    public function __construct(private readonly PathGuard $pathGuard)
    {
    }

    /**
     * @param array<int, string> $excludePaths
     */
    public function createArchive(CloudBackupRun $run, string $sourcePath, string $destination, array $excludePaths = []): void
    {
        $sourcePath = $this->pathGuard->normalizeSourcePath($sourcePath);
        $excludes = array_values(array_unique(array_merge(config('cloud-backup.exclude_paths', []), $excludePaths)));
        $skipSymlinks = (bool) config('cloud-backup.archive.skip_symlinks', true);
        $totalFiles = $this->countFiles($sourcePath, $excludes, $skipSymlinks);

        $this->markArchiveProgress($run, 0, max(1, $totalFiles), 'Scanning files for backup archive');

        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create backup ZIP archive.');
        }

        $iterator = $this->includedFiles($sourcePath, $excludes, $skipSymlinks);

        $added = 0;

        try {
            foreach ($iterator as $file) {
                $realPath = $file->getRealPath();
                if ($realPath === false || !$file->isFile()) {
                    continue;
                }

                $relative = ltrim(str_replace('\\', '/', substr($realPath, strlen($sourcePath))), '/');
                if ($relative === '' || str_contains($relative, '../')) {
                    continue;
                }

                $zip->addFile($realPath, $relative);
                $added++;

                if ($added % 1000 === 0) {
                    $run->appendLog("Archived {$added} files.");
                }

                if ($added % 250 === 0 || $added === $totalFiles) {
                    $this->markArchiveProgress($run, $added, max(1, $totalFiles), "Archived {$added} of {$totalFiles} files");
                }
            }
        } catch (\Throwable $throwable) {
            $zip->close();
            throw $throwable;
        }

        if ($zip->close() !== true) {
            throw new \RuntimeException('Could not finalize backup ZIP archive.');
        }

        $run->appendLog("Created file archive with {$added} files.");
    }

    /**
     * @param array<int, string> $excludes
     */
    private function countFiles(string $sourcePath, array $excludes, bool $skipSymlinks): int
    {
        $count = 0;

        foreach ($this->includedFiles($sourcePath, $excludes, $skipSymlinks) as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, string> $excludes
     */
    private function includedFiles(string $sourcePath, array $excludes, bool $skipSymlinks): RecursiveIteratorIterator
    {
        $directory = new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            function ($file) use ($sourcePath, $excludes, $skipSymlinks): bool {
                if ($skipSymlinks && $file->isLink()) {
                    return false;
                }

                return ! $this->pathGuard->shouldExclude($file, $sourcePath, $excludes);
            }
        );

        return new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
    }

    private function markArchiveProgress(CloudBackupRun $run, int $archived, int $totalFiles, string $step): void
    {
        $percent = 26 + (int) floor(min(1, $archived / max(1, $totalFiles)) * 18);

        $run->forceFill([
            'progress_percent' => max(26, min(44, $percent)),
            'current_step' => $step,
        ])->save();
    }
}
