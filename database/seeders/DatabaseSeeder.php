<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\BibleVersion;
use App\Models\Category;
use App\Models\ChurchEvent;
use App\Models\ContentPage;
use App\Models\Devotional;
use App\Models\DonationAccountCategory;
use App\Models\DonationBankAccount;
use App\Models\DonationCategory;
use App\Models\MediaItem;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            'super_admin' => ['*'],
            'content_manager' => ['manage_content', 'manage_media', 'manage_settings'],
            'moderator' => [
                'moderate_comments',
                'manage_mobile_users',
                'create_mobile_users',
                'update_mobile_users',
                'delete_mobile_users',
            ],
            'finance' => ['view_donations', 'manage_donations'],
        ];

        foreach (collect($roles)->flatten()->unique() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach ($roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name])->syncPermissions($permissions);
        }

        $goPermission = Permission::firstOrCreate(['name' => 'manage_prophetic_decree', 'guard_name' => 'mobile']);
        Role::firstOrCreate(['name' => 'G.O', 'guard_name' => 'mobile'])->syncPermissions([$goPermission]);
        foreach (['Pastor', 'Disciple', 'Group leader', 'Assistant group leader'] as $mobileRole) {
            Role::firstOrCreate(['name' => $mobileRole, 'guard_name' => 'mobile']);
        }

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@church.local')],
            [
                'name' => env('ADMIN_NAME', 'Church Super Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
            ],
        );
        $admin->assignRole('super_admin');

        foreach ([
            ['general', 'app_name', 'MFM Triumphant Church', false],
            ['general', 'website_url', '', false],
            ['social', 'facebook_page', '', false],
            ['social', 'youtube_page', '', false],
            ['social', 'tiktok_page', '', false],
            ['social', 'instagram_page', '', false],
            ['social', 'telegram_page', '', false],
            ['social', 'mixlr_page', '', false],
            ['social', 'whatsapp_page', '', false],
            ['mobile', 'ads_interval', '0', false],
            ['donations', 'currency', 'NGN', false],
            ['donations', 'paypal_link', '', false],
            ['donations', 'goshen_stripe_giving_enabled', '1', false],
            ['branding', 'app_logo', '', false],
            ['firebase', 'service_account_path', '', true],
        ] as [$group, $key, $value, $secret]) {
            AppSetting::firstOrCreate(['key' => $key], [
                'group' => $group,
                'value' => $value,
                'is_secret' => $secret,
            ]);
        }

        foreach ([
            ['privacy', 'Privacy Policy'],
            ['terms', 'Terms of Use'],
            ['about', 'About MFM Triumphant Church'],
        ] as [$type, $title]) {
            ContentPage::firstOrCreate(['type' => $type], [
                'title' => $title,
                'slug' => $type,
                'body' => '',
                'is_published' => true,
            ]);
        }

        $this->seedReferenceMediaContent();
        $this->seedBibleVersions();
        $this->seedDonationBankAccounts();
        $this->seedDonationCategories();
    }

    private function seedReferenceMediaContent(): void
    {
        $cover = $this->copyReferenceAsset('assets/images/header.jpg', 'reference/header.jpg')
            ?? $this->copyReferenceAsset('assets/images/logo.png', 'reference/logo.png');

        $audio = Category::firstOrCreate(['slug' => 'sermons'], [
            'name' => 'Sermons',
            'description' => 'Audio messages and sermon recordings.',
            'thumbnail' => $cover,
            'sort_order' => 10,
        ]);

        $video = Category::firstOrCreate(['slug' => 'videos'], [
            'name' => 'Videos',
            'description' => 'Video messages, service replays, and ministry media.',
            'thumbnail' => $cover,
            'sort_order' => 20,
        ]);

        $music = Category::firstOrCreate(['slug' => 'music'], [
            'name' => 'Music',
            'description' => 'Worship, choir, and music ministration.',
            'thumbnail' => $cover,
            'sort_order' => 30,
        ]);

        MediaItem::updateOrCreate(['title' => 'Sunday Worship Audio'], [
            'category_id' => $audio->id,
            'type' => 'audio',
            'description' => 'Reference audio entry modeled after the original admin MP3 upload flow.',
            'source_type' => 'external_url',
            'source' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
            'cover_photo' => $cover,
            'duration' => 372,
            'can_download' => true,
            'can_preview' => true,
            'preview_duration' => 60,
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        MediaItem::updateOrCreate(['title' => 'Service Replay Video'], [
            'category_id' => $video->id,
            'type' => 'video',
            'description' => 'Reference video entry supporting uploaded MP4s, YouTube/Vimeo IDs, M3U8, and DASH URLs.',
            'source_type' => 'youtube_video',
            'source' => 'dQw4w9WgXcQ',
            'cover_photo' => $cover,
            'duration' => 213,
            'can_download' => false,
            'can_preview' => false,
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        MediaItem::updateOrCreate(['title' => 'Choir Worship Moment'], [
            'category_id' => $music->id,
            'type' => 'music',
            'description' => 'Reference music entry for worship audio content.',
            'source_type' => 'external_url',
            'source' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
            'cover_photo' => $cover,
            'duration' => 345,
            'can_download' => true,
            'can_preview' => true,
            'preview_duration' => 45,
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        if ($cover !== null) {
            MediaItem::whereNull('cover_photo')->update(['cover_photo' => $cover]);
        }

        Stream::firstOrCreate(['type' => 'livestream', 'title' => 'Main Church Livestream'], [
            'description' => 'Primary live service stream URL for the Flutter discover screen.',
            'stream_url' => 'https://www.youtube.com/@covenantofmercychurch/live',
            'thumbnail' => $cover,
            'is_active' => true,
        ]);

        Stream::firstOrCreate(['type' => 'radio', 'title' => 'Church Radio'], [
            'description' => 'Radio stream entry matching the original radio admin concept.',
            'stream_url' => 'https://stream-ssl.radiojar.com/0tpy1h0kxtzuv',
            'thumbnail' => $cover,
            'is_active' => true,
        ]);

        Devotional::firstOrCreate(['date' => now()->toDateString()], [
            'title' => 'Mercy for Today',
            'author' => 'MFM Triumphant Church',
            'content' => '<p>Seed devotional content for validating image-backed daily content publishing.</p>',
            'thumbnail' => $cover,
            'is_published' => true,
        ]);

        ChurchEvent::firstOrCreate(['title' => 'Sunday Celebration Service'], [
            'details' => '<p>Weekly worship service with media artwork attached.</p>',
            'venue' => 'Main Auditorium',
            'thumbnail' => $cover,
            'starts_at' => now()->next('Sunday')->setTime(9, 0),
            'ends_at' => now()->next('Sunday')->setTime(11, 30),
            'is_published' => true,
        ]);
    }

    private function seedDonationCategories(): void
    {
        foreach ([
            ['Offering', 'offering', 'Regular offering and worship gifts.', 10],
            ['Tithe', 'tithe', 'Tithe giving.', 20],
            ['Donation', 'donation', 'General donation and support.', 30],
            ['Special Giving', 'special-giving', 'Special thanksgiving, project, and programme giving.', 40],
        ] as [$name, $slug, $description, $sortOrder]) {
            DonationCategory::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        }
    }

    private function copyReferenceAsset(string $source, string $target): ?string
    {
        $referencePath = base_path('../ChurchApp-Web-Project/'.$source);

        if (! is_file($referencePath)) {
            return null;
        }

        Storage::disk('public')->put($target, file_get_contents($referencePath));

        return $target;
    }

    private function seedBibleVersions(): void
    {
        foreach ([
            [2, 'King James Version', 'KJV', 'An English translation of the Christian Bible for the Church of England.', 'tbl_KJV.json'],
            [3, 'The Message Bible', 'MSG', 'Bible in Contemporary Language is a highly idiomatic translation by Eugene H.', 'tbl_MSG.json'],
            [4, 'New International Version', 'NIV', 'First published in 1978 by Bible scholars using the earliest, highest quality manuscripts available.', 'tbl_NIV.json'],
            [5, 'New King James Version', 'NKJV', 'English translation of the Bible first published in 1982 by Thomas Nelson.', 'tbl_NKJV.json'],
            [6, 'Amplified Bible', 'AMP', 'English language translation of the Bible produced jointly by Zondervan and The Lockman Foundation.', 'tbl_AMP.json'],
            [7, 'New Living Translation', 'NLT', 'A revision of The Living Bible, the project evolved into a new English translation from Hebrew and Greek texts.', 'tbl_NLT.json'],
            [8, 'New Revised Standard Version', 'NRSV', 'Published in 1989 by the National Council of Churches. It is a revision of the Revised Standard Version.', 'tbl_NRSV.json'],
        ] as [$legacyId, $name, $shortcode, $description, $file]) {
            $jsonPath = $this->copyReferenceAsset("uploads/{$file}", "bibles/{$file}");
            BibleVersion::updateOrCreate(
                ['shortcode' => $shortcode],
                [
                    'name' => $name,
                    'description' => $description,
                    'json_path' => $jsonPath,
                    'is_active' => $jsonPath !== null,
                    'legacy_id' => $legacyId,
                ],
            );
        }
    }

    private function seedDonationBankAccounts(): void
    {
        $categories = [
            ['Naira account', 'naira-account', 'NGN', 'NG', '🇳🇬', '#16a34a'],
            ['US dollar account', 'us-dollar-account', 'USD', 'US', '🇺🇸', '#2563eb'],
            ['British Pounds account', 'british-pounds-account', 'GBP', 'GB', '🇬🇧', '#7c3aed'],
            ['Canadian dollar account', 'canadian-dollar-account', 'CAD', 'CA', '🇨🇦', '#dc2626'],
            ['Euro account', 'euro-account', 'EUR', 'EU', '🇪🇺', '#1d4ed8'],
        ];

        foreach ($categories as $index => [$name, $slug, $currency, $country, $flag, $color]) {
            $category = DonationAccountCategory::firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'currency_code' => $currency,
                'country_code' => $country,
                'flag_icon' => $flag,
                'color' => $color,
                'sort_order' => $index,
                'is_active' => true,
            ]);

            DonationBankAccount::firstOrCreate([
                'donation_account_category_id' => $category->id,
                'account_number' => '0000000000',
            ], [
                'bank_name' => 'Update bank name',
                'account_name' => 'MFM Triumphant Church',
                'instructions' => 'Replace this placeholder with the correct '.$currency.' giving account details.',
                'sort_order' => $index,
                'is_active' => false,
            ]);
        }
    }
}
