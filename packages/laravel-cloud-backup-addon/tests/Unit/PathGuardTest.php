<?php

namespace ChurchTools\CloudBackup\Tests\Unit;

use ChurchTools\CloudBackup\Services\PathGuard;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

class PathGuardTest extends TestCase
{
    public function test_it_excludes_configured_relative_paths(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cloud-backup-pathguard-'.bin2hex(random_bytes(4));
        $cache = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        mkdir($cache, 0777, true);
        $file = $cache.DIRECTORY_SEPARATOR.'laravel.log';
        file_put_contents($file, 'log');

        try {
            $guard = new PathGuard();

            $this->assertTrue($guard->shouldExclude(new SplFileInfo($file), realpath($root), ['storage/logs']));
            $this->assertFalse($guard->shouldExclude(new SplFileInfo($file), realpath($root), ['storage/framework/cache']));
        } finally {
            @unlink($file);
            @rmdir($cache);
            @rmdir(dirname($cache));
            @rmdir($root);
        }
    }
}
