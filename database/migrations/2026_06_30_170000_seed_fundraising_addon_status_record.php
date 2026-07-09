<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addons')) {
            return;
        }

        $exists = DB::table('addons')
            ->where('package_key', 'sunny.fundraising')
            ->exists();

        if ($exists) {
            return;
        }

        $manifest = [
            'schema_version' => '1.0',
            'package_key' => 'sunny.fundraising',
            'composer_name' => 'sunny/fundraising',
            'name' => 'Fundraising Campaigns',
            'version' => '1.0.0',
            'provider' => 'Sunny\\Fundraising\\FundraisingServiceProvider',
            'namespace' => 'Sunny\\Fundraising\\',
            'autoload_psr4' => [
                'Sunny\\Fundraising\\' => 'src/',
            ],
        ];

        DB::table('addons')->insert([
            'package_key' => 'sunny.fundraising',
            'composer_name' => 'sunny/fundraising',
            'name' => 'Fundraising Campaigns',
            'description' => 'Fundraising campaign and wallet contribution add-on.',
            'installed_version' => '1.0.0',
            'status' => 'active',
            'provider_class' => 'Sunny\\Fundraising\\FundraisingServiceProvider',
            'namespace' => 'Sunny\\Fundraising\\',
            'autoload_psr4' => json_encode(['Sunny\\Fundraising\\' => 'src/'], JSON_THROW_ON_ERROR),
            'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'install_path' => base_path('packages/sunny-fundraising'),
            'checksum' => null,
            'signature_verified' => true,
            'installed_at' => now(),
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve administrator-managed add-on status during rollbacks.
    }
};
