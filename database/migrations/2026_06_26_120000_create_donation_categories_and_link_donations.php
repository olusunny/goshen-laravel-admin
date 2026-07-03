<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('donation_category_id')
                ->nullable()
                ->after('phone')
                ->constrained('donation_categories')
                ->nullOnDelete();
            $table->string('purpose')->nullable()->after('donation_category_id');
        });

        $now = now();
        foreach ([
            ['Offering', 'Regular offering and worship gifts.', 10],
            ['Tithe', 'Tithe giving.', 20],
            ['Donation', 'General donation and support.', 30],
            ['Special Giving', 'Special thanksgiving, project, and programme giving.', 40],
        ] as [$name, $description, $sortOrder]) {
            DB::table('donation_categories')->updateOrInsert(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $defaultCategoryId = DB::table('donation_categories')
            ->where('slug', 'donation')
            ->value('id');

        if ($defaultCategoryId) {
            DB::table('donations')
                ->whereNull('donation_category_id')
                ->orderBy('id')
                ->select(['id', 'metadata'])
                ->chunkById(200, function ($donations) use ($defaultCategoryId): void {
                    foreach ($donations as $donation) {
                        $metadata = json_decode((string) $donation->metadata, true);
                        $purpose = is_array($metadata) && is_string($metadata['purpose'] ?? null)
                            ? $metadata['purpose']
                            : 'Donation';

                        DB::table('donations')
                            ->where('id', $donation->id)
                            ->update([
                                'donation_category_id' => $defaultCategoryId,
                                'purpose' => $purpose,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('donation_category_id');
            $table->dropColumn('purpose');
        });

        Schema::dropIfExists('donation_categories');
    }
};
