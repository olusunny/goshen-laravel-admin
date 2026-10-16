<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Hymn;
use App\Models\User;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Services\ApprovedHymnDataset;
use ChurchTools\DigitalHymnBook\Services\HymnImporter;
use ChurchTools\DigitalHymnBook\Services\HymnPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithHymnAddon;
use Tests\TestCase;

class HymnLibraryTest extends TestCase
{
    use InteractsWithHymnAddon, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHymnAddon();
    }

    protected function tearDown(): void
    {
        $this->tearDownHymnAddon();
        parent::tearDown();
    }

    public function test_full_book_import_is_draft_idempotent_and_preserves_existing_ids(): void
    {
        $legacy = Hymn::query()->create(['title' => 'Existing hymn', 'number' => '1', 'content' => '<p>Keep me</p>']);
        $result = $this->importHymns(null);
        $this->assertSame(1505, $result['hymns']);
        $this->assertDatabaseCount('hymns', 1506);
        $this->assertDatabaseCount('hymn_sections', 33);
        $this->assertDatabaseCount('hymn_stanzas', 6763);
        $this->assertSame(0, Hymn::query()->where('hymn_book_id', $result['book_id'])->where('is_published', true)->count());
        $this->assertSame('<p>Keep me</p>', $legacy->fresh()->content);
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('reason', 'no_published_book');
        $hymn = Hymn::query()->where('hymn_book_id', $result['book_id'])->where('number', '1115')->firstOrFail();
        $this->assertSame('1064', $hymn->printed_number);
        $hymn->update(['title' => 'A later editorial correction', 'import_checksum' => null]);
        $repeat = $this->importHymns(null);
        $this->assertFalse($repeat['imported']);
        $this->assertSame($result['book_id'], $repeat['book_id']);
        $this->assertSame('A later editorial correction', $hymn->fresh()->title);
        $this->assertDatabaseCount('hymn_imports', 1);
    }

    public function test_public_release_has_a_verified_download_and_legacy_shape(): void
    {
        $result = $this->importHymns();
        $release = $this->publishHymns($result['book_id']);
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', true)
            ->assertJsonPath('books.0.counts.hymns', 2);
        $manifest = $this->getJson('/api/v1/hymns/books/'.$result['book_id'].'/manifest')->assertOk();
        $this->assertSame($release->sha256, hash('sha256', Storage::disk('local')->get($release->file_path)));
        $this->get($manifest->json('download_url'))->assertOk()->assertHeader('X-Content-SHA256', $release->sha256);
        foreach (['/api/fetch_hymns', '/fetch_hymns'] as $url) {
            $response = $this->postJson($url, ['data' => ['query' => '#1', 'page' => '0']])->assertOk()
                ->assertJsonPath('status', 'ok')->assertJsonPath('isLastPage', true);
            $this->assertCount(1, $response->json('hymns'));
            $this->assertStringContainsString('<strong>Verse 1</strong>', $response->json('hymns.0.content'));
            $this->assertNotEmpty($response->json('hymns.0.thumbnail'));
        }
        $payload = json_decode(Storage::disk('local')->get($release->file_path), true);
        $this->assertArrayNotHasKey('approval', $payload);
        $this->assertArrayNotHasKey('source_warnings', $payload['hymns'][0]);
        $this->assertSame([1, 2, 3, 4, 5], array_column($payload['hymns'][0]['stanzas'], 'verse_number'));
    }

    public function test_draft_edits_do_not_change_public_snapshots(): void
    {
        $result = $this->importHymns();
        $release = $this->publishHymns($result['book_id']);
        $hymn = Hymn::query()->where('hymn_book_id', $result['book_id'])->where('number', '1')->firstOrFail();
        $original = $hymn->title;
        $hymn->update(['title' => 'Not reviewed yet', 'review_status' => 'draft']);
        $this->getJson('/api/v1/hymns/'.$hymn->id)->assertOk()->assertJsonPath('hymn.title', $original);
        $this->assertSame($release->id, HymnBook::find($result['book_id'])->published_release_id);
        $this->expectException(ValidationException::class);
        app(HymnPublisher::class)->preview($result['book_id'], $this->hymnAdmin);
    }

    public function test_stale_publication_preview_cannot_replace_previous_release(): void
    {
        $result = $this->importHymns();
        $release = $this->publishHymns($result['book_id']);
        $publisher = app(HymnPublisher::class);
        $preview = $publisher->preview($result['book_id'], $this->hymnAdmin);
        Hymn::query()->where('hymn_book_id', $result['book_id'])->where('number', '1')->update(['title' => 'Changed after preview']);
        try {
            $publisher->publish($result['book_id'], $this->hymnAdmin, $preview['fingerprint']);
            $this->fail('A stale publication preview must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('hymn_releases', 1);
            $this->assertSame($release->id, HymnBook::find($result['book_id'])->published_release_id);
            Storage::disk('local')->assertExists($release->file_path);
        }
    }

    public function test_deactivation_blocks_a_previously_issued_download_and_reactivation_preserves_it(): void
    {
        $result = $this->importHymns();
        $release = $this->publishHymns($result['book_id']);
        $url = '/api/v1/hymns/books/'.$result['book_id'].'/download/'.$release->revision;
        $this->hymnAddon->update(['status' => Addon::STATUS_INACTIVE]);
        $this->getJson($url)->assertStatus(409)->assertJsonPath('code', 'hymn_disabled');
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', false);
        Storage::disk('local')->assertExists($release->file_path);
        $this->hymnAddon->update(['status' => Addon::STATUS_ACTIVE]);
        $this->get($url)->assertOk();
        $this->assertDatabaseCount('hymn_releases', 1);
    }

    public function test_an_editor_without_publish_permission_cannot_publish(): void
    {
        $result = $this->importHymns();
        $editor = User::factory()->create();
        $editor->givePermissionTo('manage_hymn');
        $this->assertDatabaseCount('hymn_releases', 0);
        $this->expectException(HttpException::class);
        app(HymnPublisher::class)->preview($result['book_id'], $editor);
    }

    public function test_search_pagination_and_literals_are_bounded_and_stable(): void
    {
        $result = $this->importHymns();
        $hymn = Hymn::query()->where('hymn_book_id', $result['book_id'])->where('number', '1')->firstOrFail();
        $hymn->stanzas()->first()->update(['text' => "Mercy is 100% sure\nAn_underlined_word\n<script>alert('x')</script>"]);
        $this->publishHymns($result['book_id']);
        $this->getJson('/api/v1/hymns?'.http_build_query(['q' => '100%']))->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/hymns?'.http_build_query(['q' => 'father son']))->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/hymns?'.http_build_query(['q' => '_underlined_']))->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/hymns?limit=1&page=1')->assertOk()->assertJsonPath('has_more', true)->assertJsonPath('items.0.number', '1');
        $this->getJson('/api/v1/hymns?limit=1&page=2')->assertOk()->assertJsonPath('has_more', false)->assertJsonPath('items.0.number', '2');
        $this->getJson('/api/v1/hymns?limit=101')->assertUnprocessable();
        $this->getJson('/api/v1/hymns?'.http_build_query(['q' => str_repeat('x', 201)]))->assertUnprocessable();
        $legacy = $this->postJson('/api/fetch_hymns', ['data' => ['query' => '#1']])->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $legacy->json('hymns.0.content'));
        $this->assertStringNotContainsString('<script>', $legacy->json('hymns.0.content'));
    }

    public function test_import_rechecks_stale_preview_identity_and_source_edition_under_lock(): void
    {
        $json = $this->hymnJson();
        $importer = app(HymnImporter::class);
        $stale = $importer->preview($json, $this->hymnAdmin);
        $result = $importer->import($json, $this->hymnAdmin, hash('sha256', $json));
        $raced = \Mockery::mock(HymnImporter::class, [app(ApprovedHymnDataset::class)])->makePartial();
        $raced->shouldReceive('preview')->once()->andReturn($stale);
        $repeat = $raced->import($json, $this->hymnAdmin, hash('sha256', $json));
        $this->assertFalse($repeat['imported']);
        $this->assertSame($result['book_id'], $repeat['book_id']);
        $data = json_decode($json, true);
        $data['source']['sha256'] = str_repeat('b', 64);
        $data['book']['source_sha256'] = $data['source']['sha256'];
        $different = json_encode($data, JSON_THROW_ON_ERROR);
        app(ApprovedHymnDataset::class)->read($different);
        $checksum = hash('sha256', $different);
        $raced->shouldReceive('preview')->once()->andReturn([...$stale, 'checksum' => $checksum]);
        try {
            $raced->import($different, $this->hymnAdmin, $checksum);
            $this->fail('A different source must not overwrite an existing book after a stale preview.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('hymns', 2);
            $this->assertDatabaseCount('hymn_imports', 1);
        }
    }
}
