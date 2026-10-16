<?php

namespace ChurchTools\DigitalHymnBook;

use App\Contracts\Hymns\HymnAdministration;
use App\Contracts\Hymns\HymnCatalog;
use App\Models\Hymn;
use App\Services\Hymns\HymnAddon;
use ChurchTools\DigitalHymnBook\Console\ImportApprovedHymns;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Models\HymnSection;
use ChurchTools\DigitalHymnBook\Models\HymnStanza;
use ChurchTools\DigitalHymnBook\Services\PublishedHymnCatalog;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class DigitalHymnBookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! interface_exists(HymnCatalog::class) || ! class_exists(HymnAddon::class)
            || HymnAddon::HOST_API_VERSION !== 1) {
            throw new RuntimeException('Digital Hymn Book requires the host hymn integration version 1. Update the host before activation.');
        }
        $this->app->bind(HymnCatalog::class, PublishedHymnCatalog::class);
        $this->app->bind(HymnAdministration::class, Admin\HymnAdministration::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportApprovedHymns::class]);
        }
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Hymn::resolveRelationUsing('book', fn (Hymn $hymn) => $hymn->belongsTo(HymnBook::class, 'hymn_book_id'));
        Hymn::resolveRelationUsing('section', fn (Hymn $hymn) => $hymn->belongsTo(HymnSection::class, 'hymn_section_id'));
        Hymn::resolveRelationUsing('stanzas', fn (Hymn $hymn) => $hymn->hasMany(HymnStanza::class)->orderBy('sort_order'));
    }
}
