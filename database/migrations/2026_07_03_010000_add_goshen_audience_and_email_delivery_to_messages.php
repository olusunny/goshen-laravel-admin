<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inbox_messages', 'email_notifications'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'goshen_event_id')) {
                    $table->foreignId('goshen_event_id')
                        ->nullable()
                        ->after('selected_role_ids')
                        ->constrained('ei_events')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'goshen_payment_filter')) {
                    $table->string('goshen_payment_filter', 40)->nullable()->after('goshen_event_id')->index();
                }

                if (! Schema::hasColumn($tableName, 'goshen_paid_from')) {
                    $table->timestamp('goshen_paid_from')->nullable()->after('goshen_payment_filter')->index();
                }

                if (! Schema::hasColumn($tableName, 'goshen_paid_until')) {
                    $table->timestamp('goshen_paid_until')->nullable()->after('goshen_paid_from')->index();
                }

                if (! Schema::hasColumn($tableName, 'goshen_recent_days')) {
                    $table->unsignedSmallInteger('goshen_recent_days')->nullable()->after('goshen_paid_until');
                }

                if (! Schema::hasColumn($tableName, 'goshen_paid_week')) {
                    $table->date('goshen_paid_week')->nullable()->after('goshen_recent_days')->index();
                }

                if (! Schema::hasColumn($tableName, 'goshen_paid_month')) {
                    $table->string('goshen_paid_month', 7)->nullable()->after('goshen_paid_week')->index();
                }

                if (! Schema::hasColumn($tableName, 'fundraising_campaign_id')) {
                    $table->foreignId('fundraising_campaign_id')
                        ->nullable()
                        ->after('goshen_paid_month')
                        ->constrained('fundraising_campaigns')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'goshen_quiz_id')) {
                    $table->foreignId('goshen_quiz_id')
                        ->nullable()
                        ->after('fundraising_campaign_id')
                        ->constrained('goshen_quizzes')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('inbox_messages')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('inbox_messages', 'send_inbox')) {
                    $table->boolean('send_inbox')->default(true)->after('thumbnail');
                }

                if (! Schema::hasColumn('inbox_messages', 'send_email')) {
                    $table->boolean('send_email')->default(false)->after('send_push');
                }

                if (! Schema::hasColumn('inbox_messages', 'email_sent_count')) {
                    $table->unsignedInteger('email_sent_count')->default(0)->after('push_last_error');
                }

                if (! Schema::hasColumn('inbox_messages', 'email_failed_count')) {
                    $table->unsignedInteger('email_failed_count')->default(0)->after('email_sent_count');
                }

                if (! Schema::hasColumn('inbox_messages', 'email_sent_at')) {
                    $table->timestamp('email_sent_at')->nullable()->after('email_failed_count');
                }

                if (! Schema::hasColumn('inbox_messages', 'email_last_error')) {
                    $table->text('email_last_error')->nullable()->after('email_sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inbox_messages')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                foreach ([
                    'send_email',
                    'send_inbox',
                    'email_sent_count',
                    'email_failed_count',
                    'email_sent_at',
                    'email_last_error',
                ] as $column) {
                    if (Schema::hasColumn('inbox_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        foreach (['inbox_messages', 'email_notifications'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ([
                    'goshen_payment_filter',
                    'goshen_paid_from',
                    'goshen_paid_until',
                    'goshen_recent_days',
                    'goshen_paid_week',
                    'goshen_paid_month',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn($tableName, 'goshen_event_id')) {
                    $table->dropConstrainedForeignId('goshen_event_id');
                }

                if (Schema::hasColumn($tableName, 'fundraising_campaign_id')) {
                    $table->dropConstrainedForeignId('fundraising_campaign_id');
                }

                if (Schema::hasColumn($tableName, 'goshen_quiz_id')) {
                    $table->dropConstrainedForeignId('goshen_quiz_id');
                }
            });
        }
    }
};
