<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BibleVersion;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ChurchEvent;
use App\Models\ChurchGroup;
use App\Models\ContactRecipient;
use App\Models\ContentPage;
use App\Models\GalleryImage;
use App\Models\InboxMessage;
use App\Models\MediaItem;
use App\Models\MobileUser;
use App\Models\TransportationArrangement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompatibilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_endpoint_keeps_legacy_status_shape(): void
    {
        $this->seed();

        $this->postJson('/discover', ['data' => []])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'slider_media' => [
                    '*' => [
                        'id',
                        'category',
                        'cover_photo',
                        'source',
                        'stream',
                        'download',
                        'can_preview',
                        'can_download',
                        'is_free',
                        'video_type',
                        'likes_count',
                        'views_count',
                        'comments_count',
                    ],
                ],
                'update_banners',
                'livestream',
                'radios',
                'website_url',
                'facebook_page',
                'youtube_page',
                'tiktok_page',
                'instagram_page',
                'telegram_page',
                'mixlr_page',
                'whatsapp_page',
                'donation_accounts',
            ]);
    }

    public function test_donation_accounts_endpoint_is_retired_for_stripe_only_goshen_giving(): void
    {
        $this->seed();

        $this->getJson('/donation_accounts')
            ->assertGone()
            ->assertJsonPath('status', 'retired');

        $this->postJson('/discover', ['data' => []])
            ->assertOk()
            ->assertJsonPath('donation_accounts', []);
    }

    public function test_google_auth_rejects_unknown_google_email_without_creating_member(): void
    {
        AppSetting::query()->updateOrCreate(['key' => 'google_login_enabled'], ['value' => '1']);
        AppSetting::query()->updateOrCreate(['key' => 'google_web_client_id'], ['value' => 'web-client-id.apps.googleusercontent.com']);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'web-client-id.apps.googleusercontent.com',
                'sub' => 'google-user-123',
                'email' => 'new.google.member@example.test',
                'email_verified' => true,
                'name' => 'New Google Member',
                'picture' => 'https://example.test/avatar.png',
            ]),
        ]);

        $response = $this->postJson('/api/googleAuth', [
            'id_token' => 'fake-google-token',
        ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('mobile_users', [
            'email' => 'new.google.member@example.test',
        ]);
    }

    public function test_google_auth_links_existing_member_and_returns_database_triumphant_id(): void
    {
        AppSetting::query()->updateOrCreate(['key' => 'google_login_enabled'], ['value' => '1']);
        AppSetting::query()->updateOrCreate(['key' => 'google_web_client_id'], ['value' => 'web-client-id.apps.googleusercontent.com']);

        $user = MobileUser::query()->create([
            'name' => 'Existing Member',
            'email' => 'existing.google.member@example.test',
            'login_type' => 'email',
            'is_verified' => true,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'web-client-id.apps.googleusercontent.com',
                'sub' => 'google-user-123',
                'email' => 'existing.google.member@example.test',
                'email_verified' => true,
                'name' => 'Existing Google Member',
                'picture' => 'https://example.test/avatar.png',
            ]),
        ]);

        $response = $this->postJson('/api/googleAuth', [
            'id_token' => 'fake-google-token',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'existing.google.member@example.test')
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('profile_needs_update', true);

        $user->refresh();

        $this->assertSame($user->id, $response->json('user.id'));
        $this->assertSame('google-user-123', $user->google_id);
        $this->assertSame('email', $user->login_type);
        $this->assertNotNull($user->triumphant_id);
        $this->assertSame($user->triumphant_id, $response->json('user.triumphant_id'));
        $this->assertDatabaseHas('mobile_users', [
            'id' => $response->json('user.id'),
            'email' => 'existing.google.member@example.test',
            'triumphant_id' => $response->json('user.triumphant_id'),
        ]);
    }

    public function test_manage_groups_requires_assigned_group_leadership(): void
    {
        Role::findOrCreate('Group leader', 'mobile');
        Role::findOrCreate('Assistant group leader', 'mobile');

        $plainToken = 'plain-token';
        $plainUser = MobileUser::create([
            'name' => 'Regular Member',
            'email' => 'regular@example.test',
            'login_type' => 'email',
            'api_token_hash' => hash('sha256', $plainToken),
            'is_verified' => true,
        ]);

        $this->postJson('/church_groups/manage', [
            'data' => ['email' => $plainUser->email, 'api_token' => $plainToken],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only assigned group leaders and assistant group leaders can manage groups.');

        $leaderToken = 'leader-token';
        $leader = MobileUser::create([
            'name' => 'Group Leader',
            'email' => 'leader@example.test',
            'login_type' => 'email',
            'api_token_hash' => hash('sha256', $leaderToken),
            'is_verified' => true,
        ]);
        $leader->assignRole('Group leader');

        $assistantToken = 'assistant-token';
        $assistant = MobileUser::create([
            'name' => 'Assistant Leader',
            'email' => 'assistant@example.test',
            'login_type' => 'email',
            'api_token_hash' => hash('sha256', $assistantToken),
            'is_verified' => true,
        ]);
        $assistant->assignRole('Assistant group leader');

        ChurchGroup::create([
            'name' => 'Care Group',
            'leader_id' => $leader->id,
            'assistant_id' => $assistant->id,
            'is_active' => true,
        ]);

        $this->postJson('/church_groups/manage', [
            'data' => ['email' => $leader->email, 'api_token' => $leaderToken],
        ])
            ->assertOk()
            ->assertJsonPath('groups.0.name', 'Care Group');

        $this->postJson('/church_groups/manage', [
            'data' => ['email' => $assistant->email, 'api_token' => $assistantToken],
        ])
            ->assertOk()
            ->assertJsonPath('groups.0.name', 'Care Group');
    }

    public function test_bible_versions_endpoint_exposes_downloadable_json_sources(): void
    {
        BibleVersion::create([
            'name' => 'King James Version',
            'shortcode' => 'KJV',
            'description' => 'Classic English Bible translation.',
            'json_path' => 'bibles/tbl_KJV.json',
            'is_active' => true,
        ]);

        BibleVersion::create([
            'name' => 'Hidden Version',
            'shortcode' => 'HID',
            'json_path' => 'bibles/hidden.json',
            'is_active' => false,
        ]);

        $this->getJson('/getBibleVersions')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(1, 'versions')
            ->assertJsonPath('versions.0.shortcode', 'KJV')
            ->assertJsonPath('versions.0.source', url('/storage/bibles/tbl_KJV.json'));
    }

    public function test_public_media_urls_are_absolute_for_mobile_clients(): void
    {
        $this->seed();

        $this->postJson('/discover', ['data' => []])
            ->assertOk()
            ->assertJsonPath('app_logo', null)
            ->assertJsonPath('slider_media.0.cover_photo', url('/storage/reference/header.jpg'));
    }

    public function test_media_endpoints_emit_flutter_compatible_payloads(): void
    {
        $this->seed();

        $this->postJson('/fetch_media', ['data' => ['media_type' => 'audio', 'page' => 0]])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('media.0.type', 'audio')
            ->assertJsonPath('media.0.can_download', 0)
            ->assertJsonStructure(['media' => [['source', 'cover_photo', 'stream', 'download_url']]]);

        $this->postJson('/search', ['data' => ['query' => 'Sunday', 'page' => 0]])
            ->assertOk()
            ->assertJsonStructure(['search']);
    }

    public function test_uploaded_video_source_type_maps_to_legacy_flutter_video_type(): void
    {
        $this->seed();

        MediaItem::create([
            'category_id' => Category::where('slug', 'videos')->value('id'),
            'type' => 'video',
            'title' => 'Uploaded Service',
            'source_type' => 'upload',
            'source' => 'media/library/service.mp4',
            'duration' => 120,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->postJson('/fetch_media', ['data' => ['media_type' => 'video', 'page' => 0]])
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Uploaded Service',
                'video_type' => 'mp4_video',
            ]);
    }

    public function test_update_banners_are_available_for_home_updates_slider(): void
    {
        $this->seed();

        MediaItem::create([
            'type' => 'banner',
            'title' => 'Conference Banner',
            'description' => 'Home updates banner',
            'source_type' => 'none',
            'cover_photo' => 'reference/header.jpg',
            'duration' => 0,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->postJson('/discover', ['data' => []])
            ->assertOk()
            ->assertJsonPath('update_banners.0.title', 'Conference Banner')
            ->assertJsonPath('update_banners.0.cover_photo', url('/storage/reference/header.jpg'))
            ->assertJsonPath('update_banners.0.type', 'banner');
    }

    public function test_home_slider_includes_upcoming_events_and_featured_media(): void
    {
        $this->seed();

        ChurchEvent::create([
            'title' => 'Upcoming Visitation',
            'details' => '<p>Join us.</p>',
            'thumbnail' => 'reference/header.jpg',
            'starts_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        MediaItem::create([
            'type' => 'video',
            'title' => 'Featured Video',
            'source_type' => 'youtube_video',
            'source' => 'abc123',
            'cover_photo' => 'reference/header.jpg',
            'duration' => 60,
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->postJson('/discover', ['data' => []])
            ->assertOk()
            ->assertJsonFragment(['title' => 'Upcoming Visitation', 'type' => 'event'])
            ->assertJsonFragment(['title' => 'Featured Video', 'type' => 'video']);
    }

    public function test_gallery_endpoint_exposes_active_images_by_category(): void
    {
        GalleryImage::create([
            'title' => 'Sunday Service',
            'category' => 'Services',
            'image_path' => 'gallery/sunday.jpg',
            'is_active' => true,
            'sort_order' => 1,
            'published_at' => now(),
        ]);

        GalleryImage::create([
            'title' => 'Hidden',
            'category' => 'Services',
            'image_path' => 'gallery/hidden.jpg',
            'is_active' => false,
        ]);

        $this->getJson('/gallery_images')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('categories.0', 'Services')
            ->assertJsonPath('gallery.0.title', 'Sunday Service')
            ->assertJsonPath('gallery.0.image_url', url('/storage/gallery/sunday.jpg'))
            ->assertJsonMissing(['title' => 'Hidden']);
    }

    public function test_events_endpoint_filters_by_selected_date_and_uses_flutter_shape(): void
    {
        $this->seed();

        ChurchEvent::create([
            'title' => 'Midweek Mercy Service',
            'details' => '<p>Prayer and teaching.</p>',
            'venue' => 'Main Auditorium',
            'thumbnail' => 'reference/header.jpg',
            'starts_at' => '2026-05-19 18:00:00',
            'ends_at' => '2026-05-19 20:00:00',
            'is_published' => true,
        ]);

        ChurchEvent::create([
            'title' => 'Different Day Service',
            'starts_at' => '2026-05-20 18:00:00',
            'is_published' => true,
        ]);

        $this->postJson('/fetch_events', ['data' => ['date' => '2026-05-19']])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('events.0.title', 'Midweek Mercy Service')
            ->assertJsonPath('events.0.date', '2026-05-19')
            ->assertJsonPath('events.0.time', '6:00 PM')
            ->assertJsonPath('events.0.thumbnail', url('/storage/reference/header.jpg'))
            ->assertJsonPath('events.0.registration_availability', 'everywhere')
            ->assertJsonMissing(['title' => 'Different Day Service']);
    }

    public function test_events_endpoint_expands_weekly_programmes_for_selected_date(): void
    {
        $event = ChurchEvent::create([
            'title' => 'Sunday Celebration Service',
            'details' => '<p>Weekly worship service.</p>',
            'venue' => 'Main Auditorium',
            'thumbnail' => 'reference/header.jpg',
            'starts_at' => '2026-01-04 09:00:00',
            'ends_at' => '2026-01-04 11:30:00',
            'recurrence_type' => ChurchEvent::RECURRENCE_WEEKLY,
            'recurrence_interval' => 1,
            'recurrence_weekday' => 0,
            'recurrence_until' => '2026-12-31',
            'is_published' => true,
        ]);

        $this->postJson('/fetch_events', ['data' => ['date' => '2026-07-05']])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('events.0.title', 'Sunday Celebration Service')
            ->assertJsonPath('events.0.date', '2026-07-05')
            ->assertJsonPath('events.0.time', '9:00 AM')
            ->assertJsonPath('events.0.starts_at', '2026-07-05 09:00:00')
            ->assertJsonPath('events.0.ends_at', '2026-07-05 11:30:00')
            ->assertJsonPath('events.0.recurring_parent_id', $event->id)
            ->assertJsonPath('events.0.recurrence_label', 'Every Sunday');

        $this->postJson('/fetch_events', ['data' => ['date' => '2026-07-06']])
            ->assertOk()
            ->assertJsonPath('events', []);
    }

    public function test_events_endpoint_expands_monthly_last_weekday_programmes(): void
    {
        $event = ChurchEvent::create([
            'title' => 'Last Sunday Thanksgiving',
            'details' => '<p>Monthly thanksgiving service.</p>',
            'venue' => 'Main Auditorium',
            'thumbnail' => 'reference/header.jpg',
            'starts_at' => '2026-01-25 17:00:00',
            'ends_at' => '2026-01-25 19:00:00',
            'recurrence_type' => ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY,
            'recurrence_interval' => 1,
            'recurrence_weekday' => 0,
            'recurrence_week_of_month' => -1,
            'is_published' => true,
        ]);

        $this->postJson('/fetch_events', ['data' => ['date' => '2026-07-26']])
            ->assertOk()
            ->assertJsonPath('events.0.title', 'Last Sunday Thanksgiving')
            ->assertJsonPath('events.0.date', '2026-07-26')
            ->assertJsonPath('events.0.starts_at', '2026-07-26 17:00:00')
            ->assertJsonPath('events.0.recurring_parent_id', $event->id)
            ->assertJsonPath('events.0.recurrence_label', 'Last Sunday of the month');
    }

    public function test_branches_endpoint_uses_flutter_safe_shape(): void
    {
        Branch::create([
            'name' => 'Lagos Branch',
            'pastor' => null,
            'phone' => null,
            'email' => null,
            'address' => 'Lagos, Nigeria',
            'latitude' => 6.5244,
            'longitude' => 3.3792,
            'is_active' => true,
        ]);

        $this->getJson('/church_branches')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('branches.0.name', 'Lagos Branch')
            ->assertJsonPath('branches.0.pastor', '')
            ->assertJsonPath('branches.0.email', '')
            ->assertJsonPath('branches.0.latitude', 6.5244)
            ->assertJsonPath('branches.0.longitude', 3.3792);
    }

    public function test_pastors_endpoint_lists_only_pastor_role_users_with_profile_images(): void
    {
        $pastorRole = Role::firstOrCreate([
            'name' => 'Pastor',
            'guard_name' => 'mobile',
        ]);
        $discipleRole = Role::firstOrCreate([
            'name' => 'Disciple',
            'guard_name' => 'mobile',
        ]);

        $pastor = MobileUser::create([
            'name' => 'Pastor John Ade',
            'email' => 'pastor@example.com',
            'phone' => '+2348012345678',
            'password' => Hash::make('Passw0rd!234'),
            'avatar' => 'mobile-users/avatars/pastor.jpg',
            'role_title' => 'Resident Pastor',
            'sort_order' => 1,
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $pastor->assignRole($pastorRole);

        $hiddenPastor = MobileUser::create([
            'name' => 'Pastor No Image',
            'email' => 'pastor-no-image@example.com',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $hiddenPastor->assignRole($pastorRole);

        $disciple = MobileUser::create([
            'name' => 'Disciple With Image',
            'email' => 'disciple@example.com',
            'password' => Hash::make('Passw0rd!234'),
            'avatar' => 'mobile-users/avatars/disciple.jpg',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $disciple->assignRole($discipleRole);

        $this->getJson('/church_pastors')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('pastors.0.name', 'Pastor John Ade')
            ->assertJsonPath('pastors.0.image_url', url('/storage/mobile-users/avatars/pastor.jpg'))
            ->assertJsonPath('pastors.0.phone_number', '+2348012345678')
            ->assertJsonPath('pastors.0.role_title', 'Resident Pastor')
            ->assertJsonMissing(['name' => 'Pastor No Image'])
            ->assertJsonMissing(['name' => 'Disciple With Image']);
    }

    public function test_contact_form_stores_message_for_admin_and_returns_success(): void
    {
        ContactRecipient::create([
            'name' => 'Church Office',
            'email' => 'office@example.com',
            'is_active' => true,
        ]);

        $this->postJson('/submit_contact', [
            'data' => [
                'name' => 'Visitor',
                'email' => 'visitor@example.com',
                'phone' => '+2348000000000',
                'subject' => 'Need help',
                'message' => 'Please contact me about the programme.',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('contact_message.email', 'visitor@example.com');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'visitor@example.com',
            'subject' => 'Need help',
        ]);
    }

    public function test_transportation_arrangements_endpoint_exposes_active_72hours_pickup_details(): void
    {
        TransportationArrangement::create([
            'program_name' => '72Hours',
            'city_town' => 'Ibadan',
            'state' => 'Oyo State',
            'bus_location' => 'Iwo Road Bus Terminal',
            'bus_type' => 'Coaster Bus',
            'passenger_capacity' => 32,
            'driver_name' => 'Mr. John Ade',
            'driver_phone' => '+2348012345678',
            'contact_person_name' => 'Bro. Michael',
            'contact_person_phone' => '+2348098765432',
            'is_active' => true,
        ]);

        TransportationArrangement::create([
            'program_name' => '72Hours',
            'city_town' => 'Hidden City',
            'bus_location' => 'Inactive pickup',
            'is_active' => false,
        ]);

        $this->getJson('/transportation_arrangements')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('transportation_arrangements.0.program_name', '72Hours')
            ->assertJsonPath('transportation_arrangements.0.city_town', 'Ibadan')
            ->assertJsonPath('transportation_arrangements.0.passenger_capacity', 32)
            ->assertJsonPath('transportation_arrangements.0.driver_phone', '+2348012345678')
            ->assertJsonMissing(['city_town' => 'Hidden City']);
    }

    public function test_church_groups_endpoint_exposes_leaders_assistants_and_members(): void
    {
        $leader = MobileUser::create([
            'name' => 'Choir Leader',
            'email' => 'leader@example.com',
            'avatar' => 'mobile-users/avatars/leader.jpg',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $assistant = MobileUser::create([
            'name' => 'Assistant Leader',
            'email' => 'assistant@example.com',
            'avatar' => 'mobile-users/avatars/assistant.jpg',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $group = ChurchGroup::updateOrCreate(['name' => 'Choir'], [
            'functions' => 'Worship and special ministrations.',
            'leader_id' => $leader->id,
            'assistant_id' => $assistant->id,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        MobileUser::create([
            'name' => 'Choir Member',
            'email' => 'member@example.com',
            'password' => Hash::make('Passw0rd!234'),
            'group_id' => $group->id,
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->getJson('/church_groups')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonFragment([
                'name' => 'Choir',
                'leader_name' => 'Choir Leader',
                'leader_avatar' => url('/storage/mobile-users/avatars/leader.jpg'),
                'assistant_name' => 'Assistant Leader',
                'assistant_avatar' => url('/storage/mobile-users/avatars/assistant.jpg'),
                'members_count' => 1,
            ])
            ->assertJsonFragment(['name' => 'Choir Member']);
    }

    public function test_inbox_endpoint_exposes_app_messages_without_firebase(): void
    {
        InboxMessage::create([
            'title' => 'Service Notice',
            'content' => '<p>Join us tonight.</p>',
            'thumbnail' => 'inbox/notice.jpg',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->getJson('/fetch_inbox')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('inbox.0.title', 'Service Notice')
            ->assertJsonPath('inbox.0.message', '<p>Join us tonight.</p>')
            ->assertJsonPath('inbox.0.thumbnail', url('/storage/inbox/notice.jpg'));
    }

    public function test_mobile_registration_and_profile_payload_include_group(): void
    {
        $group = ChurchGroup::updateOrCreate(['name' => 'Ushering'], [
            'functions' => 'Service flow support.',
            'is_active' => true,
        ]);

        $this->postJson('/registerUser', [
            'data' => [
                'name' => 'Member One',
                'first_name' => 'Member',
                'last_name' => 'One',
                'email' => 'member-one@example.com',
                'phone' => '+2348000000000',
                'gender' => 'Female',
                'member_type' => 'church_member',
                'group_id' => $group->id,
                'country_of_residence' => 'Nigeria',
                'state_county_province' => 'Lagos',
                'address' => '1 Mercy Road, Lagos',
                'password' => 'Passw0rd!234',
            ],
        ])->assertOk()->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('mobile_users', [
            'email' => 'member-one@example.com',
            'group_id' => $group->id,
            'member_type' => 'church_member',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
        ]);
    }

    public function test_content_page_endpoint_exposes_structured_about_page(): void
    {
        ContentPage::create([
            'type' => 'about',
            'title' => 'About MFM Triumphant Church',
            'slug' => 'about-covenant-of-mercy',
            'body' => '<p>Welcome.</p>',
            'hero_image' => 'reference/header.jpg',
            'sections' => [
                ['title' => 'Our Mission', 'body' => '<p>Mercy.</p>', 'image' => 'reference/header.jpg', 'sort_order' => 1],
            ],
            'is_published' => true,
        ]);

        $this->getJson('/content_page/about')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('page.title', 'About MFM Triumphant Church')
            ->assertJsonPath('page.hero_image', url('/storage/reference/header.jpg'))
            ->assertJsonPath('page.sections.0.image', url('/storage/reference/header.jpg'));
    }

    public function test_verified_mobile_user_can_submit_app_suggestion(): void
    {
        $user = MobileUser::create([
            'name' => 'Verified Member',
            'email' => 'verified@example.com',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => true,
        ]);
        $token = $user->issueApiToken();

        $this->postJson('/submit_suggestion', [
            'data' => [
                'api_token' => $token,
                'subject' => 'Home idea',
                'message' => 'Please add more event reminders.',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggestion.sender_email', 'verified@example.com');

        $this->assertDatabaseHas('app_suggestions', [
            'sender_email' => 'verified@example.com',
            'subject' => 'Home idea',
        ]);
    }

    public function test_youtube_urls_saved_as_external_video_links_play_as_youtube_videos(): void
    {
        $this->seed();

        MediaItem::create([
            'category_id' => Category::where('slug', 'videos')->value('id'),
            'type' => 'video',
            'title' => 'YouTube Full URL',
            'source_type' => 'external_url',
            'source' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'duration' => 120,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->postJson('/fetch_media', ['data' => ['media_type' => 'video', 'page' => 0]])
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'YouTube Full URL',
                'video_type' => 'youtube_video',
            ]);
    }

    public function test_versioned_health_endpoint_is_available(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_mobile_registration_and_login_return_flutter_friendly_messages(): void
    {
        $group = ChurchGroup::updateOrCreate(['name' => 'No group'], [
            'functions' => 'Default registration option.',
            'is_active' => true,
        ]);

        $this->postJson('/registerUser', [
            'data' => [
                'email' => 'member@example.com',
                'name' => 'Church Member',
                'first_name' => 'Church',
                'last_name' => 'Member',
                'phone' => '+2348000000000',
                'gender' => 'Female',
                'member_type' => 'church_member',
                'group_id' => $group->id,
                'country_of_residence' => 'Nigeria',
                'state_county_province' => 'Lagos',
                'address' => '1 Mercy Road, Lagos',
                'password' => 'Passw0rd!234',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('needs_verification', true)
            ->assertJsonPath('email', 'member@example.com');

        $this->assertDatabaseHas('mobile_users', [
            'email' => 'member@example.com',
            'phone' => '+2348000000000',
            'gender' => 'Female',
            'member_type' => 'church_member',
            'group_id' => $group->id,
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
        ]);

        $this->postJson('/loginUser', [
            'data' => [
                'email' => 'member@example.com',
                'password' => 'Passw0rd!234',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('needs_verification', true);

        MobileUser::where('email', 'member@example.com')->update([
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/loginUser', [
            'data' => [
                'email' => 'member@example.com',
                'password' => 'Passw0rd!234',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Signed in successfully.');

        $this->postJson('/loginUser', [
            'data' => [
                'email' => 'member@example.com',
                'password' => 'wrong-password',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_mobile_user_can_update_profile_without_required_images_or_social_fields(): void
    {
        $group = ChurchGroup::updateOrCreate(['name' => 'Choir'], [
            'functions' => 'Worship ministry.',
            'is_active' => true,
        ]);

        $user = MobileUser::create([
            'name' => 'Profile User',
            'email' => 'profile@example.com',
            'phone' => '+2348000000000',
            'gender' => 'Male',
            'member_type' => 'church_member',
            'group_id' => $group->id,
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/updateProfile', [
            'email' => 'profile@example.com',
            'fullname' => 'Updated Profile User',
            'phone' => '+2348111111111',
            'gender' => 'Female',
            'member_type' => 'visitor',
            'group_id' => $group->id,
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Abuja',
            'address' => '22 Mercy Avenue, Abuja',
            'about_me' => base64_encode('Serving in mercy.'),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('user.name', 'Updated Profile User')
            ->assertJsonPath('user.gender', 'Female')
            ->assertJsonPath('user.phone', '+2348111111111')
            ->assertJsonPath('user.state_county_province', 'Abuja');

        $this->assertDatabaseHas('mobile_users', [
            'id' => $user->id,
            'name' => 'Updated Profile User',
            'gender' => 'Female',
            'phone' => '+2348111111111',
            'group_id' => $group->id,
            'member_type' => 'visitor',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Abuja',
            'address' => '22 Mercy Avenue, Abuja',
        ]);
    }

    public function test_mobile_email_verification_and_password_reset_flow(): void
    {
        $user = MobileUser::create([
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => false,
        ]);

        $verificationCode = 'ABC123';
        $user->forceFill([
            'email_verification_code_hash' => Hash::make($verificationCode),
            'email_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson('/verifyMobileEmail', [
            'data' => ['email' => 'pending@example.com', 'code' => $verificationCode],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['user' => ['api_token']]);

        $resetCode = 'RST123';
        $user->refresh()->forceFill([
            'password_reset_code_hash' => Hash::make($resetCode),
            'password_reset_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson('/resetMobilePassword', [
            'data' => [
                'email' => 'pending@example.com',
                'code' => $resetCode,
                'password' => 'NewPassw0rd!234',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson('/loginUser', [
            'data' => ['email' => 'pending@example.com', 'password' => 'NewPassw0rd!234'],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
