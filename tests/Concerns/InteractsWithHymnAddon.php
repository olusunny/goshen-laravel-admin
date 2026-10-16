<?php

namespace Tests\Concerns;

use App\Models\Addon;
use App\Models\User;
use App\Services\Hymns\HymnAddon;
use ChurchTools\DigitalHymnBook\DigitalHymnBookServiceProvider;
use ChurchTools\DigitalHymnBook\Models\HymnRelease;
use ChurchTools\DigitalHymnBook\Services\HymnImporter;
use ChurchTools\DigitalHymnBook\Services\HymnPublisher;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait InteractsWithHymnAddon
{
    private string $hymnTestRoot;

    protected Addon $hymnAddon;

    protected User $hymnAdmin;

    protected function setUpHymnAddon(): void
    {
        $relative = 'addons/testing-hymns-'.Str::lower(Str::random(12));
        $this->hymnTestRoot = base_path($relative);
        config(['addons.install_path' => $relative,
            'addons.runtime_cache_path' => $this->hymnTestRoot.'/active.json', 'addons.enabled' => true]);
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('ChurchTools\\DigitalHymnBook\\', base_path('packages/digital-hymn-book-addon/src'));
        }
        app()->register(DigitalHymnBookServiceProvider::class);
        (require base_path('packages/digital-hymn-book-addon/database/migrations/2026_10_16_120000_create_digital_hymn_library.php'))->up();
        Storage::set('local', Storage::build(['driver' => 'local', 'root' => $this->hymnTestRoot.'/private', 'throw' => true]));
        $path = $this->hymnTestRoot.'/'.HymnAddon::PACKAGE_KEY;
        File::ensureDirectoryExists($path);
        File::copy(base_path('packages/digital-hymn-book-addon/addon.json'), $path.'/addon.json');
        $manifest = json_decode(File::get($path.'/addon.json'), true);
        $this->hymnAddon = Addon::query()->create(['package_key' => HymnAddon::PACKAGE_KEY,
            'name' => 'Digital Hymn Book', 'installed_version' => '1.0.0', 'status' => Addon::STATUS_ACTIVE,
            'manifest' => $manifest, 'install_path' => $path, 'signature_verified' => true]);
        Role::findOrCreate('super_admin', 'web');
        Permission::findOrCreate('manage_hymn', 'web');
        Permission::findOrCreate('publish_hymn', 'web');
        $this->hymnAdmin = User::factory()->create();
        $this->hymnAdmin->assignRole('super_admin');
    }

    protected function tearDownHymnAddon(): void
    {
        File::deleteDirectory($this->hymnTestRoot);
    }

    protected function hymnJson(?int $count = 2): string
    {
        $data = json_decode(File::get(resource_path('hymns/mfm-hymns.approved.json')), true);
        if ($count !== null) {
            $data['hymns'] = array_slice($data['hymns'], 0, $count);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function importHymns(?int $count = 2): array
    {
        $json = $this->hymnJson($count);

        return app(HymnImporter::class)->import($json, $this->hymnAdmin, hash('sha256', $json));
    }

    protected function publishHymns(int $bookId): HymnRelease
    {
        $publisher = app(HymnPublisher::class);
        $publisher->prepareImportedBook($bookId, $this->hymnAdmin);
        $preview = $publisher->preview($bookId, $this->hymnAdmin);

        return $publisher->publish($bookId, $this->hymnAdmin, $preview['fingerprint']);
    }
}
