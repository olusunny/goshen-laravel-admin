<?php

namespace Tests\Feature;

use App\Services\Addons\AddonManifestValidator;
use RuntimeException;
use Tests\TestCase;

class AddonManifestValidatorTest extends TestCase
{
    public function test_valid_manifest_passes_validation(): void
    {
        $manifest = $this->validator()->validate($this->manifest());

        $this->assertSame('sunny.test-addon', $manifest['package_key']);
    }

    public function test_unsafe_migrations_path_is_rejected(): void
    {
        $manifest = $this->manifest([
            'migrations_path' => 'database/../migrations',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migrations_path');

        $this->validator()->validate($manifest);
    }

    public function test_windows_absolute_migrations_path_is_rejected(): void
    {
        $manifest = $this->manifest([
            'migrations_path' => 'C:\\temp\\migrations',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migrations_path');

        $this->validator()->validate($manifest);
    }

    public function test_host_app_seeder_class_is_rejected(): void
    {
        $manifest = $this->manifest([
            'seeders' => [
                'Database\\Seeders\\DatabaseSeeder',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inside the add-on namespace');

        $this->validator()->validate($manifest);
    }

    public function test_activate_on_install_must_be_a_boolean_when_present(): void
    {
        $manifest = $this->manifest(['activate_on_install' => 'false']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('activate_on_install');

        $this->validator()->validate($manifest);
    }

    public function test_capability_permission_mappings_are_validated(): void
    {
        $manifest = $this->manifest([
            'capabilities' => [
                'prayer_session_attendance' => [
                    'permissions' => ['attendance.scan', 'attendance.coordinate'],
                ],
            ],
        ]);

        $validated = $this->validator()->validate($manifest);

        $this->assertSame(['attendance.scan', 'attendance.coordinate'], $validated['capabilities']['prayer_session_attendance']['permissions']);
    }

    public function test_capability_permission_mappings_reject_invalid_values(): void
    {
        $manifest = $this->manifest([
            'capabilities' => [
                'Prayer attendance' => ['permissions' => ['attendance.scan']],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('capability key');

        $this->validator()->validate($manifest);
    }

    private function validator(): AddonManifestValidator
    {
        return new AddonManifestValidator();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function manifest(array $overrides = []): array
    {
        return array_replace_recursive([
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
            'migrations_path' => 'database/migrations',
            'seeders' => [
                'Sunny\\TestAddon\\Database\\Seeders\\TestSeeder',
            ],
        ], $overrides);
    }
}
