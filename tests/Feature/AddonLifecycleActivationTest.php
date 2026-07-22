<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Services\Addons\AddonLifecycleService;
use App\Services\Addons\AddonRuntimeLoader;
use App\Services\Addons\AddonSignatureVerifier;
use App\Services\Addons\AddonZipInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class AddonLifecycleActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $installRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installRoot = base_path('addons/testing-lifecycle');
        config([
            'addons.install_path' => 'addons/testing-lifecycle',
            'addons.runtime_cache_path' => storage_path('framework/testing-lifecycle-active-addons.json'),
        ]);

        File::deleteDirectory($this->installRoot);
        File::delete(config('addons.runtime_cache_path'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installRoot);
        File::delete(config('addons.runtime_cache_path'));

        parent::tearDown();
    }

    public function test_existing_manifests_continue_to_activate_on_install(): void
    {
        Artisan::shouldReceive('call')->with('optimize:clear', [])->andReturn(0)->atLeast()->once();
        Artisan::shouldReceive('output')->andReturn('')->atLeast()->once();
        $service = $this->service($this->manifest());

        $addon = $service->installFromZip('test-addon.zip');

        $this->assertSame(Addon::STATUS_ACTIVE, $addon->status);
        $this->assertDatabaseHas('addon_install_logs', [
            'addon_id' => $addon->id,
            'action' => 'activate',
            'status' => 'successful',
        ]);
    }

    public function test_manifest_can_install_dormant_until_an_administrator_explicitly_activates_it(): void
    {
        $service = $this->service($this->manifest([
            'activate_on_install' => false,
            'migrations_path' => 'database/migrations',
        ]));

        $addon = $service->installFromZip('test-addon.zip');

        $this->assertSame(Addon::STATUS_INSTALLED, $addon->status);
        $this->assertDatabaseMissing('addon_install_logs', [
            'addon_id' => $addon->id,
            'action' => 'activate',
        ]);
        $this->assertDatabaseMissing('addon_install_logs', [
            'addon_id' => $addon->id,
            'action' => 'migrate',
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', [
                '--path' => 'addons/testing-lifecycle/church-tools.test-addon/database/migrations',
                '--force' => true,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('call')->twice()->with('optimize:clear', [])->andReturn(0);
        Artisan::shouldReceive('output')->times(3)->andReturn('');

        $activated = $service->activate($addon);

        $this->assertSame(Addon::STATUS_ACTIVE, $activated->status);
        $this->assertDatabaseHas('addon_install_logs', [
            'addon_id' => $addon->id,
            'action' => 'migrate',
            'status' => 'successful',
        ]);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function service(array $manifest): AddonLifecycleService
    {
        $zips = Mockery::mock(AddonZipInspector::class);
        $zips->shouldReceive('inspect')
            ->once()
            ->with('test-addon.zip')
            ->andReturn([
                'manifest' => $manifest,
                'checksum' => 'test-checksum',
                'root' => null,
            ]);
        $zips->shouldReceive('extractToStaging')
            ->once()
            ->andReturnUsing(function (string $zipPath, string $stagingPath) use ($manifest): array {
                File::ensureDirectoryExists($stagingPath);
                File::put($stagingPath.DIRECTORY_SEPARATOR.'addon.json', '{}');

                if (is_string($manifest['migrations_path'] ?? null)) {
                    File::ensureDirectoryExists($stagingPath.DIRECTORY_SEPARATOR.$manifest['migrations_path']);
                }

                return [
                    'manifest' => $manifest,
                    'checksum' => 'test-checksum',
                    'root' => null,
                    'path' => $stagingPath,
                ];
            });

        $runtime = Mockery::mock(AddonRuntimeLoader::class);
        $runtime->shouldReceive('registerAddon')->zeroOrMoreTimes();
        $runtime->shouldReceive('refreshActiveAddonCache')->zeroOrMoreTimes();

        $signatures = Mockery::mock(AddonSignatureVerifier::class);
        $signatures->shouldReceive('verify')
            ->once()
            ->andReturn(['verified' => true, 'method' => 'test', 'key_id' => 'test']);

        return new AddonLifecycleService($zips, $runtime, $signatures);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function manifest(array $overrides = []): array
    {
        return array_replace_recursive([
            'package_key' => 'church-tools.test-addon',
            'composer_name' => 'church-tools/test-addon',
            'name' => 'Test Add-on',
            'description' => 'A lifecycle test add-on.',
            'version' => '1.0.0',
            'provider' => 'ChurchTools\\TestAddon\\TestAddonServiceProvider',
            'namespace' => 'ChurchTools\\TestAddon\\',
            'autoload_psr4' => ['ChurchTools\\TestAddon\\' => 'src/'],
        ], $overrides);
    }
}
