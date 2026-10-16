<?php

namespace Tests\Unit;

use ChurchTools\DigitalHymnBook\Services\ApprovedHymnDataset;
use Composer\Autoload\ClassLoader;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovedHymnDatasetTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('ChurchTools\\DigitalHymnBook\\', dirname(__DIR__, 2).'/packages/digital-hymn-book-addon/src');
        }
    }

    public function test_full_approved_source_dataset_is_valid_and_keeps_original_number(): void
    {
        $data = (new ApprovedHymnDataset)->read(file_get_contents(dirname(__DIR__, 2).'/resources/hymns/mfm-hymns.approved.json'));
        $this->assertCount(1505, $data['hymns']);
        $this->assertCount(33, $data['sections']);
        $hymn = $data['hymns'][1114];
        $this->assertSame('1115', $hymn['number']);
        $this->assertSame('1064', $hymn['printed_number']);
        $this->assertSame('approved_source', $hymn['review_status']);
    }

    public function test_candidate_cannot_bypass_editorial_gate(): void
    {
        $this->expectException(ValidationException::class);
        (new ApprovedHymnDataset)->read('{"schema_version":1,"dataset_kind":"candidate"}');
    }

    public function test_invalid_json_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new ApprovedHymnDataset)->read('{invalid');
    }
}
