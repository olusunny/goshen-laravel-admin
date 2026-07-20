<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VerseOfDayResource;
use App\Models\AppSetting;
use App\Models\AppSuggestion;
use App\Models\BibleVersion;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ChurchEvent;
use App\Models\ChurchGroup;
use App\Models\ChurchGroupJoinRequest;
use App\Models\CommunityPrayerRequest;
use App\Models\ContactMessage;
use App\Models\ContactRecipient;
use App\Models\ContentPage;
use App\Models\Devotional;
use App\Models\Donation;
use App\Models\DonationAccountCategory;
use App\Models\DonationCategory;
use App\Models\FcmToken;
use App\Models\GalleryImage;
use App\Models\Hymn;
use App\Models\InboxMessage;
use App\Models\MediaItem;
use App\Models\MobileUser;
use App\Models\PrayerPoint;
use App\Models\Stream;
use App\Models\Testimony;
use App\Models\TransportationArrangement;
use App\Models\VerseOfDay;
use App\Services\AutomaticNotificationService;
use App\Services\DynamicSmtpMailer;
use App\Services\MergedAccountCredentialService;
use App\Services\MessagePersonalizationService;
use App\Services\ProfileImageOptimizer;
use App\Services\RecurringChurchEventService;
use App\Services\TriumphantIdService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Sunny\Fundraising\Contracts\PermissionResolverContract;

class CompatibilityController extends Controller
{
    private const DISCOVER_SETTING_KEYS = [
        'facebook_page',
        'youtube_page',
        'tiktok_page',
        'instagram_page',
        'telegram_page',
        'mixlr_page',
        'whatsapp_page',
        'twitter_page',
        'website_url',
        'ads_interval',
        'app_logo',
        'google_login_enabled',
        'mobile_phone_otp_login_enabled',
        'testimonies_enabled',
        'counseling_enabled',
        'fundraising_enabled',
        'prayer_points_enabled',
        'interactive_prayer_wall_enabled',
        'hymns_enabled',
        'devotionals_enabled',
        'verse_of_day_enabled',
        'transportation_arrangements_enabled',
        'church_groups_enabled',
        'dynamic_forms_enabled',
        'goshen_quiz_enabled',
        'goshen_wallet_withdrawals_enabled',
        'goshen_wallet_auto_topup_enabled',
        'branches_enabled',
        'google_web_client_id',
        'google_android_client_id',
        'google_ios_client_id',
    ];

    public function discover(Request $request)
    {
        $user = $this->mobileUserFromRequest($request);
        $inboxQuery = $this->visibleInboxQuery($user);
        $livestream = Stream::where('type', 'livestream')->where('is_active', true)->first();
        $radio = Stream::where('type', 'radio')->where('is_active', true)->first();
        $settings = AppSetting::query()
            ->whereIn('key', self::DISCOVER_SETTING_KEYS)
            ->pluck('value', 'key');
        $setting = static fn (string $key, mixed $default = null): mixed => $settings->get($key, $default);
        $settingEnabled = static fn (string $key, bool $default = false): bool => filter_var(
            $setting($key, $default ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN,
        );
        $publishedEvents = $this->publishedChurchEventsForExpansion()->get();
        $verseOfDayEnabled = $settingEnabled('verse_of_day_enabled');
        $testimoniesEnabled = $settingEnabled('testimonies_enabled');

        return response()->json([
            'status' => 'ok',
            'slider_media' => $this->homeSliderPayload($publishedEvents),
            'update_banners' => $this->orderedMediaQuery(MediaItem::with(['category', 'subCategory'])
                ->withCount(['comments as comments_count' => fn ($query) => $query->where('is_published', true)])
                ->where('type', 'banner')
                ->where('is_published', true))
                ->limit(10)
                ->get()
                ->map(fn (MediaItem $media) => $this->mediaPayload($media)),
            'livestream' => $livestream ? $this->streamPayload($livestream) : null,
            'radios' => $radio ? $this->streamPayload($radio) : null,
            'facebook_page' => $setting('facebook_page', ''),
            'youtube_page' => $setting('youtube_page', ''),
            'tiktok_page' => $setting('tiktok_page', ''),
            'instagram_page' => $setting('instagram_page', ''),
            'telegram_page' => $setting('telegram_page', ''),
            'mixlr_page' => $setting('mixlr_page', ''),
            'whatsapp_page' => $setting('whatsapp_page', ''),
            'twitter_page' => $setting('twitter_page', ''),
            'website_url' => $setting('website_url', ''),
            'ads_interval' => $setting('ads_interval', '0'),
            'app_logo' => MediaUrl::resolve($setting('app_logo')),
            'google_login_enabled' => $settingEnabled('google_login_enabled'),
            'mobile_phone_otp_login_enabled' => $settingEnabled('mobile_phone_otp_login_enabled'),
            'testimonies_enabled' => $testimoniesEnabled,
            'counseling_enabled' => $settingEnabled('counseling_enabled', true),
            'fundraising_enabled' => $settingEnabled('fundraising_enabled'),
            'prayer_points_enabled' => $settingEnabled('prayer_points_enabled'),
            'interactive_prayer_wall_enabled' => $settingEnabled('interactive_prayer_wall_enabled'),
            'hymns_enabled' => $settingEnabled('hymns_enabled'),
            'devotionals_enabled' => $settingEnabled('devotionals_enabled'),
            'verse_of_day_enabled' => $verseOfDayEnabled,
            'transportation_arrangements_enabled' => $settingEnabled('transportation_arrangements_enabled'),
            'church_groups_enabled' => $settingEnabled('church_groups_enabled'),
            'dynamic_forms_enabled' => $settingEnabled('dynamic_forms_enabled'),
            'goshen_quiz_enabled' => $settingEnabled('goshen_quiz_enabled'),
            'goshen_wallet_withdrawals_enabled' => $settingEnabled('goshen_wallet_withdrawals_enabled'),
            'goshen_wallet_auto_topup_enabled' => $settingEnabled('goshen_wallet_auto_topup_enabled'),
            'branches_enabled' => $settingEnabled('branches_enabled'),
            'google_web_client_id' => $setting('google_web_client_id', ''),
            'google_android_client_id' => $setting('google_android_client_id', ''),
            'google_ios_client_id' => $setting('google_ios_client_id', ''),
            'verse_of_day' => $verseOfDayEnabled && ($verse = VerseOfDay::current()) ? new VerseOfDayResource($verse) : null,
            'prayer_requests_count' => CommunityPrayerRequest::visible()->count(),
            'prayer_request_avatars' => CommunityPrayerRequest::visible()
                ->where('is_anonymous', false)
                ->with('mobileUser')
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (CommunityPrayerRequest $request) => MediaUrl::resolve($request->mobileUser?->avatar))
                ->filter()
                ->values(),
            'testimonies_count' => $testimoniesEnabled
                ? Testimony::approved()->count()
                : 0,
            'donation_accounts' => [],
            'events' => app(RecurringChurchEventService::class)
                ->upcomingOccurrences($publishedEvents, 50)
                ->count(),
            'inbox' => (clone $inboxQuery)->count(),
            'inbox_latest_ids' => (clone $inboxQuery)
                ->latest('published_at')
                ->latest()
                ->limit(100)
                ->get(['id', 'legacy_id'])
                ->map(fn (InboxMessage $message) => (string) ($message->legacy_id ?? $message->id))
                ->values(),
        ]);
    }

    public function verseOfDay()
    {
        $verse = VerseOfDay::current();

        return response()->json([
            'status' => 'ok',
            'verse_of_day' => $verse ? new VerseOfDayResource($verse) : null,
        ]);
    }

