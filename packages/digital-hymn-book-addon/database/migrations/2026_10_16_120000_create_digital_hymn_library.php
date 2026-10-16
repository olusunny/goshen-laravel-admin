<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hymn_books')) {
            Schema::create('hymn_books', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 120)->unique();
                $table->string('name');
                $table->string('language', 20)->default('en');
                $table->string('edition')->nullable();
                $table->text('attribution')->nullable();
                $table->text('copyright_notice')->nullable();
                $table->string('source_sha256', 64)->nullable();
                $table->unsignedBigInteger('published_release_id')->nullable()->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('hymn_sections')) {
            Schema::create('hymn_sections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hymn_book_id')->constrained('hymn_books')->restrictOnDelete();
                $table->string('code', 120);
                $table->string('name');
                $table->unsignedInteger('sort_order');
                $table->timestamps();
                $table->unique(['hymn_book_id', 'code']);
                $table->index(['hymn_book_id', 'sort_order']);
            });
        }

        $columns = [
            'hymn_book_id' => fn (Blueprint $t) => $t->unsignedBigInteger('hymn_book_id')->nullable()->index(),
            'hymn_section_id' => fn (Blueprint $t) => $t->unsignedBigInteger('hymn_section_id')->nullable()->index(),
            'source_key' => fn (Blueprint $t) => $t->string('source_key', 160)->nullable(),
            'printed_number' => fn (Blueprint $t) => $t->string('printed_number', 80)->nullable(),
            'numeric_order' => fn (Blueprint $t) => $t->unsignedInteger('numeric_order')->nullable()->index(),
            'first_line' => fn (Blueprint $t) => $t->text('first_line')->nullable(),
            'normalized_title' => fn (Blueprint $t) => $t->string('normalized_title')->nullable()->index(),
            'title_initial' => fn (Blueprint $t) => $t->string('title_initial', 8)->nullable()->index(),
            'tune' => fn (Blueprint $t) => $t->string('tune')->nullable(),
            'metre' => fn (Blueprint $t) => $t->string('metre')->nullable(),
            'source_heading' => fn (Blueprint $t) => $t->text('source_heading')->nullable(),
            'source_pages' => fn (Blueprint $t) => $t->json('source_pages')->nullable(),
            'review_status' => fn (Blueprint $t) => $t->string('review_status', 40)->default('draft'),
            'source_warnings' => fn (Blueprint $t) => $t->json('source_warnings')->nullable(),
            'import_checksum' => fn (Blueprint $t) => $t->string('import_checksum', 64)->nullable(),
            'reviewed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('reviewed_by')->nullable(),
            'reviewed_at' => fn (Blueprint $t) => $t->timestamp('reviewed_at')->nullable(),
        ];
        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('hymns', $name)) {
                Schema::table('hymns', $definition);
            }
        }
        if (! Schema::hasIndex('hymns', 'hymns_book_source_unique')) {
            Schema::table('hymns', fn (Blueprint $t) => $t->unique(['hymn_book_id', 'source_key'], 'hymns_book_source_unique'));
        }
        if (! Schema::hasIndex('hymns', 'hymns_book_number_unique')) {
            Schema::table('hymns', fn (Blueprint $t) => $t->unique(['hymn_book_id', 'number'], 'hymns_book_number_unique'));
        }
        if (! Schema::hasTable('hymn_stanzas')) {
            Schema::create('hymn_stanzas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hymn_id')->constrained('hymns')->cascadeOnDelete();
                $table->unsignedInteger('sort_order');
                $table->string('kind', 16);
                $table->string('label', 50)->nullable();
                $table->longText('text');
                $table->timestamps();
                $table->unique(['hymn_id', 'sort_order']);
            });
        }
        if (! Schema::hasTable('hymn_imports')) {
            Schema::create('hymn_imports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hymn_book_id')->constrained('hymn_books')->restrictOnDelete();
                $table->string('file_checksum', 64)->unique();
                $table->json('approval');
                $table->json('summary');
                $table->unsignedBigInteger('imported_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('hymn_releases')) {
            Schema::create('hymn_releases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hymn_book_id')->constrained('hymn_books')->restrictOnDelete();
                $table->uuid('revision')->unique();
                $table->string('file_path');
                $table->string('sha256', 64);
                $table->unsignedBigInteger('size_bytes');
                $table->json('counts');
                $table->json('book_metadata');
                $table->json('section_metadata');
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('hymn_publication_entries')) {
            Schema::create('hymn_publication_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hymn_release_id')->constrained('hymn_releases')->cascadeOnDelete();
                $table->unsignedBigInteger('hymn_id');
                $table->unsignedBigInteger('hymn_book_id')->index();
                $table->string('number', 80);
                $table->unsignedInteger('numeric_order');
                $table->string('normalized_title');
                $table->string('title_initial', 8);
                $table->string('section_code', 120);
                $table->longText('search_text');
                $table->json('payload');
                $table->unique(['hymn_release_id', 'hymn_id']);
                $table->index(['hymn_book_id', 'numeric_order']);
                $table->index(['hymn_book_id', 'title_initial']);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Hymn content is retained. Deactivate/uninstall the add-on; schema removal needs a separate reviewed data backup and removal procedure.');
    }
};
