<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'counseling_enabled'],
            [
                'group' => 'features',
                'value' => '1',
                'description' => 'Turn private Counseling requests and pastoral care chat on or off in the app and admin.',
                'is_secret' => false,
            ],
        );
    }

    public function down(): void
    {
        AppSetting::query()->where('key', 'counseling_enabled')->delete();
    }
};
