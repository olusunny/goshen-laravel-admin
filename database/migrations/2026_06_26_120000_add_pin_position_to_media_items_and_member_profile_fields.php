<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('media_items', 'pin_position', function (Blueprint $table): void {
            $table->unsignedTinyInteger('pin_position')->nullable()->after('is_featured')->index();
        });

        $this->addColumnIfMissing('mobile_users', 'first_name', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
        });

        $this->addColumnIfMissing('mobile_users', 'middle_name', function (Blueprint $table): void {
            $table->string('middle_name')->nullable()->after('first_name');
        });

        $this->addColumnIfMissing('mobile_users', 'last_name', function (Blueprint $table): void {
            $table->string('last_name')->nullable()->after('middle_name');
        });

        $this->addColumnIfMissing('mobile_users', 'member_type', function (Blueprint $table): void {
            $table->string('member_type', 40)->nullable()->after('group_id')->index();
        });

        $this->addColumnIfMissing('mobile_users', 'address', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('state_county_province');
        });

        $this->addColumnIfMissing('mobile_users', 'address_latitude', function (Blueprint $table): void {
            $table->decimal('address_latitude', 10, 7)->nullable()->after('address');
        });

        $this->addColumnIfMissing('mobile_users', 'address_longitude', function (Blueprint $table): void {
            $table->decimal('address_longitude', 10, 7)->nullable()->after('address_latitude');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('media_items') && Schema::hasColumn('media_items', 'pin_position')) {
            Schema::table('media_items', function (Blueprint $table): void {
                $table->dropColumn('pin_position');
            });
        }

        if (Schema::hasTable('mobile_users')) {
            Schema::table('mobile_users', function (Blueprint $table): void {
                foreach ([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'member_type',
                    'address',
                    'address_latitude',
                    'address_longitude',
                ] as $column) {
                    if (Schema::hasColumn('mobile_users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function addColumnIfMissing(string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }
};
