<?php

namespace Tests\Feature;

use App\Contracts\Addons\AddonLifecycleHandler;
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

    public function test_dormant_addon_update_runs_setup_tasks_before_remaining_installed(): void
    {
        $manifest = $this->manifest([
            'version' => '1.0.1',
            'activate_on_install' => false,
            'migrations_path' => 'database/migrations',
        ]);
        $installPath = $this->installRoot.'/church-tools.test-addon';
        File::ensureDirectoryExists($installPath);
        File::put($installPath.'/addon.json', '{}');

        $addon = Addon::query()->create([
            'package_key' => 'church-tools.test-addon',
            'name' => 'Test Add-on',
            'installed_version' => '1.0.0',
            'status' => Addon::STATUS_INSTALLED,
            'manifest' => $this->manifest(['activate_on_install' => false]),
            'install_path' => $installPath,
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', [
                '--path' => 'addons/testing-lifecycle/church-tools.test-addon/database/migrations',
                '--force' => true,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->andReturn(0);
        Artisan::shouldReceive('output')->twice()->andReturn('');

        $updated = $this->service($manifest)->installFromZip('test-addon.zip');

        $this->assertSame(Addon::STATUS_INSTALLED, $updated->status);
        $this->assertSame('1.0.1', $updated->installed_version);
        $this->assertDatabaseHas('addon_install_logs', [
            'addon_id' => $addon->id,
            'action' => 'migrate',
            'status' => 'successful',
        ]);
    }

    public function test_failed_update_migration_restores_the_previous_active_addon(): void
    {
        $oldManifest = $this->manifest([
            'supports_birthday_celebrations' => true,
        ]);
        $addon = $this->activeAddon($oldManifest);
        $oldHashes = $this->fileHashes($addon->install_path);
        $newManifest = $this->manifest([
            'version' => '1.0.1',
            'migrations_path' => 'database/migrations',
            'supports_birthday_celebrations' => false,
            'test_file_content' => 'failed update',
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', [
                '--path' => 'addons/testing-lifecycle/church-tools.test-addon/database/migrations',
                '--force' => true,
            ])
            ->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn('migration failed');

        try {
            $this->service($newManifest, new AddonRuntimeLoader())->installFromZip('test-addon.zip');
            $this->fail('The failed migration should abort the add-on update.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('migrate', $exception->getMessage());
        }

        $this->assertPreviousActiveAddonWasRestored($addon, $oldManifest, $oldHashes);
        $this->assertActiveRuntimeCacheWasRestored($oldManifest);
    }

    public function test_failed_update_activation_restores_the_previous_active_addon(): void
    {
        $oldManifest = $this->manifest([
            'supports_birthday_celebrations' => true,
        ]);
        $addon = $this->activeAddon($oldManifest);
        $oldHashes = $this->fileHashes($addon->install_path);
        $newManifest = $this->manifest([
            'version' => '1.0.1',
            'supports_birthday_celebrations' => false,
            'test_file_content' => 'failed update',
        ]);

        $runtime = Mockery::mock(AddonRuntimeLoader::class);
        $runtime->shouldReceive('registerAddon')->once()->andThrow(new \RuntimeException('provider activation failed'));
        $runtime->shouldReceive('refreshActiveAddonCache')->twice();

        try {
            $this->service($newManifest, $runtime)->installFromZip('test-addon.zip');
            $this->fail('The failed provider activation should abort the add-on update.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('provider activation failed', $exception->getMessage());
        }

        $this->assertPreviousActiveAddonWasRestored($addon, $oldManifest, $oldHashes);
    }

    public function test_failed_update_gates_runtime_and_quarantines_replacement_files_before_restore(): void
    {
        $oldManifest = $this->manifest(['supports_birthday_celebrations' => true]);
        $addon = $this->activeAddon($oldManifest);
        $oldHashes = $this->fileHashes($addon->install_path);
        $runtime = new FailingRecordingAddonRuntimeLoader;
        $service = $this->service($this->manifest([
            'version' => '1.0.1',
            'replacement_only_file' => 'replacement-only.php',
        ]), $runtime);

        try {
            $service->installFromZip('test-addon.zip');
            $this->fail('The failed provider activation should abort the add-on update.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('provider activation failed', $exception->getMessage());
        }

        $this->assertCount(2, $runtime->refreshSnapshots);
        $this->assertSame(Addon::STATUS_INSTALLED, $runtime->refreshSnapshots[0]['status']);
        $this->assertNull(collect($runtime->refreshSnapshots[0]['addons'])->firstWhere('package_key', 'church-tools.test-addon'));
        $this->assertSame(Addon::STATUS_ACTIVE, $runtime->refreshSnapshots[1]['status']);
        $this->assertSame('1.0.0', collect($runtime->refreshSnapshots[1]['addons'])
            ->firstWhere('package_key', 'church-tools.test-addon')['installed_version']);
        $this->assertTrue(collect($service->movedDirectories)->contains(
            fn (array $move): bool => $move['from'] === $addon->install_path
                && str_contains(basename($move['to']), '.failed-'),
        ));
        $this->assertFileDoesNotExist($addon->install_path.'/replacement-only.php');
        $this->assertPreviousActiveAddonWasRestored($addon, $oldManifest, $oldHashes);
    }

    public function test_failed_backup_move_uses_checked_copy_recovery_and_removes_quarantine(): void
    {
        $oldManifest = $this->manifest(['supports_birthday_celebrations' => true]);
        $addon = $this->activeAddon($oldManifest);
        $oldHashes = $this->fileHashes($addon->install_path);
        $runtime = new FailingRecordingAddonRuntimeLoader;
        $service = $this->service($this->manifest([
            'version' => '1.0.1',
            'replacement_only_file' => 'replacement-only.php',
        ]), $runtime);
        $service->failBackupRestoreMove = true;

        try {
            $service->installFromZip('test-addon.zip');
            $this->fail('The failed provider activation should abort the add-on update.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('provider activation failed', $exception->getMessage());
        }

        $this->assertTrue(collect($service->copiedDirectories)->contains(
            fn (array $copy): bool => str_contains($copy['from'], 'backups') && $copy['to'] === $addon->install_path,
        ));
        $quarantinePath = collect($service->movedDirectories)
            ->first(fn (array $move): bool => str_contains(basename($move['to']), '.failed-'))['to'];
        $this->assertFalse(File::exists($quarantinePath));
        $this->assertPreviousActiveAddonWasRestored($addon, $oldManifest, $oldHashes);
        $this->assertActiveRuntimeCacheWasRestored($oldManifest);
    }

    public function test_failed_rollback_preserves_the_original_update_exception(): void
    {
        $oldManifest = $this->manifest(['supports_birthday_celebrations' => true]);
        $addon = $this->activeAddon($oldManifest);
        $originalFailure = new OriginalUpdateFailure('provider activation failed');
        $runtime = new FailingRecordingAddonRuntimeLoader($originalFailure);
        $service = $this->service($this->manifest([
            'version' => '1.0.1',
            'replacement_only_file' => 'replacement-only.php',
        ]), $runtime);
        $service->failBackupRestoreMove = true;
        $service->failBackupCopy = true;

        try {
            $service->installFromZip('test-addon.zip');
            $this->fail('The failed provider activation should abort the add-on update.');
        } catch (OriginalUpdateFailure $exception) {
            $this->assertSame('provider activation failed', $exception->getMessage());
            $this->assertSame($originalFailure, $exception);
        }

        $this->assertCount(2, $runtime->refreshSnapshots);
        $this->assertSame(Addon::STATUS_INSTALLED, $addon->fresh()->status);
        foreach ($runtime->refreshSnapshots as $snapshot) {
            $this->assertSame(Addon::STATUS_INSTALLED, $snapshot['status']);
            $this->assertNull(collect($snapshot['addons'])->firstWhere('package_key', 'church-tools.test-addon'));
        }
        $this->assertNull(
            collect(json_decode((string) File::get(config('addons.runtime_cache_path')), true)['addons'] ?? [])
                ->firstWhere('package_key', 'church-tools.test-addon'),
        );
        $this->assertDatabaseHas('addon_install_logs', [
            'action' => 'rollback',
            'status' => 'failed',
        ]);
    }

    public function test_lifecycle_handler_runs_before_deactivation_and_uninstall(): void
    {
        LifecycleHandlerForTest::$operations = [];
        class_alias(LifecycleHandlerForTest::class, 'ChurchTools\\TestAddon\\LifecycleHandlerForTest');
        $installPath = $this->installRoot.'/church-tools.test-addon';
        File::ensureDirectoryExists($installPath);

        $addon = Addon::query()->create([
            'package_key' => 'church-tools.test-addon',
            'name' => 'Test Add-on',
            'installed_version' => '1.0.0',
            'status' => Addon::STATUS_ACTIVE,
            'manifest' => $this->manifest([
                'lifecycle_handler' => 'ChurchTools\\TestAddon\\LifecycleHandlerForTest',
            ]),
            'install_path' => $installPath,
        ]);

        Artisan::shouldReceive('call')->twice()->with('optimize:clear', [])->andReturn(0);
        Artisan::shouldReceive('output')->twice()->andReturn('');

        $service = $this->service($this->manifest());
        $service->deactivate($addon);
        $service->uninstall($addon->fresh(), removeFiles: false);

        $this->assertSame(['deactivate', 'uninstall'], LifecycleHandlerForTest::$operations);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function service(array $manifest, ?AddonRuntimeLoader $runtime = null): RecordingAddonLifecycleService
    {
        $zips = Mockery::mock(AddonZipInspector::class);
        $zips->shouldReceive('inspect')
            ->zeroOrMoreTimes()
            ->with('test-addon.zip')
            ->andReturn([
                'manifest' => $manifest,
                'checksum' => 'test-checksum',
                'root' => null,
            ]);
        $zips->shouldReceive('extractToStaging')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $zipPath, string $stagingPath) use ($manifest): array {
                File::ensureDirectoryExists($stagingPath);
                File::put($stagingPath.DIRECTORY_SEPARATOR.'addon.json', (string) ($manifest['test_file_content'] ?? '{}'));

                if (is_string($manifest['replacement_only_file'] ?? null)) {
                    File::put($stagingPath.DIRECTORY_SEPARATOR.$manifest['replacement_only_file'], 'replacement-only');
                }

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

        if ($runtime === null) {
            $runtime = Mockery::mock(AddonRuntimeLoader::class);
            $runtime->shouldReceive('registerAddon')->zeroOrMoreTimes();
            $runtime->shouldReceive('refreshActiveAddonCache')->zeroOrMoreTimes();
        }

        $signatures = Mockery::mock(AddonSignatureVerifier::class);
        $signatures->shouldReceive('verify')
            ->zeroOrMoreTimes()
            ->andReturn(['verified' => true, 'method' => 'test', 'key_id' => 'test']);

        return new RecordingAddonLifecycleService($zips, $runtime, $signatures);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function activeAddon(array $manifest): Addon
    {
        $installPath = $this->installRoot.'/church-tools.test-addon';
        File::ensureDirectoryExists($installPath);
        File::put($installPath.'/addon.json', 'previous release');
        File::ensureDirectoryExists($installPath.'/src');
        File::put($installPath.'/src/Release.php', 'previous source');

        return Addon::query()->create([
            'package_key' => 'church-tools.test-addon',
            'name' => 'Test Add-on',
            'installed_version' => '1.0.0',
            'status' => Addon::STATUS_ACTIVE,
            'provider_class' => $manifest['provider'],
            'namespace' => $manifest['namespace'],
            'autoload_psr4' => $manifest['autoload_psr4'],
            'manifest' => $manifest,
            'install_path' => $installPath,
            'checksum' => 'previous-checksum',
            'signature_verified' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $oldManifest
     */
    private function assertPreviousActiveAddonWasRestored(Addon $addon, array $oldManifest, array $oldHashes): void
    {
        $restored = $addon->fresh();

        $this->assertSame('1.0.0', $restored->installed_version);
        $this->assertSame(Addon::STATUS_ACTIVE, $restored->status);
        $this->assertSame('previous-checksum', $restored->checksum);
        $this->assertSame($oldManifest, $restored->manifest);
        $this->assertTrue($restored->supports('birthday_celebrations'));
        $this->assertTrue(File::isDirectory($restored->install_path));
        $this->assertSame($oldHashes, $this->fileHashes($restored->install_path));
        $this->assertDatabaseHas('addon_install_logs', [
            'addon_id' => $restored->id,
            'action' => 'rollback',
            'status' => 'successful',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function fileHashes(string $path): array
    {
        return [
            'addon.json' => hash_file('sha256', $path.'/addon.json'),
            'src/Release.php' => hash_file('sha256', $path.'/src/Release.php'),
        ];
    }

    /**
     * @param array<string, mixed> $oldManifest
     */
    private function assertActiveRuntimeCacheWasRestored(array $oldManifest): void
    {
        $cache = json_decode((string) File::get(config('addons.runtime_cache_path')), true, flags: JSON_THROW_ON_ERROR);
        $activeAddon = $cache['addons'][0] ?? [];

        $this->assertSame('1.0.0', $activeAddon['installed_version'] ?? null);
        $this->assertSame($oldManifest, $activeAddon['manifest'] ?? null);
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

class LifecycleHandlerForTest implements AddonLifecycleHandler
{
    /** @var array<int, string> */
    public static array $operations = [];

    public function deactivate(): void
    {
        self::$operations[] = 'deactivate';
    }

    public function uninstall(): void
    {
        self::$operations[] = 'uninstall';
    }
}

class RecordingAddonLifecycleService extends AddonLifecycleService
{
    /** @var array<int, array{from: string, to: string}> */
    public array $movedDirectories = [];

    public bool $failQuarantineMove = false;

    public bool $failBackupRestoreMove = false;

    public bool $failBackupCopy = false;

    /** @var array<int, array{from: string, to: string}> */
    public array $copiedDirectories = [];

    protected function moveDirectoryOrFail(string $from, string $to, bool $overwrite = false): void
    {
        $this->movedDirectories[] = compact('from', 'to');

        if ($this->failQuarantineMove && str_contains(basename($to), '.failed-')) {
            throw new \RuntimeException('quarantine move failed');
        }

        if ($this->failBackupRestoreMove && str_contains($from, 'backups')) {
            throw new \RuntimeException('backup restore move failed');
        }

        parent::moveDirectoryOrFail($from, $to, $overwrite);
    }

    protected function copyDirectoryOrFail(string $from, string $to): void
    {
        $this->copiedDirectories[] = compact('from', 'to');

        if ($this->failBackupCopy) {
            throw new \RuntimeException('backup restore copy failed');
        }

        parent::copyDirectoryOrFail($from, $to);
    }
}

class FailingRecordingAddonRuntimeLoader extends AddonRuntimeLoader
{
    /** @var array<int, array{status: string, addons: array<int, array<string, mixed>>}> */
    public array $refreshSnapshots = [];

    public function __construct(private readonly \Throwable $failure = new \RuntimeException('provider activation failed')) {}

    public function registerAddon(array $manifest, string $installPath): void
    {
        throw $this->failure;
    }

    public function refreshActiveAddonCache(): void
    {
        parent::refreshActiveAddonCache();

        $cache = json_decode((string) File::get(config('addons.runtime_cache_path')), true, flags: JSON_THROW_ON_ERROR);
        $this->refreshSnapshots[] = [
            'status' => (string) Addon::query()
                ->where('package_key', 'church-tools.test-addon')
                ->value('status'),
            'addons' => $cache['addons'] ?? [],
        ];
    }
}

class OriginalUpdateFailure extends \RuntimeException {}