    public function categories()
    {
        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->withCount('children')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'thumbnail' => $category->thumbnail_url,
                'media_count' => MediaItem::where('category_id', $category->id)->where('is_published', true)->count(),
                'subcategories_count' => $category->children_count,
            ]);

        return response()->json(['status' => 'ok', 'categories' => $categories]);
    }

    public function gallery()
    {
        $items = GalleryImage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'ok',
            'categories' => $items->pluck('category')->filter()->unique()->values(),
            'gallery' => $items->map(fn (GalleryImage $image) => $this->galleryPayload($image))->values(),
        ]);
    }

    public function fetchMedia(Request $request)
    {
        $data = $this->payload($request);
        $query = $this->orderedMediaQuery(MediaItem::with(['category', 'subCategory'])->where('is_published', true));
        if (! empty($data['media_type'])) {
            $query->where('type', $data['media_type']);
        }

        return $this->paginated('media', $query, (int) ($data['page'] ?? 0));
    }

    public function fetchCategoriesMedia(Request $request)
    {
        $data = $this->payload($request);
        $query = $this->orderedMediaQuery(MediaItem::with(['category', 'subCategory'])->where('is_published', true));
        if (! empty($data['category'])) {
            $query->where('category_id', $data['category']);
        }
        if (! empty($data['sub'])) {
            $query->where('sub_category_id', $data['sub']);
        }

        $response = $this->paginatedData($query, (int) ($data['page'] ?? 0));
        $subcategories = Category::where('parent_id', $data['category'] ?? null)->where('is_active', true)->get(['id', 'name']);

        return response()->json([
            'status' => 'ok',
            'media' => $response['items'],
            'subcategories' => $subcategories,
            'isLastPage' => $response['isLastPage'],
        ]);
    }

    public function search(Request $request)
    {
        $data = $this->payload($request);
        $term = $data['query'] ?? $request->input('query', '');
        $query = $this->orderedMediaQuery(
            MediaItem::with(['category', 'subCategory'])->where('is_published', true)
                ->where(fn ($builder) => $builder->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"))
        );

        $response = $this->paginatedData($query, (int) ($data['page'] ?? 0));

        return response()->json(['status' => 'ok', 'search' => $response['items'], 'isLastPage' => $response['isLastPage']]);
    }

    public function devotionals(Request $request)
    {
        if (! $this->settingEnabled('devotionals_enabled')) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Devotionals are not currently available.',
                'message' => 'Devotionals are not currently available.',
            ], 404);
        }

        $data = $this->payload($request);
        $query = Devotional::query()
            ->where('is_published', true)
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if (! empty($data['id'])) {
            $devotional = (clone $query)->whereKey($data['id'])->first();
        } else {
            $date = $data['date'] ?? now()->toDateString();
            $devotional = (clone $query)->whereDate('date', $date)->first()
                ?: (clone $query)->first();
        }

        return response()->json($devotional ? [
            'status' => 'ok',
            'devotional' => $this->devotionalPayload($devotional),
        ] : [
            'status' => 'error',
            'msg' => 'No devotional is available for this date.',
            'message' => 'No devotional is available for this date.',
        ]);
    }

    public function events(Request $request)
    {
        $data = $this->payload($request);
        $date = $data['date'] ?? $request->input('date');
        $events = app(RecurringChurchEventService::class)
            ->expandForRequest($this->publishedChurchEventsForExpansion()->get(), $date);

        return response()->json([
            'status' => 'ok',
            'events' => $events->map(fn (ChurchEvent $event) => $this->eventPayload($event)),
        ]);
    }

    public function prayers()
    {
        return response()->json(['status' => 'ok', 'prayers' => PrayerPoint::where('is_published', true)->latest()->get()]);
    }

    public function submitPrayer(Request $request)
    {
        $data = $this->payload($request) ?: $request->only(['date', 'title', 'author', 'content']);
        PrayerPoint::create([
            'date' => $data['date'] ?? now()->toDateString(),
            'title' => $data['title'] ?? 'Prayer Request',
            'author' => $data['author'] ?? null,
            'content' => $data['content'] ?? '',
            'is_published' => false,
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function inbox(Request $request)
    {
        $user = $this->mobileUserFromRequest($request);

        $response = $this->paginatedData(
            $this->visibleInboxQuery($user)
                ->latest('published_at')
                ->latest(),
            (int) (($this->payload($request)['page'] ?? 0)),
        );

        return response()->json([
            'status' => 'ok',
            'inbox' => $response['items']->map(fn (InboxMessage $message) => $this->inboxPayload($message, $user))->values(),
            'unread_count' => $response['items']->count(),
            'isLastPage' => $response['isLastPage'],
        ]);
    }

    public function deleteInbox(Request $request)
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Please sign in to delete notifications.'], 401);
        }

        $data = $this->payload($request);
        if (($data['mode'] ?? null) === 'read') {
            $readIds = collect($data['read_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($readIds->isEmpty()) {
                return response()->json(['status' => 'ok', 'message' => 'No read notifications selected.', 'deleted_count' => 0]);
            }

            $messages = $this->visibleInboxQuery($user)
                ->where(function ($query) use ($readIds): void {
                    $query->whereIn('id', $readIds->all())
                        ->orWhereIn('legacy_id', $readIds->all());
                })
                ->pluck('id');

            foreach ($messages as $messageId) {
                DB::table('inbox_message_deletions')->updateOrInsert(
                    ['inbox_message_id' => $messageId, 'mobile_user_id' => $user->id],
                    ['deleted_at' => now()]
                );
            }

            return response()->json([
                'status' => 'ok',
                'message' => 'Read notifications deleted.',
                'deleted_count' => $messages->count(),
            ]);
        }

        $id = (int) ($data['id'] ?? $request->input('id'));
        $message = $this->visibleInboxQuery($user)
            ->where(fn ($query) => $query->where('id', $id)->orWhere('legacy_id', $id))
            ->first();
        if (! $message) {
            return response()->json(['status' => 'error', 'message' => 'Notification not found.'], 404);
        }

        DB::table('inbox_message_deletions')->updateOrInsert(
            ['inbox_message_id' => $message->id, 'mobile_user_id' => $user->id],
            ['deleted_at' => now()]
        );

        return response()->json(['status' => 'ok', 'message' => 'Notification deleted.']);
    }

    public function fetchUserSettings(Request $request)
    {
        $user = $this->mobileUserFromRequest($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in to manage notification preferences.',
                'message' => 'Please sign in to manage notification preferences.',
            ], 401);
        }

        return response()->json($this->notificationSettingsPayload($user, 'Notification preferences loaded.'));
    }

    public function updateUserSettings(Request $request)
    {
        $user = $this->mobileUserFromRequest($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in to update notification preferences.',
                'message' => 'Please sign in to update notification preferences.',
            ], 401);
        }

        $data = $this->payload($request);
        $submitted = $data['notification_preferences'] ?? $data['preferences'] ?? [];

        if (! is_array($submitted)) {
            $submitted = [];
        }

        $user->forceFill([
            'notification_preferences' => $this->normalizeNotificationPreferences($submitted),
        ])->save();

        return response()->json($this->notificationSettingsPayload($user->fresh(), 'Notification preferences saved.'));
    }

    public function hymns(Request $request)
    {
        $data = $this->payload($request);
        $query = Hymn::where('is_published', true)->latest();
        if (! empty($data['query'])) {
            $query->where('title', 'like', "%{$data['query']}%");
        }

        return $this->paginated('hymns', $query, (int) ($data['page'] ?? 0));
    }

    public function bibleVersions()
    {
        return response()->json([
            'status' => 'ok',
            'versions' => BibleVersion::where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(fn (BibleVersion $version) => [
                    'id' => $version->id,
                    'name' => $version->name,
                    'shortcode' => $version->shortcode,
                    'description' => $version->description,
                    'source' => MediaUrl::resolve($version->json_path),
                ]),
        ]);
    }

    public function branches()
    {
        $branches = Branch::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->legacy_id ?? $branch->id,
                'name' => $branch->name ?? '',
                'pastor' => $branch->pastor ?? '',
                'phone' => $branch->phone ?? '',
                'email' => $branch->email ?? '',
                'address' => $branch->address ?? '',
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
            ]);

        return response()->json(['status' => 'ok', 'branches' => $branches]);
    }

    public function pastors()
    {
        $pastors = MobileUser::role('Pastor', 'mobile')
            ->where('is_verified', true)
            ->where('is_blocked', false)
            ->where('is_deleted', false)
            ->whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MobileUser $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'image_url' => MediaUrl::resolve($user->avatar) ?: '',
                'phone_number' => $user->phone ?? '',
                'role_title' => $user->role_title ?: 'Pastor',
                'is_active' => $user->canUseCommunity(),
                'sort_order' => (int) ($user->sort_order ?? 0),
            ])
            ->values();

        return response()->json(['status' => 'ok', 'pastors' => $pastors]);
    }

    public function churchGroups()
    {
        $groups = ChurchGroup::with(['leader', 'assistant', 'members' => fn ($query) => $query
            ->where('is_verified', true)
            ->where('is_blocked', false)
            ->where('is_deleted', false)
            ->orderBy('name')])
            ->withCount(['members' => fn ($query) => $query
                ->where('is_verified', true)
                ->where('is_blocked', false)
                ->where('is_deleted', false)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ChurchGroup $group) => $this->churchGroupPayload($group))
            ->values();

        return response()->json(['status' => 'ok', 'groups' => $groups]);
    }

    public function requestChurchGroupJoin(Request $request, ChurchGroup $churchGroup)
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user || ! $user->canUseCommunity()) {
            return response()->json(['status' => 'error', 'message' => 'Please sign in with a verified account to request group membership.'], 403);
        }
        if (! $churchGroup->is_active || str($churchGroup->name)->lower()->toString() === 'no group') {
            return response()->json(['status' => 'error', 'message' => 'This group is not available for joining.'], 422);
        }
        if ((int) $user->group_id === (int) $churchGroup->id) {
            return response()->json(['status' => 'ok', 'message' => 'You are already a member of this group.']);
        }

        $requestModel = ChurchGroupJoinRequest::updateOrCreate(
            [
                'church_group_id' => $churchGroup->id,
                'mobile_user_id' => $user->id,
                'status' => 'pending',
            ],
            [
                'message' => $this->payload($request)['message'] ?? null,
            ],
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Your group joining request has been sent.',
            'request' => $this->groupJoinRequestPayload($requestModel->load('mobileUser')),
        ]);
    }

    public function manageChurchGroups(Request $request)
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user || ! $this->canManageAnyGroup($user)) {
            return response()->json(['status' => 'error', 'message' => 'Only assigned group leaders and assistant group leaders can manage groups.'], 403);
        }

        $groups = ChurchGroup::with([
            'joinRequests' => fn ($query) => $query->where('status', 'pending')->with('mobileUser')->latest(),
            'members' => fn ($query) => $query->where('is_verified', true)->where('is_blocked', false)->where('is_deleted', false)->orderBy('name'),
        ])
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('leader_id', $user->id)->orWhere('assistant_id', $user->id))
            ->orderBy('name')
            ->get()
            ->map(fn (ChurchGroup $group) => [
                ...$this->churchGroupPayload($group),
                'pending_requests' => $group->joinRequests->map(fn (ChurchGroupJoinRequest $joinRequest) => $this->groupJoinRequestPayload($joinRequest))->values(),
            ])
            ->values();

        return response()->json(['status' => 'ok', 'groups' => $groups]);
    }

    public function reviewChurchGroupJoin(Request $request, ChurchGroupJoinRequest $joinRequest)
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user || ! $this->canManageGroup($user, $joinRequest->group)) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage requests for your assigned group.'], 403);
        }

        $action = $this->payload($request)['action'] ?? $request->input('action');
        if (! in_array($action, ['approve', 'reject'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Choose approve or reject.'], 422);
        }

        if ($action === 'approve') {
            $joinRequest->mobileUser?->forceFill(['group_id' => $joinRequest->church_group_id])->save();
        }

        $joinRequest->forceFill([
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json(['status' => 'ok', 'message' => 'Request '.$joinRequest->status.'.']);
    }

    public function removeChurchGroupMember(Request $request, ChurchGroup $churchGroup, MobileUser $member)
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user || ! $this->canManageGroup($user, $churchGroup)) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage members for your assigned group.'], 403);
        }
        if ((int) $member->group_id !== (int) $churchGroup->id) {
            return response()->json(['status' => 'error', 'message' => 'This user is not a member of the selected group.'], 422);
        }

        $member->forceFill(['group_id' => null])->save();

        return response()->json(['status' => 'ok', 'message' => 'Member removed from group.']);
    }

    public function transportationArrangements(Request $request)
    {
        $data = $this->payload($request);
        $programName = trim((string) ($data['program_name'] ?? $request->input('program_name', '72Hours')));

        $query = TransportationArrangement::where('is_active', true);

        if ($programName !== '') {
            $query->where('program_name', $programName);
        }

        return response()->json([
            'status' => 'ok',
            'transportation_arrangements' => $query
                ->orderBy('sort_order')
                ->orderBy('state')
                ->orderBy('city_town')
                ->get()
                ->map(fn (TransportationArrangement $arrangement) => $this->transportationPayload($arrangement)),
        ]);
    }

    public function streams()
    {
        return response()->json([
            'status' => 'ok',
            'livestreams' => Stream::where('type', 'livestream')->where('is_active', true)->get()->map(fn (Stream $stream) => $this->streamPayload($stream)),
        ]);
    }

    public function discoverTrends()
    {
        $query = MediaItem::with(['category', 'subCategory'])
            ->where('is_published', true)
            ->orderByDesc('views_count')
            ->orderByDesc('likes_count');

        return $this->paginated('media', $query, 0);
    }

    public function mediaTotals(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $media = MediaItem::find($data['media'] ?? $data['media_id'] ?? $data['id'] ?? null);

        if (! $media) {
            return response()->json(['status' => 'error', 'msg' => 'Media not found.']);
        }

        return response()->json([
            'status' => 'ok',
            'total_comments' => $media->comments()->where('is_published', true)->count(),
            'total_likes' => $media->likes_count,
            'total_views' => $media->views_count,
            'isLiked' => false,
        ]);
    }

    public function updateMediaViews(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $media = MediaItem::find($data['media'] ?? $data['media_id'] ?? $data['id'] ?? null);

        if (! $media) {
            return response()->json(['status' => 'error', 'msg' => 'Media not found.']);
        }

        $media->increment('views_count');

        return response()->json(['status' => 'ok', 'total_views' => $media->fresh()->views_count]);
    }

    public function likeUnlikeMedia(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $media = MediaItem::find($data['media'] ?? $data['media_id'] ?? $data['id'] ?? null);

        if (! $media) {
            return response()->json(['status' => 'error', 'msg' => 'Media not found.']);
        }

        $action = $data['action'] ?? 'like';
        $action === 'unlike'
            ? $media->decrement('likes_count', min(1, $media->likes_count))
            : $media->increment('likes_count');

        return response()->json(['status' => 'ok', 'total_likes' => $media->fresh()->likes_count]);
    }

    public function loginUser(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $user = MobileUser::where('email', $data['email'] ?? null)->first();
        $password = (string) ($data['password'] ?? '');
        $credentialsValid = $user
            ? app(MergedAccountCredentialService::class)->validateMobileCredentials($user, $password)
            : false;

        if (! $credentialsValid && $user && $user->google_id && (! $user->password || ! Hash::check($password, $user->password ?? ''))) {
            return response()->json([
                'status' => 'error',
                'msg' => 'This account was created with Google. Please continue with Google, or use Forgot Password to create a password for email sign-in.',
                'message' => 'This account was created with Google. Please continue with Google, or use Forgot Password to create a password for email sign-in.',
                'google_account' => true,
                'can_create_password' => true,
            ]);
        }

        if (! $user || ! $credentialsValid) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Invalid email or password.',
                'message' => 'Invalid email or password.',
            ]);
        }

        if (! $user->is_verified || ! $user->email_verified_at) {
            $this->sendMobileVerificationCode($user);

            return response()->json([
                'status' => 'error',
                'msg' => 'Please verify your email address before signing in. A new verification code has been sent.',
                'message' => 'Please verify your email address before signing in. A new verification code has been sent.',
                'needs_verification' => true,
                'email' => $user->email,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'msg' => 'Signed in successfully.',
            'message' => 'Signed in successfully.',
            'user' => $this->mobileUserPayload($user, $user->issueApiToken()),
        ]);
    }

    public function syncMobileSession(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $token = $data['api_token'] ?? $request->bearerToken();

        if (! filled($token)) {
            return response()->json([
                'status' => 'error',
                'auth_invalid' => true,
                'msg' => 'Your saved login has expired. Please sign in again.',
                'message' => 'Your saved login has expired. Please sign in again.',
            ], 401);
        }

        $user = MobileUser::where('api_token_hash', hash('sha256', $token))->first();

        if (! $user || $user->is_deleted || $user->is_blocked) {
            return response()->json([
                'status' => 'error',
                'auth_invalid' => true,
                'msg' => 'Your saved login has expired. Please sign in again.',
                'message' => 'Your saved login has expired. Please sign in again.',
            ], $user && ($user->is_deleted || $user->is_blocked) ? 403 : 401);
        }

        $user->markApiSeen();

        return response()->json([
            'status' => 'ok',
            'msg' => 'Session verified.',
            'message' => 'Session verified.',
            'user' => $this->mobileUserPayload($user->fresh(), $token),
        ]);
    }

    public function registerUser(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'profile_title' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'salutation' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:80'],
            'gender' => ['required', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', Rule::in(array_keys(MobileUser::MARITAL_STATUS_OPTIONS))],
            'group_id' => ['required', 'integer', 'exists:church_groups,id'],
            'member_type' => ['required', 'string', 'in:church_member,visitor'],
            'country_of_residence' => ['required', 'string', 'max:120'],
            'state_county_province' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:500'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ])->validate();

        $existingUser = MobileUser::where('email', $data['email'] ?? null)->first();
        if ($existingUser) {
            if ($existingUser->is_verified && $existingUser->email_verified_at) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'An account already exists for this email. Please sign in.',
                    'message' => 'An account already exists for this email. Please sign in.',
                ]);
            }

            $existingUser->forceFill([
                'name' => $data['name'] ?? $existingUser->name,
                'title' => $data['title'] ?? $data['profile_title'] ?? $data['salutation'] ?? $existingUser->title,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'gender' => $data['gender'],
                'marital_status' => $data['marital_status'] ?? $existingUser->marital_status,
                'group_id' => $data['group_id'],
                'member_type' => $data['member_type'],
                'country_of_residence' => $data['country_of_residence'],
                'state_county_province' => $data['state_county_province'],
                'address' => $data['address'],
                'address_latitude' => $data['address_latitude'] ?? null,
                'address_longitude' => $data['address_longitude'] ?? null,
                'password' => Hash::make($data['password']),
            ])->save();
            $user = $existingUser;
        } else {
            $user = MobileUser::create([
                'name' => $data['name'] ?? 'Mobile User',
                'title' => $data['title'] ?? $data['profile_title'] ?? $data['salutation'] ?? null,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'gender' => $data['gender'],
                'marital_status' => $data['marital_status'] ?? null,
                'group_id' => $data['group_id'],
                'member_type' => $data['member_type'],
                'country_of_residence' => $data['country_of_residence'],
                'state_county_province' => $data['state_county_province'],
                'address' => $data['address'],
                'address_latitude' => $data['address_latitude'] ?? null,
                'address_longitude' => $data['address_longitude'] ?? null,
                'password' => Hash::make($data['password'] ?? str()->random(24)),
                'is_verified' => false,
                'email_verified_at' => null,
            ]);
        }

        $this->sendMobileVerificationCode($user);

        return response()->json([
            'status' => 'ok',
            'msg' => 'Account created. Please check your email for the verification code.',
            'message' => 'Account created. Please check your email for the verification code.',
            'needs_verification' => true,
            'email' => $user->email,
        ]);
    }

    public function googleAuth(Request $request)
    {
        if (! $this->settingEnabled('google_login_enabled')) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Google sign-in is not enabled yet.',
                'message' => 'Google sign-in is not enabled yet.',
            ], 403);
        }

        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'string', 'max:30'],
            'group_id' => ['nullable', 'integer', 'exists:church_groups,id'],
            'member_type' => ['nullable', 'string', 'in:church_member,visitor'],
            'country_of_residence' => ['nullable', 'string', 'max:120'],
            'state_county_province' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ])->validate();

        $allowedAudiences = collect([
            AppSetting::value('google_web_client_id', ''),
            AppSetting::value('google_android_client_id', ''),
            AppSetting::value('google_ios_client_id', ''),
        ])
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values();

        if ($allowedAudiences->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Google sign-in is not configured yet.',
                'message' => 'Google sign-in is not configured yet.',
            ], 503);
        }

        try {
            $googleResponse = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['id_token'],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Unable to verify Google sign-in right now.',
                'message' => 'Unable to verify Google sign-in right now.',
            ], 422);
        }

        if (! $googleResponse->ok()) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Google sign-in could not be verified.',
                'message' => 'Google sign-in could not be verified.',
            ], 422);
        }

        $google = $googleResponse->json();

        if (! $allowedAudiences->contains($google['aud'] ?? null)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'This Google account is not configured for this app.',
                'message' => 'This Google account is not configured for this app.',
            ], 422);
        }

        if (! filter_var($google['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please verify your Google email address before continuing.',
                'message' => 'Please verify your Google email address before continuing.',
            ], 422);
        }

        $email = strtolower((string) ($google['email'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Google did not return a valid email address.',
                'message' => 'Google did not return a valid email address.',
            ], 422);
        }

        $user = MobileUser::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'No member account exists for this Google email yet. Please register with email first, then use Google after your account is available in the portal.',
                'message' => 'No member account exists for this Google email yet. Please register with email first, then use Google after your account is available in the portal.',
            ], 404);
        }

        $wasVerified = (bool) ($user->is_verified && $user->email_verified_at);

        if ($user->is_blocked || $user->is_deleted) {
            return response()->json([
                'status' => 'error',
                'msg' => 'This account is not allowed to sign in. Please contact support.',
                'message' => 'This account is not allowed to sign in. Please contact support.',
            ], 403);
        }

        $user->forceFill([
            'google_id' => $google['sub'] ?? $user->google_id,
            'name' => $user->name ?: ($data['name'] ?? $google['name'] ?? explode('@', $email)[0]),
            'phone' => $data['phone'] ?? $user->phone,
            'gender' => $data['gender'] ?? $user->gender,
            'group_id' => $data['group_id'] ?? $user->group_id,
            'member_type' => $data['member_type'] ?? $user->member_type,
            'country_of_residence' => $data['country_of_residence'] ?? $user->country_of_residence,
            'state_county_province' => $data['state_county_province'] ?? $user->state_county_province,
            'address' => $data['address'] ?? $user->address,
            'address_latitude' => $data['address_latitude'] ?? $user->address_latitude,
            'address_longitude' => $data['address_longitude'] ?? $user->address_longitude,
            'avatar' => $user->avatar ?: ($data['photo_url'] ?? $google['picture'] ?? null),
            'login_type' => $user->login_type ?: 'google',
            'is_verified' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'email_verification_code_hash' => null,
            'email_verification_expires_at' => null,
        ])->save();

        if (! $wasVerified) {
            app(AutomaticNotificationService::class)->enqueue('welcome_verified_user', $user);
        }

        $user = $user->refresh();
        if (blank($user->triumphant_id)) {
            $user = app(TriumphantIdService::class)->assignFor($user)->refresh();
        }

        return response()->json([
            'status' => 'ok',
            'msg' => 'Signed in successfully.',
            'message' => 'Signed in successfully.',
            'user' => $this->mobileUserPayload($user, $user->issueApiToken()),
            'is_new_user' => false,
            'profile_needs_update' => blank($user->phone) || blank($user->gender) || blank($user->member_type) || blank($user->country_of_residence) || blank($user->state_county_province) || blank($user->address),
        ]);
    }

    public function phoneAuth(Request $request, FirebaseAuth $firebaseAuth)
    {
        if (! $this->settingEnabled('mobile_phone_otp_login_enabled')) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Phone OTP login is not enabled yet.',
                'message' => 'Phone OTP login is not enabled yet.',
            ], 403);
        }

        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'id_token' => ['required', 'string'],
        ])->validate();

        try {
            $verifiedToken = $firebaseAuth->verifyIdToken($data['id_token'], true, 60);
        } catch (\Throwable) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Phone verification could not be verified. Please request a new code and try again.',
                'message' => 'Phone verification could not be verified. Please request a new code and try again.',
            ], 422);
        }

        $claims = $verifiedToken->claims();
        $firebaseUid = (string) ($claims->get('sub') ?? '');
        $phone = $this->normalizedFirebasePhone((string) ($claims->get('phone_number') ?? ''));
        $firebaseClaim = $claims->get('firebase') ?? [];
        $signInProvider = is_array($firebaseClaim) ? (string) ($firebaseClaim['sign_in_provider'] ?? '') : '';

        if ($firebaseUid === '' || $phone === '' || $signInProvider !== 'phone') {
            return response()->json([
                'status' => 'error',
                'msg' => 'Firebase did not return a verified phone sign-in.',
                'message' => 'Firebase did not return a verified phone sign-in.',
            ], 422);
        }

        $user = $this->mobileUserForFirebasePhone($firebaseUid, $phone);
        $isNew = ! $user->exists;

        if ($user->exists && ($user->is_blocked || $user->is_deleted)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'This account is not currently active.',
                'message' => 'This account is not currently active.',
            ], 403);
        }

        if ($user->exists && filled($user->firebase_uid) && $user->firebase_uid !== $firebaseUid) {
            return response()->json([
                'status' => 'error',
                'msg' => 'This phone number is already linked to another verified Firebase account.',
                'message' => 'This phone number is already linked to another verified Firebase account.',
            ], 409);
        }

        $user->forceFill([
            'firebase_uid' => $firebaseUid,
            'phone' => $user->phone ?: $phone,
            'phone_normalized' => $phone,
            'name' => $user->name ?: 'Mobile user',
            'email' => $user->email ?: $this->phonePlaceholderEmail($phone),
            'login_type' => $isNew ? 'phone_otp' : $user->login_type,
            'is_verified' => true,
            'phone_verified_at' => now(),
        ])->save();

        if ($isNew) {
            app(AutomaticNotificationService::class)->enqueue('welcome_verified_user', $user);
        }

        return response()->json([
            'status' => 'ok',
            'msg' => 'Signed in successfully.',
            'message' => 'Signed in successfully.',
            'user' => $this->mobileUserPayload($user->fresh(), $user->issueApiToken()),
            'is_new_user' => $isNew,
            'profile_needs_update' => blank($user->first_name) || blank($user->last_name) || blank($user->gender) || blank($user->member_type) || blank($user->country_of_residence) || blank($user->state_county_province) || blank($user->address),
        ]);
    }

    public function verifyMobileEmail(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ])->validate();

        $user = MobileUser::where('email', $data['email'])->first();
        if (! $user || ! $this->validMobileCode($user->email_verification_code_hash, $data['code'])) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Invalid verification code.',
                'message' => 'Invalid verification code.',
            ]);
        }

        if ($user->email_verification_expires_at && $user->email_verification_expires_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Verification code has expired. Please request a new code.',
                'message' => 'Verification code has expired. Please request a new code.',
            ]);
        }

        $user->forceFill([
            'is_verified' => true,
            'email_verified_at' => now(),
            'email_verification_code_hash' => null,
            'email_verification_expires_at' => null,
        ])->save();

        app(AutomaticNotificationService::class)->enqueue('welcome_verified_user', $user);

        return response()->json([
            'status' => 'ok',
            'msg' => 'Email verified successfully.',
            'message' => 'Email verified successfully.',
            'user' => $this->mobileUserPayload($user, $user->issueApiToken()),
        ]);
    }

    public function resendMobileVerification(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'email' => ['required', 'email:rfc', 'max:255'],
        ])->validate();

        $user = MobileUser::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['status' => 'ok', 'msg' => 'If the email exists, a verification code has been sent.', 'message' => 'If the email exists, a verification code has been sent.']);
        }

        if ($user->is_verified && $user->email_verified_at) {
            return response()->json(['status' => 'ok', 'msg' => 'This email is already verified.', 'message' => 'This email is already verified.']);
        }

        $this->sendMobileVerificationCode($user);

        return response()->json(['status' => 'ok', 'msg' => 'Verification code sent.', 'message' => 'Verification code sent.']);
    }

    public function requestPasswordReset(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'email' => ['required', 'email:rfc', 'max:255'],
        ])->validate();

        $user = MobileUser::where('email', $data['email'])->first();
        if ($user) {
            $code = $this->newMobileCode();
            $user->forceFill([
                'password_reset_code_hash' => Hash::make($code),
                'password_reset_expires_at' => now()->addMinutes(30),
            ])->save();

            try {
                app(DynamicSmtpMailer::class)->sendRaw(
                    $user->email,
                    'MFM Triumphant Church password reset code',
                    "Hello {$user->name},\n\nUse this code to reset your MFM Triumphant Church password: {$code}\n\nThis code expires in 30 minutes.\n\nIf you did not request this, please ignore this email.",
                );
            } catch (\Throwable) {
                // Keep response generic so account existence and mail config are not leaked.
            }
        }

        return response()->json([
            'status' => 'ok',
            'msg' => 'If this email is registered, a password reset code has been sent.',
            'message' => 'If this email is registered, a password reset code has been sent.',
        ]);
    }

    public function resetMobilePassword(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        validator($data, [
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ])->validate();

        $user = MobileUser::where('email', $data['email'])->first();
        if (! $user || ! $this->validMobileCode($user->password_reset_code_hash, $data['code'])) {
            return response()->json(['status' => 'error', 'msg' => 'Invalid password reset code.', 'message' => 'Invalid password reset code.']);
        }

        if ($user->password_reset_expires_at && $user->password_reset_expires_at->isPast()) {
            return response()->json(['status' => 'error', 'msg' => 'Password reset code has expired.', 'message' => 'Password reset code has expired.']);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_reset_code_hash' => null,
            'password_reset_expires_at' => null,
        ])->save();

        try {
            app(DynamicSmtpMailer::class)->sendRaw(
                $user->email,
                'Your MFM Triumphant Church password was reset',
                "Hello {$user->name},\n\nYour MFM Triumphant Church app password was reset successfully.\n\nIf you made this change, no further action is needed. If this was not you, please contact the church admin immediately so your account can be protected.\n\nGod bless you.",
            );
        } catch (\Throwable) {
            // Do not fail the reset if the notification email cannot be sent.
        }

        return response()->json(['status' => 'ok', 'msg' => 'Password reset successfully. Please sign in.', 'message' => 'Password reset successfully. Please sign in.']);
    }

    public function memberMe(Request $request)
    {
        $user = $this->mobileUserFromToken($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in again.',
                'message' => 'Please sign in again.',
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'user' => $this->mobileUserPayload($user, null),
        ]);
    }

    public function storeFcmToken(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $token = $data['token'] ?? $request->input('token');
        if (! filled($token)) {
            return response()->json(['status' => 'ok', 'msg' => 'No FCM token available yet.']);
        }

        FcmToken::updateOrCreate(['token' => $token], [
            'email' => $data['email'] ?? null,
            'app_version' => $data['app_version'] ?? $data['version'] ?? 'v1',
            'channel' => $data['channel'] ?? 'general',
            'last_seen_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'msg' => 'Token stored.']);
    }

    public function updateProfile(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $token = $data['api_token'] ?? $request->bearerToken();
        $user = filled($token)
            ? MobileUser::where('api_token_hash', hash('sha256', $token))->first()
            : null;

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in again before updating your profile.',
                'message' => 'Please sign in again before updating your profile.',
            ], 401);
        }

        if ($user->is_blocked || $user->is_deleted) {
            return response()->json([
                'status' => 'error',
                'msg' => 'This account cannot update its profile.',
                'message' => 'This account cannot update its profile.',
            ], 403);
        }

        $validated = validator(array_merge($data, $request->allFiles()), [
            'fullname' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'profile_title' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'salutation' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'first_name' => ['nullable', 'required_without:fullname', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'required_without:fullname', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:80'],
            'gender' => ['required', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', Rule::in(array_keys(MobileUser::MARITAL_STATUS_OPTIONS))],
            'group_id' => ['required', 'integer', 'exists:church_groups,id'],
            'member_type' => ['required', 'string', 'in:church_member,visitor'],
            'country_of_residence' => ['required', 'string', 'max:120'],
            'state_county_province' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:500'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'about_me' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ])->validate();

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = app(ProfileImageOptimizer::class)->store($request->file('avatar'), 'mobile-users/avatars');
        }

        if ($request->hasFile('cover_photo')) {
            $validated['cover_photo'] = app(ProfileImageOptimizer::class)->store($request->file('cover_photo'), 'mobile-users/covers');
        }

        $fullName = trim((string) ($validated['fullname'] ?? ''));
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $middleName = trim((string) ($validated['middle_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        if ($firstName === '' && $fullName !== '') {
            $firstName = str($fullName)->before(' ')->toString();
        }

        if ($lastName === '' && $fullName !== '' && str($fullName)->contains(' ')) {
            $lastName = str($fullName)->after(' ')->toString();
        }

        $displayName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));
        if ($displayName === '') {
            $displayName = $fullName;
        }

        $user->forceFill([
            'name' => $displayName,
            'title' => $validated['title'] ?? $validated['profile_title'] ?? $validated['salutation'] ?? $user->title,
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'phone' => $validated['phone'],
            'gender' => $validated['gender'],
            'marital_status' => $validated['marital_status'] ?? $user->marital_status,
            'group_id' => $validated['group_id'],
            'member_type' => $validated['member_type'],
            'country_of_residence' => $validated['country_of_residence'],
            'state_county_province' => $validated['state_county_province'],
            'address' => $validated['address'],
            'address_latitude' => $validated['address_latitude'] ?? null,
            'address_longitude' => $validated['address_longitude'] ?? null,
            'bio' => $validated['about_me'] ?? null,
            'avatar' => $validated['avatar'] ?? $user->avatar,
            'cover_photo' => $validated['cover_photo'] ?? $user->cover_photo,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'msg' => 'Profile updated successfully.',
            'message' => 'Profile updated successfully.',
            'user' => $this->mobileUserPayload($user->fresh(), $token),
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $email = $data['email'] ?? $request->input('email');
        $token = $data['api_token'] ?? $request->bearerToken();

        if (! filled($email) || ! filled($token)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in again before deleting your account.',
                'message' => 'Please sign in again before deleting your account.',
            ], 422);
        }

        $user = MobileUser::where('email', $email)
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in again before deleting your account.',
                'message' => 'Please sign in again before deleting your account.',
            ], 401);
        }

        $user->tokens()->delete();
        FcmToken::where('email', $user->email)->delete();

        $user->forceFill([
            'is_deleted' => true,
            'is_blocked' => true,
            'api_token_hash' => null,
            'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(8)).'@deleted.local',
            'name' => 'Deleted account',
            'password' => null,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'msg' => 'Account deleted successfully.',
            'message' => 'Account deleted successfully.',
        ]);
    }

    public function saveDonation(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $category = $this->resolveDonationCategory($data);

        $donation = Donation::create([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'donation_category_id' => $category?->id,
            'purpose' => $category?->name ?? ($data['purpose'] ?? null),
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? AppSetting::value('currency', 'NGN'),
            'provider' => $data['provider'] ?? null,
            'reference' => $data['reference'] ?? uniqid('don_', true),
            'status' => $data['status'] ?? 'pending',
            'metadata' => array_merge($data, [
                'donation_category_id' => $category?->id,
                'category_slug' => $category?->slug,
            ]),
        ]);

        return response()->json(['status' => 'ok', 'donation' => $donation]);
    }

    private function resolveDonationCategory(array $data): ?DonationCategory
    {
        $query = DonationCategory::query()->where('is_active', true);

        if (! empty($data['donation_category_id'])) {
            return (clone $query)->whereKey($data['donation_category_id'])->first();
        }

        if (! empty($data['category_slug'])) {
            return (clone $query)->where('slug', $data['category_slug'])->first();
        }

        if (! empty($data['purpose'])) {
            return (clone $query)
                ->where(function ($categoryQuery) use ($data): void {
                    $categoryQuery
                        ->where('slug', Str::slug((string) $data['purpose']))
                        ->orWhere('name', (string) $data['purpose']);
                })
                ->first();
        }

        return null;
    }

    public function donationAccounts()
    {
        return response()->json([
            'status' => 'ok',
            'categories' => $this->donationAccountPayload(),
        ]);
    }

    public function contentPage(string $type)
    {
        $page = ContentPage::where('type', $type)->where('is_published', true)->first();

        return response()->json([
            'status' => $page ? 'ok' : 'error',
            'page' => $page ? $this->contentPagePayload($page) : null,
        ]);
    }

    public function submitSuggestion(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $user = $this->verifiedMobileUser($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in with a verified account before sending a suggestion.',
            ], 401);
        }

        $validated = validator($data, [
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'app_version' => ['nullable', 'string', 'max:80'],
            'device' => ['nullable', 'string', 'max:160'],
        ])->validate();

        $suggestion = AppSuggestion::create([
            'mobile_user_id' => $user->id,
            'sender_name' => $user->name ?? 'Mobile User',
            'sender_email' => $user->email,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'app_version' => $validated['app_version'] ?? null,
            'device' => $validated['device'] ?? null,
        ]);

        return response()->json(['status' => 'ok', 'suggestion' => $suggestion]);
    }

    public function submitContact(Request $request)
    {
        $data = $this->payload($request) ?: $request->all();
        $user = $this->verifiedMobileUser($request);

        $validated = validator($data, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ])->validate();

        $contactMessage = ContactMessage::create([
            'mobile_user_id' => $user?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? 'Contact message',
            'message' => $validated['message'],
        ]);

        $recipients = ContactRecipient::where('is_active', true)->orderBy('sort_order')->pluck('email');
        $mailBody = "New contact message from {$contactMessage->name}\n\nEmail: {$contactMessage->email}\nPhone: {$contactMessage->phone}\nSubject: {$contactMessage->subject}\n\n{$contactMessage->message}";
        $emailed = false;

        foreach ($recipients as $recipient) {
            try {
                app(DynamicSmtpMailer::class)->sendRaw($recipient, 'New contact message: '.$contactMessage->subject, $mailBody);
                $emailed = true;
            } catch (\Throwable) {
                //
            }
        }

        if ($emailed) {
            $contactMessage->forceFill(['emailed_at' => now()])->save();
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Your message has been sent.',
            'contact_message' => $contactMessage,
        ]);
    }

    public function page(string $type)
    {
        $page = ContentPage::where('type', $type)->where('is_published', true)->first();

        return view('content-page', ['page' => $page, 'type' => $type]);
    }

    public function notImplemented()
    {
        return response()->json([
            'status' => 'error',
            'msg' => 'This legacy feature has been retired in the current app.',
            'message' => 'This legacy feature has been retired in the current app.',
        ], 410);
    }

    public function health()
    {
        return response()->json(['status' => 'ok', 'service' => 'covenant-admin-api']);
    }

    private function payload(Request $request): array
    {
        $data = $request->input('data');
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($data)) {
            return $data;
        }
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : (is_array($decoded) ? $decoded : []);
    }

    private function paginated(string $key, $query, int $page)
    {
        $response = $this->paginatedData($query, $page);

        return response()->json(['status' => 'ok', $key => $response['items'], 'isLastPage' => $response['isLastPage']]);
    }

    private function paginatedData($query, int $page): array
    {
        $perPage = 20;
        $results = (clone $query)->skip($page * $perPage)->take($perPage)->get();
        $total = (clone $query)->count();

        return [
            'items' => $results->map(fn ($item) => $item instanceof MediaItem ? $this->mediaPayload($item) : $item),
            'isLastPage' => (($page + 1) * $perPage) >= $total,
        ];
    }

    private function donationAccountPayload()
    {
        return DonationAccountCategory::with(['accounts' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DonationAccountCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'currency_code' => $category->currency_code,
                'country_code' => $category->country_code,
                'flag_icon' => $category->flag_icon,
                'color' => $category->color,
                'accounts' => $category->accounts->map(fn ($account) => [
                    'id' => $account->id,
                    'bank_name' => $account->bank_name,
                    'account_name' => $account->account_name,
                    'account_number' => $account->account_number,
                    'sort_code' => $account->sort_code,
                    'routing_number' => $account->routing_number,
                    'swift_code' => $account->swift_code,
                    'iban' => $account->iban,
                    'instructions' => $account->instructions,
                ]),
            ]);
    }

    private function homeSliderPayload($publishedEvents)
    {
        $events = app(RecurringChurchEventService::class)
            ->upcomingDistinctEvents($publishedEvents, 5)
            ->map(fn (ChurchEvent $event) => $this->eventAsMediaPayload($event));

        $media = $this->orderedMediaQuery(MediaItem::with(['category', 'subCategory'])
            ->withCount(['comments as comments_count' => fn ($query) => $query->where('is_published', true)])
            ->where('is_published', true)
            ->where('is_featured', true)
            ->whereIn('type', ['video', 'audio', 'music']))
            ->limit(10)
            ->get()
            ->map(fn (MediaItem $item) => $this->mediaPayload($item));

        return $events
            ->concat($media)
            ->take(12)
            ->values();
    }

    private function publishedChurchEventsForExpansion()
    {
        return ChurchEvent::where('is_published', true)->orderBy('starts_at');
    }

    private function orderedMediaQuery($query)
    {
        return $query
            ->orderByRaw('CASE WHEN pin_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pin_position')
            ->latest();
    }

    private function eventAsMediaPayload(ChurchEvent $event): array
    {
        return [
            'id' => $event->legacy_id ?? $event->id,
            'category' => 'Event',
            'category_id' => null,
            'sub_category' => null,
            'title' => $event->title,
            'description' => strip_tags((string) $event->details),
            'cover_photo' => $event->thumbnail_url ?? MediaUrl::resolve('reference/header.jpg'),
            'source' => '',
            'stream' => '',
            'download' => '',
            'download_url' => '',
            'hd_source' => '',
            'sd_source' => '',
            'audio_source' => '',
            'duration' => 0,
            'can_preview' => 1,
            'preview_duration' => 0,
            'can_download' => 1,
            'is_free' => 0,
            'type' => 'event',
            'video_type' => '',
            'likes_count' => 0,
            'views_count' => 0,
            'comments_count' => 0,
            'user_liked' => false,
            'dateInserted' => optional($event->starts_at)->toDateTimeString(),
        ];
    }

    private function galleryPayload(GalleryImage $image): array
    {
        return [
            'id' => $image->id,
            'title' => $image->title,
            'category' => $image->category ?: 'General',
            'description' => $image->description ?? '',
            'image_url' => $image->image_url,
            'published_at' => optional($image->published_at ?? $image->created_at)->toDateTimeString(),
        ];
    }

    private function mediaPayload(MediaItem $media): array
    {
        $source = $this->mediaSource($media);
        $videoType = $this->legacyVideoType($media);

        return [
            'id' => $media->id,
            'category' => $media->category?->name ?? $media->category_id,
            'category_id' => $media->category_id,
            'sub_category' => $media->sub_category_id,
            'title' => $media->title,
            'description' => $media->description,
            'cover_photo' => $media->cover_photo_url ?? MediaUrl::resolve('reference/header.jpg'),
            'source' => $source,
            'stream' => $source,
            'download' => $source,
            'download_url' => $source,
            'hd_source' => $media->hd_source_url,
            'sd_source' => $media->sd_source_url,
            'audio_source' => $media->audio_source_url,
            'duration' => $media->duration,
            'can_preview' => $media->can_preview ? 0 : 1,
            'preview_duration' => $media->preview_duration,
            'can_download' => $media->can_download ? 0 : 1,
            'is_free' => $media->is_free ? 0 : 1,
            'type' => $media->type,
            'video_type' => $videoType,
            'likes_count' => $media->likes_count,
            'views_count' => $media->views_count,
            'pin_position' => $media->pin_position,
            'comments_count' => isset($media->comments_count)
                ? (int) $media->comments_count
                : $media->comments()->where('is_published', true)->count(),
            'user_liked' => false,
            'dateInserted' => optional($media->published_at ?? $media->created_at)->toDateTimeString(),
        ];
    }

    private function devotionalPayload(Devotional $devotional): array
    {
        return [
            'id' => $devotional->id,
            'title' => $devotional->title,
            'author' => $devotional->author ?? '',
            'date' => optional($devotional->date)->toDateString(),
            'content' => $devotional->content ?? '',
            'bible_reading' => $devotional->bible_reading ?? '',
            'confession' => $devotional->confession ?? '',
            'studies' => $devotional->studies ?? '',
            'excerpt' => str($devotional->content ?? '')->stripTags()->limit(140)->toString(),
            'thumbnail' => MediaUrl::resolve($devotional->thumbnail) ?: '',
            'thumbnail_url' => MediaUrl::resolve($devotional->thumbnail) ?: '',
            'is_published' => (bool) $devotional->is_published,
            'dateInserted' => optional($devotional->created_at)->toDateTimeString(),
        ];
    }

    private function mobileUserPayload(MobileUser $user, ?string $apiToken = null): array
    {
        $user->loadMissing('churchGroup');

        return [
            'id' => $user->id,
            'triumphant_id' => $user->triumphant_id,
            'name' => $user->name,
            'title' => $user->title ?? '',
            'profile_title' => $user->title ?? '',
            'first_name' => $user->first_name ?? '',
            'middle_name' => $user->middle_name ?? '',
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
            'api_token' => $apiToken,
            'avatar' => MediaUrl::resolve($user->avatar) ?: '',
            'cover_photo' => MediaUrl::resolve($user->cover_photo) ?: '',
            'gender' => $user->gender ?? '',
            'marital_status' => $user->marital_status ?? '',
            'group_id' => $user->group_id,
            'group_name' => $user->churchGroup?->name ?? '',
            'member_type' => $user->member_type ?? '',
            'country_of_residence' => $user->country_of_residence ?? '',
            'state_county_province' => $user->state_county_province ?? '',
            'address' => $user->address ?? '',
            'address_latitude' => $user->address_latitude,
            'address_longitude' => $user->address_longitude,
            'date_of_birth' => '',
            'phone' => $user->phone ?? '',
            'about_me' => $user->bio ?? '',
            'location' => '',
            'qualification' => '',
            'facebook' => '',
            'twitter' => '',
            'linkdln' => '',
            'roles' => $user->roles()->pluck('name')->values(),
            'can_manage_groups' => $this->canManageAnyGroup($user),
            'can_manage_goshen_registration' => $this->canManageGoshenRegistration($user),
            'can_manage_goshen_vouchers' => $this->canManageGoshenVouchers($user),
            'can_manage_goshen_quiz' => $this->canManageGoshenQuiz($user),
            'can_manage_fundraising' => $this->canManageFundraising($user),
            'can_manage_wallet_withdrawals' => $this->canManageWalletWithdrawals($user),
            'can_manage_dynamic_forms' => $this->canManageDynamicForms($user),
            'can_manage_church_events' => $this->canManageChurchEvents($user),
            'can_manage_verse_of_day' => $this->canManageVerseOfDay($user),
            'can_manage_mobile_users' => $this->canManageMobileUsers($user),
            'can_manage_counseling' => $this->canManageCounseling($user),
            'can_send_admin_messages' => $this->canSendAdminMessages($user),
            'is_go' => $user->hasGeneralOverseerRole(),
            'can_manage_prophetic_decree' => $user->canManagePropheticDecree(),
            'activated' => $user->canUseCommunity() ? 0 : 1,
        ];
    }

    private function settingEnabled(string $key, bool $default = false): bool
    {
        return filter_var(AppSetting::value($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizedFirebasePhone(string $phone): string
    {
        $phone = trim($phone);
        if (! str_starts_with($phone, '+')) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return '';
        }

        return '+'.$digits;
    }

    private function mobileUserForFirebasePhone(string $firebaseUid, string $phone): MobileUser
    {
        return MobileUser::query()
            ->where('firebase_uid', $firebaseUid)
            ->orWhere('phone_normalized', $phone)
            ->orWhere('phone', $phone)
            ->first()
            ?: new MobileUser();
    }

    private function phonePlaceholderEmail(string $phone): string
    {
        return 'phone-'.hash('sha256', $phone).'@phone.goshen.local';
    }

    private function churchGroupPayload(ChurchGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'functions' => $group->functions ?? '',
            'leader_name' => $group->leader?->name ?? '',
            'leader_avatar' => MediaUrl::resolve($group->leader?->avatar) ?: '',
            'assistant_name' => $group->assistant?->name ?? '',
            'assistant_avatar' => MediaUrl::resolve($group->assistant?->avatar) ?: '',
            'members_count' => (int) ($group->members_count ?? $group->members()->count()),
            'members' => $group->members
                ->map(fn (MobileUser $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone' => $member->phone ?? '',
                    'avatar' => MediaUrl::resolve($member->avatar) ?: '',
                ])
                ->values(),
            'is_active' => $group->is_active,
            'sort_order' => $group->sort_order,
        ];
    }

    private function groupJoinRequestPayload(ChurchGroupJoinRequest $joinRequest): array
    {
        $user = $joinRequest->mobileUser;

        return [
            'id' => $joinRequest->id,
            'status' => $joinRequest->status,
            'message' => $joinRequest->message ?? '',
            'created_at' => $joinRequest->created_at?->toIso8601String(),
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'phone' => $user?->phone ?? '',
                'gender' => $user?->gender ?? '',
                'avatar' => MediaUrl::resolve($user?->avatar) ?: '',
                'about_me' => $user?->bio ?? '',
            ],
        ];
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();
        $user = null;

        if ($token) {
            $user = MobileUser::where('api_token_hash', hash('sha256', $token))->first();
        } elseif (! empty($data['email'])) {
            $user = MobileUser::where('email', $data['email'])->first();
        }

        $user?->markApiSeen();

        return $user;
    }

    private function mobileUserFromToken(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();

        if (! filled($token)) {
            return null;
        }

        $user = MobileUser::where('api_token_hash', hash('sha256', $token))->first();
        $user?->markApiSeen();

        return $user;
    }

    private function canManageAnyGroup(MobileUser $user): bool
    {
        return $user->hasAnyRole(['Group leader', 'Assistant group leader']) &&
            ChurchGroup::where('is_active', true)
                ->where(fn ($query) => $query->where('leader_id', $user->id)->orWhere('assistant_id', $user->id))
                ->exists();
    }

    private function canManageGroup(MobileUser $user, ?ChurchGroup $group): bool
    {
        return $group instanceof ChurchGroup &&
            $this->canManageAnyGroup($user) &&
            ((int) $group->leader_id === (int) $user->id || (int) $group->assistant_id === (int) $user->id);
    }

    private function canManageFundraising(MobileUser $user): bool
    {
        if (interface_exists(PermissionResolverContract::class)
            && app(PermissionResolverContract::class)->canManage($user)) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['superadmin', 'fundraisingmanager', 'eventmanager', 'goshenmanager', 'retreatmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageGoshenRegistration(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageGoshenVouchers(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_goshen_vouchers') || $user->can('manage_goshen_voucher')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'vouchermanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageGoshenQuiz(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_goshen_quiz') || $user->can('manage_goshen_quizzes')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'quizmanager', 'goshenquizmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageWalletWithdrawals(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_goshen_wallet_withdrawals')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'walletmanager', 'goshenwalletmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageDynamicForms(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_dynamic_forms') || $user->can('manage_on_demand_forms') || $user->can('manage_forms')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'formsmanager', 'dynamicformsmanager', 'ondemandformsmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageChurchEvents(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_church_events')
            || $user->can('manage_church_event')
            || $user->can('manage_events')
            || $user->can('manage_event')
            || $user->can('manage_content')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'eventsmanager', 'churcheventmanager', 'contentmanager', 'goshenmanager', 'retreatmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageVerseOfDay(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_verse_of_day')
            || $user->can('manage_verses')
            || $user->can('manage_devotionals')
            || $user->can('manage_devotional')
            || $user->can('manage_content')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'contentmanager', 'devotionalmanager', 'versemanager', 'verseofdaymanager', 'goshenmanager', 'retreatmanager', 'go', 'generaloverseer', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageMobileUsers(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_mobile_users')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function canManageCounseling(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('counseling.triage')
            || $user->can('counseling.assign')
            || $user->can('counseling.respond')
            || $user->can('counseling.settings')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'counselor', 'counsellingteam', 'counselingteam', 'pastor', 'triageteam', 'triumphantitmanager'],
                true,
            ));
    }

    private function canSendAdminMessages(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('send_admin_messages') || $user->can('manage_inbox_message') || $user->can('manage_inbox_messages')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'messagingmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function visibleInboxQuery(?MobileUser $user)
    {
        $query = InboxMessage::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));

        if ($user) {
            $deletedIds = DB::table('inbox_message_deletions')
                ->where('mobile_user_id', $user->id)
                ->pluck('inbox_message_id');

            $query->when($deletedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $deletedIds));

            $disabledCategories = collect($user->effectiveNotificationPreferences())
                ->filter(fn (bool $enabled): bool => ! $enabled)
                ->keys()
                ->values();

            if ($disabledCategories->isNotEmpty()) {
                $query->where(function ($categoryQuery) use ($disabledCategories): void {
                    if (! $disabledCategories->contains('general')) {
                        $categoryQuery->whereNull('notification_category')
                            ->orWhereNotIn('notification_category', $disabledCategories->all());

                        return;
                    }

                    $categoryQuery->whereNotNull('notification_category')
                        ->whereNotIn('notification_category', $disabledCategories->all());
                });
            }
        }

        $roleIds = $user ? $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->values() : collect();

        return $query->where(function ($query) use ($user, $roleIds) {
            $query->whereNull('recipient_mode')
                ->orWhere('recipient_mode', '')
                ->orWhere('recipient_mode', 'all');

            if (! $user) {
                return;
            }

            $query->orWhere(function ($selected) use ($user) {
                $selected->where('recipient_mode', 'selected')
                    ->where(function ($recipient) use ($user) {
                        $recipient->whereJsonContains('selected_mobile_user_ids', (int) $user->id)
                            ->orWhereJsonContains('selected_mobile_user_ids', (string) $user->id);
                    });
            });

            $query->orWhere(function ($delivered) use ($user) {
                $delivered->whereJsonContains('delivered_mobile_user_ids', (int) $user->id)
                    ->orWhereJsonContains('delivered_mobile_user_ids', (string) $user->id);
            });

            if ($user->group_id) {
                $query->orWhere(function ($groups) use ($user) {
                    $groups->where('recipient_mode', 'groups')
                        ->where(function ($recipient) use ($user) {
                            $recipient->whereJsonContains('selected_church_group_ids', (int) $user->group_id)
                                ->orWhereJsonContains('selected_church_group_ids', (string) $user->group_id);
                        });
                });
            }

            if (filled($user->country_of_residence)) {
                $query->orWhere(function ($countries) use ($user) {
                    $countries->where('recipient_mode', 'countries')
                        ->whereJsonContains('selected_country_of_residences', $user->country_of_residence);
                });
            }

            if (filled($user->state_county_province)) {
                $query->orWhere(function ($states) use ($user) {
                    $states->where('recipient_mode', 'states')
                        ->whereJsonContains('selected_states_counties_provinces', $user->state_county_province)
                        ->where(function ($recipientCountry) use ($user) {
                            $recipientCountry
                                ->whereNull('selected_country_of_residences')
                                ->orWhereJsonLength('selected_country_of_residences', 0);

                            if (filled($user->country_of_residence)) {
                                $recipientCountry->orWhereJsonContains('selected_country_of_residences', $user->country_of_residence);
                            }
                        });
                });
            }

            if (filled($user->gender)) {
                $query->orWhere(function ($genders) use ($user) {
                    $genders->where('recipient_mode', 'genders')
                        ->whereJsonContains('selected_genders', $user->gender);
                });
            }

            if ($roleIds->isNotEmpty()) {
                $query->orWhere(function ($roles) use ($roleIds) {
                    $roles->where('recipient_mode', 'roles')
                        ->where(function ($recipient) use ($roleIds) {
                            foreach ($roleIds as $roleId) {
                                $recipient->orWhereJsonContains('selected_role_ids', $roleId)
                                    ->orWhereJsonContains('selected_role_ids', (string) $roleId);
                            }
                        });
                });
            }
        });
    }

    private function inboxPayload(InboxMessage $message, ?MobileUser $user = null): array
    {
        $personalization = app(MessagePersonalizationService::class);
        $title = $personalization->renderText((string) $message->title, $user, $message);
        $content = $personalization->renderHtml((string) ($message->content ?? ''), $user, $message);

        return [
            'id' => $message->legacy_id ?? $message->id,
            'title' => $title,
            'message' => $content,
            'content' => $content,
            'thumbnail' => MediaUrl::resolve($message->thumbnail) ?: '',
            'image_url' => MediaUrl::resolve($message->thumbnail) ?: '',
            'tone_enabled' => (bool) $message->notification_tone_enabled,
            'tone_url' => $message->notification_tone_enabled ? (MediaUrl::resolve($message->notification_tone_path) ?: '') : '',
            'tone_label' => $message->notification_tone_label ?: '',
            'notification_category' => $message->notification_category ?: 'general',
            'date' => optional($message->published_at ?? $message->created_at)->timestamp ?? now()->timestamp,
            'published_at' => optional($message->published_at ?? $message->created_at)->toDateTimeString(),
        ];
    }

    private function notificationSettingsPayload(MobileUser $user, string $message): array
    {
        $preferences = $user->effectiveNotificationPreferences();

        return [
            'status' => 'ok',
            'msg' => $message,
            'message' => $message,
            'notification_preferences' => $preferences,
            'categories' => collect(MobileUser::notificationPreferenceDefinitions())
                ->map(fn (array $category): array => [
                    ...$category,
                    'enabled' => (bool) ($preferences[$category['key']] ?? true),
                ])
                ->values(),
            // Keep the legacy shape stable for older clients that still read these keys.
            'user' => [
                'show_phone' => 1,
                'show_dateofbirth' => 1,
                'notify_follows' => 1,
                'notify_comments' => 1,
                'notify_likes' => 1,
            ],
        ];
    }

    private function normalizeNotificationPreferences(array $submitted): array
    {
        return collect(MobileUser::defaultNotificationPreferences())
            ->mapWithKeys(function (bool $default, string $key) use ($submitted): array {
                $value = $submitted[$key] ?? $default;

                return [$key => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false];
            })
            ->all();
    }

    private function sendMobileVerificationCode(MobileUser $user): void
    {
        $code = $this->newMobileCode();

        $user->forceFill([
            'email_verification_code_hash' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(30),
        ])->save();

        try {
            app(DynamicSmtpMailer::class)->sendRaw(
                $user->email,
                'Verify your MFM Triumphant Church account',
                "Hello {$user->name},\n\nUse this code to verify your MFM Triumphant Church account: {$code}\n\nThis code expires in 30 minutes.\n\nGod bless you.",
            );
        } catch (\Throwable) {
            // The app still shows the verification step; admin can configure SMTP and resend.
        }
    }

    private function newMobileCode(): string
    {
        return Str::upper(Str::random(6));
    }

    private function validMobileCode(?string $hash, string $code): bool
    {
        return filled($hash) && Hash::check(Str::upper(trim($code)), $hash);
    }

    private function mediaSource(MediaItem $media): ?string
    {
        if (in_array($media->source_type, ['youtube_video', 'vimeo_video', 'dailymotion_video'], true)) {
            return $media->source;
        }

        return $media->source_url;
    }

    private function legacyVideoType(MediaItem $media): ?string
    {
        if ($media->video_type) {
            return $media->video_type;
        }

        if ($media->type !== 'video') {
            return $media->source_type;
        }

        if ($this->isYoutubeUrl($media->source)) {
            return 'youtube_video';
        }

        return match ($media->source_type) {
            'upload' => 'mp4_video',
            'external_url' => 'video_link',
            default => $media->source_type,
        };
    }

    private function eventPayload(ChurchEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'details' => $event->details ?? '',
            'venue' => $event->venue,
            'theme' => $event->theme,
            'bible_verse' => $event->bible_verse,
            'host' => $event->host,
            'other_ministers' => $event->other_ministers,
            'portrait_image' => $event->portrait_image_url,
            'event_schedule' => $this->eventSchedulePayload($event->event_schedule ?? []),
            'is_pilgrimage' => (bool) $event->is_pilgrimage,
            'pilgrimage_details' => $this->pilgrimagePayload($event->pilgrimage_details ?? []),
            'live_streaming_platforms' => collect($event->live_streaming_platforms ?? [])
                ->map(fn ($platform) => [
                    'platform' => is_array($platform) ? ($platform['platform'] ?? '') : '',
                    'url' => is_array($platform) ? ($platform['url'] ?? '') : '',
                ])
                ->filter(fn (array $platform) => filled($platform['platform']) || filled($platform['url']))
                ->values(),
            'invited_gospel_musicians' => collect($event->invited_gospel_musicians ?? [])
                ->map(fn ($musician) => [
                    'name' => is_array($musician) ? ($musician['name'] ?? '') : '',
                    'image' => is_array($musician) ? ($musician['image'] ?? '') : '',
                    'image_url' => is_array($musician) ? MediaUrl::resolve($musician['image'] ?? '') : null,
                ])
                ->filter(fn (array $musician) => filled($musician['name']) || filled($musician['image_url']))
                ->values(),
            'thumbnail' => $event->thumbnail_url ?? MediaUrl::resolve('reference/header.jpg'),
            'date' => optional($event->starts_at)->toDateString(),
            'time' => optional($event->starts_at)->format('g:i A'),
            'starts_at' => optional($event->starts_at)->toDateTimeString(),
            'ends_at' => optional($event->ends_at)->toDateTimeString(),
            'recurrence_type' => $event->recurrence_type ?? ChurchEvent::RECURRENCE_NONE,
            'recurrence_label' => $event->recurrenceLabel(),
            'recurring_parent_id' => $event->recurring_parent_id ?? null,
            'recurrence_occurrence_date' => $event->recurrence_occurrence_date ?? null,
            'registration_url' => $event->registration_url,
            'registration_availability' => $event->registration_availability ?? 'everywhere',
            'registration_label' => $this->registrationAvailabilityLabel($event->registration_availability ?? 'everywhere'),
        ];
    }

    private function eventSchedulePayload(array $schedule): array
    {
        return collect($schedule)
            ->filter(fn ($day): bool => is_array($day))
            ->map(fn (array $day): array => [
                'day_label' => $day['day_label'] ?? '',
                'date_label' => $day['date_label'] ?? '',
                'sessions' => collect($day['sessions'] ?? [])
                    ->filter(fn ($session): bool => is_array($session))
                    ->map(fn (array $session): array => [
                        'title' => $session['title'] ?? '',
                        'time' => $session['time'] ?? '',
                    ])
                    ->filter(fn (array $session): bool => filled($session['title']) || filled($session['time']))
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $day): bool => filled($day['day_label']) || filled($day['date_label']) || ! empty($day['sessions']))
            ->values()
            ->all();
    }

    private function pilgrimagePayload(array $details): array
    {
        return [
            'organizer' => $details['organizer'] ?? '',
            'packaged_by' => $details['packaged_by'] ?? '',
            'theme' => $details['theme'] ?? '',
            'country_venue' => $details['country_venue'] ?? '',
            'date_text' => $details['date_text'] ?? '',
            'ministering' => $details['ministering'] ?? '',
            'participation_fees' => collect($details['participation_fees'] ?? [])
                ->filter(fn ($fee): bool => is_array($fee))
                ->map(fn (array $fee): array => [
                    'label' => $fee['label'] ?? '',
                    'amount' => $fee['amount'] ?? '',
                    'note' => $fee['note'] ?? '',
                ])
                ->filter(fn (array $fee): bool => filled($fee['label']) || filled($fee['amount']) || filled($fee['note']))
                ->values()
                ->all(),
            'payment_details' => collect($details['payment_details'] ?? [])
                ->filter(fn ($payment): bool => is_array($payment))
                ->map(fn (array $payment): array => [
                    'title' => $payment['title'] ?? '',
                    'details' => $payment['details'] ?? '',
                ])
                ->filter(fn (array $payment): bool => filled($payment['title']) || filled($payment['details']))
                ->values()
                ->all(),
            'registration_contacts' => collect($details['registration_contacts'] ?? [])
                ->filter(fn ($contact): bool => is_array($contact))
                ->map(fn (array $contact): array => [
                    'name' => $contact['name'] ?? '',
                    'phone' => $contact['phone'] ?? '',
                ])
                ->filter(fn (array $contact): bool => filled($contact['name']) || filled($contact['phone']))
                ->values()
                ->all(),
        ];
    }

    private function contentPagePayload(ContentPage $page): array
    {
        return [
            'id' => $page->id,
            'type' => $page->type,
            'title' => $page->title,
            'slug' => $page->slug,
            'body' => $page->body ?? '',
            'hero_image' => $page->hero_image_url,
            'sections' => collect($page->sections ?? [])
                ->map(fn (array $section) => [
                    'title' => $section['title'] ?? '',
                    'body' => $section['body'] ?? '',
                    'image' => MediaUrl::resolve($section['image'] ?? null),
                    'sort_order' => (int) ($section['sort_order'] ?? 0),
                ])
                ->sortBy('sort_order')
                ->values(),
        ];
    }

    private function transportationPayload(TransportationArrangement $arrangement): array
    {
        $contacts = $arrangement->contactList();
        $primaryContact = $contacts[0] ?? ['name' => '', 'phone' => ''];

        return [
            'id' => $arrangement->id,
            'program_name' => $arrangement->program_name,
            'event_title' => $arrangement->event_title ?: $arrangement->program_name,
            'city_town' => $arrangement->city_town,
            'state' => $arrangement->state ?? '',
            'bus_location' => $arrangement->bus_location,
            'bus_type' => $arrangement->bus_type ?? '',
            'passenger_capacity' => $arrangement->passenger_capacity,
            'buses_available' => $arrangement->buses_available === null
                ? null
                : (int) $arrangement->buses_available,
            'driver_name' => $arrangement->driver_name ?? '',
            'driver_phone' => $arrangement->driver_phone ?? '',
            'contact_person_name' => $primaryContact['name'] ?? '',
            'contact_person_phone' => $primaryContact['phone'] ?? '',
            'contacts' => $contacts,
            'is_active' => $arrangement->is_active,
        ];
    }

    private function registrationAvailabilityLabel(string $value): string
    {
        return match ($value) {
            'nigeria' => 'Available in Nigeria',
            'outside_nigeria' => 'Outside Nigeria Only',
            default => 'Available everywhere',
        };
    }

    private function verifiedMobileUser(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();
        $query = MobileUser::query();

        if ($token) {
            $query->where('api_token_hash', hash('sha256', $token));
        } elseif (! empty($data['email'])) {
            $query->where('email', $data['email']);
        } else {
            return null;
        }

        $user = $query->first();

        if (! $user && $token && ! empty($data['email'])) {
            $user = MobileUser::where('email', $data['email'])->first();
        }

        if (! $user?->canUseCommunity()) {
            return null;
        }

        $user->markApiSeen();

        return $user;
    }

    private function isYoutubeUrl(?string $url): bool
    {
        $url = strtolower($url ?? '');

        return str_contains($url, 'youtube.com/') || str_contains($url, 'youtu.be/');
    }

    private function streamPayload(Stream $stream): array
    {
        return [
            'id' => $stream->id,
            'title' => $stream->title,
            'description' => $stream->description,
            'stream_url' => $stream->stream_url,
            'thumbnail' => $stream->thumbnail_url,
            'type' => $this->streamPlaybackType($stream),
            'stream_type' => $stream->type,
            'status' => $stream->is_active ? 1 : 0,
        ];
    }

    private function streamPlaybackType(Stream $stream): string
    {
        $url = strtolower($stream->stream_url ?? '');

        if (str_contains($url, '.m3u8')) {
            return 'm3u8';
        }

        if (str_starts_with($url, 'rtmp://')) {
            return 'rtmp';
        }

        if (str_contains($url, 'youtube.com/watch') || str_contains($url, 'youtu.be/')) {
            return 'youtube';
        }

        return $stream->type;
    }
}
