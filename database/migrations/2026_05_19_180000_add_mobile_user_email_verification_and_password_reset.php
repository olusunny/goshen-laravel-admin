<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_users', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('is_verified');
            }

            if (! Schema::hasColumn('mobile_users', 'email_verification_code_hash')) {
                $table->string('email_verification_code_hash')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('mobile_users', 'email_verification_expires_at')) {
                $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code_hash');
            }

            if (! Schema::hasColumn('mobile_users', 'password_reset_code_hash')) {
                $table->string('password_reset_code_hash')->nullable()->after('email_verification_expires_at');
            }

            if (! Schema::hasColumn('mobile_users', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_code_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verified_at',
                'email_verification_code_hash',
                'email_verification_expires_at',
                'password_reset_code_hash',
                'password_reset_expires_at',
            ]);
        });
    }
};
