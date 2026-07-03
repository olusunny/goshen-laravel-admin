<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundraising_campaign_contributions', function (Blueprint $table): void {
            if (! Schema::hasColumn('fundraising_campaign_contributions', 'payment_provider')) {
                $table->string('payment_provider', 40)->default('wallet')->after('status')->index();
            }

            if (! Schema::hasColumn('fundraising_campaign_contributions', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('payment_provider')->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fundraising_campaign_contributions', function (Blueprint $table): void {
            if (Schema::hasColumn('fundraising_campaign_contributions', 'provider_reference')) {
                $table->dropUnique(['provider_reference']);
                $table->dropColumn('provider_reference');
            }

            if (Schema::hasColumn('fundraising_campaign_contributions', 'payment_provider')) {
                $table->dropColumn('payment_provider');
            }
        });
    }
};
