<?php

namespace Tests\Feature;

use App\Services\Addons\AddonManifestValidator;
use App\Services\Addons\AddonZipInspector;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class AddonZipInspectorTest extends TestCase
{
    private array $createdZips = [];

    protected function tearDown(): void
    {
        foreach ($this->createdZips as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_valid_addon_zip_can_be_inspected(): void
    {
        $zip = $this->zip([
            'addon.json' => json_encode($this->manifest(), JSON_THROW_ON_ERROR),
            'composer.json' => '{"name":"sunny/test-addon"}',
            'src/TestServiceProvider.php' => '<?php',
        ]);

        $inspection = $this->inspector()->inspect($zip);

        $this->assertSame('sunny.test-addon', $inspection['manifest']['package_key']);
        $this->assertNotEmpty($inspection['checksum']);
        $this->assertNull($inspection['root']);
    }

    public function test_zip_with_traversal_path_is_rejected(): void
    {
        $zip = $this->zip([
            'addon.json' => json_encode($this->manifest(), JSON_THROW_ON_ERROR),
            '../evil.php' => '<?php',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe traversal path');

        $this->inspector()->inspect($zip);
    }

    public function test_zip_with_nested_archive_is_rejected(): void
    {
        $zip = $this->zip([
            'addon.json' => json_encode($this->manifest(), JSON_THROW_ON_ERROR),
            'composer.json' => '{"name":"sunny/test-addon"}',
            'src/payload.zip' => 'nested',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nested archive files are not allowed');

        $this->inspector()->inspect($zip);
    }

    public function test_zip_with_too_many_files_is_rejected(): void
    {
        config(['addons.zip.max_files' => 2]);

        $zip = $this->zip([
            'addon.json' => json_encode($this->manifest(), JSON_THROW_ON_ERROR),
            'composer.json' => '{"name":"sunny/test-addon"}',
            'src/TestServiceProvider.php' => '<?php',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('too many files');

        $this->inspector()->inspect($zip);
    }

    public function test_zip_with_oversized_entry_is_rejected(): void
    {
        config(['addons.zip.max_entry_size_kb' => 1]);

        $zip = $this->zip([
            'addon.json' => json_encode($this->manifest(), JSON_THROW_ON_ERROR),
            'composer.json' => '{"name":"sunny/test-addon"}',
            'src/TestServiceProvider.php' => str_repeat('A', 2048),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('per-file limit');

        $this->inspector()->inspect($zip);
    }

    private function inspector(): AddonZipInspector
    {
        return new AddonZipInspector(new AddonManifestValidator());
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return [
            'schema_version' => '1.0',
            'package_key' => 'sunny.test-addon',
            'composer_name' => 'sunny/test-addon',
            'name' => 'Test Add-on',
            'version' => '1.0.0',
            'minimum_php' => '8.2',
            'minimum_laravel' => '10.0',
            'provider' => 'Sunny\\TestAddon\\TestServiceProvider',
            'namespace' => 'Sunny\\TestAddon\\',
            'autoload_psr4' => [
                'Sunny\\TestAddon\\' => 'src/',
            ],
        ];
    }

    /**
     * @param array<string, string> $entries
     */
    private function zip(array $entries): string
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is not available.');
        }

        $path = tempnam(sys_get_temp_dir(), 'addon-test-');
        $this->createdZips[] = $path;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));

        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());

        return $path;
    }
}
