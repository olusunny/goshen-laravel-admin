<?php

namespace Tests\Feature;

use App\Filament\Pages\AppSettings;
use App\Filament\Resources\AddonResource\Pages\ListAddons;
use App\Filament\Resources\HymnResource\Pages\EditHymn;
use App\Filament\Resources\HymnResource\Pages\ListHymns;
use App\Models\Addon;
use App\Models\AppSetting;
use App\Models\Hymn;
use App\Models\User;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Services\HymnPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithHymnAddon;
use Tests\TestCase;

class HymnAdminTest extends TestCase
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

    public function test_editor_can_manage_lyrics_but_cannot_publish_or_install_addons(): void
    {
        $this->importHymns();
        $editor = User::factory()->create();
        $editor->givePermissionTo('manage_hymn');
        Livewire::actingAs($editor)->test(ListHymns::class)->assertSuccessful()
            ->assertActionVisible('importHymnJson')->assertActionVisible('prepareHymnBook')
            ->assertActionHidden('publishHymnBook');
        Livewire::actingAs($editor)->test(ListAddons::class)->assertForbidden();
    }

    public function test_admin_import_and_publication_previews_are_bound_to_the_selected_content(): void
    {
        $json = $this->hymnJson();
        $upload = UploadedFile::fake()->createWithContent('approved-hymns.json', $json);
        Livewire::actingAs($this->hymnAdmin)->test(ListHymns::class)->mountAction('importHymnJson')
            ->setActionData(['file' => $upload])->assertActionDataSet(['expected_checksum' => hash('sha256', $json)])
            ->callMountedAction()->assertHasNoFormErrors();
        $this->assertDatabaseCount('hymns', 2);
        $book = HymnBook::query()->firstOrFail();
        Livewire::actingAs($this->hymnAdmin)->test(ListHymns::class)
            ->callAction('prepareHymnBook', data: ['book_id' => $book->id])->assertHasNoFormErrors();
        $preview = app(HymnPublisher::class)->preview($book->id, $this->hymnAdmin);
        Livewire::actingAs($this->hymnAdmin)->test(ListHymns::class)->mountAction('publishHymnBook')
            ->setActionData(['book_id' => $book->id])->assertActionDataSet(['expected_fingerprint' => $preview['fingerprint']])
            ->callMountedAction()->assertHasNoFormErrors();
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', true);
    }

    public function test_guarded_console_import_previews_before_applying_and_can_be_repeated(): void
    {
        $json = $this->hymnJson();
        Storage::disk('local')->put('tests/approved.json', $json);
        $arguments = ['path' => Storage::disk('local')->path('tests/approved.json'),
            '--actor' => $this->hymnAdmin->id, '--checksum' => hash('sha256', $json)];
        $this->artisan('hymns:import-approved', $arguments)->assertSuccessful();
        $this->assertDatabaseCount('hymns', 0);
        $this->artisan('hymns:import-approved', [...$arguments, '--apply' => true, '--publish' => true])->assertSuccessful();
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', true);
        $this->artisan('hymns:import-approved', [...$arguments, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('hymns', 2);
        $this->assertDatabaseCount('hymn_imports', 1);
    }

    public function test_structured_form_save_updates_lyrics_and_requires_new_review(): void
    {
        $result = $this->importHymns();
        $hymn = Hymn::query()->where('hymn_book_id', $result['book_id'])->where('number', '1')->firstOrFail();
        Livewire::actingAs($this->hymnAdmin)->test(EditHymn::class, ['record' => $hymn->id])
            ->fillForm(['title' => 'An edited hymn', 'stanzas' => [
                ['kind' => 'verse', 'label' => '1', 'text' => "First line\nA <script> tag is literal lyrics"],
                ['kind' => 'chorus', 'label' => null, 'text' => 'The chorus'],
            ]])->call('save')->assertHasNoFormErrors();
        $hymn->refresh();
        $this->assertSame('An edited hymn', $hymn->title);
        $this->assertSame('draft', $hymn->review_status);
        $this->assertNull($hymn->import_checksum);
        $this->assertCount(2, $hymn->stanzas);
        $this->assertStringNotContainsString('<script>', $hymn->content);
        Livewire::actingAs($this->hymnAdmin)->test(EditHymn::class, ['record' => $hymn->id])->callAction('reviewHymn');
        $this->assertSame('reviewed', $hymn->fresh()->review_status);
    }

    public function test_stale_edit_form_cannot_save_after_deactivation(): void
    {
        $result = $this->importHymns();
        $hymn = Hymn::query()->where('hymn_book_id', $result['book_id'])->firstOrFail();
        $original = $hymn->title;
        $page = Livewire::actingAs($this->hymnAdmin)->test(EditHymn::class, ['record' => $hymn->id]);
        $this->hymnAddon->update(['status' => Addon::STATUS_INACTIVE]);
        $page->fillForm(['title' => 'Stale write'])->call('save')->assertNotFound();
        $this->assertSame($original, $hymn->fresh()->title);
        $this->actingAs($this->hymnAdmin)->get('/admin/hymns')->assertForbidden();
    }

    public function test_old_visibility_setting_is_retained_but_no_longer_editable_in_the_hub(): void
    {
        AppSetting::query()->updateOrCreate(['key' => 'hymns_enabled'], ['value' => '1', 'group' => 'features', 'is_secret' => false]);
        Livewire::actingAs($this->hymnAdmin)->test(AppSettings::class)
            ->assertDontSee('wire:model.live="hymnsEnabled"', false)
            ->set('hymnsEnabled', true)->assertForbidden();
        $this->assertSame('1', AppSetting::where('key', 'hymns_enabled')->value('value'));
    }

    public function test_missing_download_is_a_transport_error_and_does_not_prevent_admin_recovery(): void
    {
        $result = $this->importHymns();
        $release = $this->publishHymns($result['book_id']);
        Storage::disk('local')->delete($release->file_path);
        $this->getJson('/api/v1/hymns/status')->assertStatus(500);
        Livewire::actingAs($this->hymnAdmin)->test(ListHymns::class)->assertSuccessful();
        $publisher = app(HymnPublisher::class);
        $preview = $publisher->preview($result['book_id'], $this->hymnAdmin);
        $recovered = $publisher->publish($result['book_id'], $this->hymnAdmin, $preview['fingerprint']);
        $this->assertNotSame($release->id, $recovered->id);
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', true);
        $this->assertSame($recovered->id, HymnBook::find($result['book_id'])->published_release_id);
    }
}
