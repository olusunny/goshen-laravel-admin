<?php

namespace ChurchTools\DigitalHymnBook\Console;

use App\Models\User;
use ChurchTools\DigitalHymnBook\Services\HymnImporter;
use ChurchTools\DigitalHymnBook\Services\HymnPublisher;
use Illuminate\Console\Command;
use RuntimeException;

class ImportApprovedHymns extends Command
{
    protected $signature = 'hymns:import-approved {path} {--actor= : Existing authorized administrator ID} {--checksum= : Expected reviewed JSON SHA256} {--apply : Import drafts after preview} {--publish : Include approved imports and publish a reviewed revision}';

    protected $description = 'Validate or import an editorially approved hymn JSON; publication requires a separate explicit option.';

    public function handle(HymnImporter $importer, HymnPublisher $publisher): int
    {
        $actor = User::query()->findOrFail((int) $this->option('actor'));
        $path = realpath((string) $this->argument('path'));
        if ($path === false || ! is_file($path) || filesize($path) > 12 * 1024 * 1024) {
            throw new RuntimeException('A reviewed JSON file no larger than 12 MiB is required.');
        }
        $json = file_get_contents($path);
        $checksum = hash('sha256', $json);
        if (! hash_equals($checksum, (string) $this->option('checksum'))) {
            throw new RuntimeException('The JSON checksum does not match the reviewed artifact.');
        }
        $preview = $importer->preview($json, $actor);
        $this->line(json_encode(['preview' => $preview], JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            if ($this->option('publish')) {
                throw new RuntimeException('Publication requires an applied import.');
            }

            return self::SUCCESS;
        }
        $result = $importer->import($json, $actor, $checksum);
        $this->line(json_encode(['import' => $result], JSON_THROW_ON_ERROR));
        if ($this->option('publish')) {
            $publisher->prepareImportedBook($result['book_id'], $actor);
            $publication = $publisher->preview($result['book_id'], $actor);
            $release = $publisher->publish($result['book_id'], $actor, $publication['fingerprint']);
            $this->line(json_encode(['release' => ['revision' => $release->revision, 'sha256' => $release->sha256, 'counts' => $release->counts]], JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
