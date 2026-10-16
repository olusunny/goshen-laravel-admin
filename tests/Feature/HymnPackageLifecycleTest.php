<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use App\Services\Addons\AddonLifecycleService;
use ChurchTools\DigitalHymnBook\Services\HymnImporter;
use ChurchTools\DigitalHymnBook\Services\HymnPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class HymnPackageLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_trusted_zip_installs_dormant_and_preserves_content_through_its_lifecycle(): void
    {
        $relative = 'addons/testing-hymn-package-'.Str::lower(Str::random(10));
        $root = base_path($relative);
        File::ensureDirectoryExists($root);
        config(['addons.enabled' => true, 'addons.install_path' => $relative,
            'addons.runtime_cache_path' => $root.'/active.json',
            'addons.storage.staging_path' => $relative.'/staging',
            'addons.storage.backups_path' => $relative.'/backups', 'addons.signatures.required' => true]);
        Storage::set('local', Storage::build(['driver' => 'local', 'root' => $root.'/private', 'throw' => true]));
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $zipPath = $root.'/book.zip';
        try {
            $this->packageZip($zipPath);
            config(['addons.signatures.trusted_checksums' => [hash_file('sha256', $zipPath)]]);
            $lifecycle = app(AddonLifecycleService::class);
            $addon = $lifecycle->installFromZip($zipPath, $admin);
            $this->assertSame(Addon::STATUS_INSTALLED, $addon->status);
            $this->assertTrue($addon->signature_verified);
            $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', false);
            $addon = $lifecycle->activate($addon, $admin);
            $this->assertTrue(Schema::hasTable('hymn_books'));
            $this->assertTrue(Permission::where('name', 'publish_hymn')->exists());
            $this->assertSame(0, Role::findByName('super_admin')->permissions()->where('name', 'publish_hymn')->count());
            $data = json_decode(File::get(resource_path('hymns/mfm-hymns.approved.json')), true);
            $data['hymns'] = array_slice($data['hymns'], 0, 2);
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $result = app(HymnImporter::class)->import($json, $admin, hash('sha256', $json));
            $publisher = app(HymnPublisher::class);
            $publisher->prepareImportedBook($result['book_id'], $admin);
            $release = $publisher->publish($result['book_id'], $admin, $publisher->preview($result['book_id'], $admin)['fingerprint']);
            $url = '/api/v1/hymns/books/'.$result['book_id'].'/download/'.$release->revision;
            $this->get($url)->assertOk();
            $addon = $lifecycle->deactivate($addon, $admin);
            $this->getJson($url)->assertStatus(409);
            $addon = $lifecycle->activate($addon, $admin);
            $this->get($url)->assertOk();
            $this->packageZip($zipPath, '1.0.1');
            config(['addons.signatures.trusted_checksums' => [hash_file('sha256', $zipPath)]]);
            $addon = $lifecycle->installFromZip($zipPath, $admin);
            $this->assertSame('1.0.1', $addon->installed_version);
            $this->assertSame(Addon::STATUS_ACTIVE, $addon->status);
            $this->get($url)->assertOk();
            $lifecycle->uninstall($addon, $admin);
            $this->getJson($url)->assertStatus(409);
            $this->assertDatabaseCount('hymns', 2);
            Storage::disk('local')->assertExists($release->file_path);
            $addon = $lifecycle->installFromZip($zipPath, $admin);
            $this->assertSame(Addon::STATUS_INSTALLED, $addon->status);
            $lifecycle->activate($addon, $admin);
            $this->get($url)->assertOk();
            $this->assertDatabaseCount('hymn_releases', 1);
        } finally {
            File::deleteDirectory($root);
            File::deleteDirectory(storage_path('app/'.$relative));
        }
    }

    private function packageZip(string $path, string $version = '1.0.0'): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $source = base_path('packages/digital-hymn-book-addon');
        foreach (File::allFiles($source) as $file) {
            $name = str_replace('\\', '/', $file->getRelativePathname());
            $content = $file->getContents();
            if ($name === 'addon.json') {
                $manifest = json_decode($content, true);
                $manifest['version'] = $version;
                $content = json_encode($manifest, JSON_THROW_ON_ERROR);
            }
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }
}
