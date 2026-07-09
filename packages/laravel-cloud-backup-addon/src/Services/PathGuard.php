<?php

namespace ChurchTools\CloudBackup\Services;

use SplFileInfo;

class PathGuard
{
    public function normalizeSourcePath(?string $path): string
    {
        $source = $path ?: config('cloud-backup.default_source_path', base_path());
        $real = realpath($source);

        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException('Backup source path does not exist or is not a directory.');
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    /**
     * @param array<int, string> $excludePaths
     */
    public function shouldExclude(SplFileInfo $file, string $sourcePath, array $excludePaths): bool
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return true;
        }

        $relative = ltrim(str_replace('\\', '/', substr($realPath, strlen($sourcePath))), '/');
        if ($relative === '') {
            return false;
        }

        foreach ($excludePaths as $exclude) {
            $exclude = trim(str_replace('\\', '/', $exclude), '/');
            if ($exclude === '') {
                continue;
            }

            if (
                $relative === $exclude
                || str_starts_with($relative, $exclude.'/')
                || fnmatch($exclude, $relative, FNM_PATHNAME)
            ) {
                return true;
            }
        }

        return false;
    }
}
