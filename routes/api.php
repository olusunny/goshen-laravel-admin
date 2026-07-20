<?php

use App\Http\Controllers\Api\AccommodationController;
use App\Http\Controllers\Api\AppSplashMediaController;
use App\Http\Controllers\Api\CompatibilityController;
use App\Http\Controllers\Api\ControlHubChurchEventController;
use App\Http\Controllers\Api\ControlHubMessagingController;
use App\Http\Controllers\Api\ControlHubMobileUserController;
use App\Http\Controllers\Api\ControlHubVerseOfDayController;
use App\Http\Controllers\Api\DonationStripeController;
use App\Http\Controllers\Api\DynamicFormController;
use App\Http\Controllers\Api\GoshenExperienceController;
use App\Http\Controllers\Api\GoshenQuizController;
use App\Http\Controllers\Api\GoshenRetreatController;
use App\Http\Controllers\Api\GoshenWalletController;
use App\Http\Controllers\Api\PrayerCommunityController;
use App\Http\Controllers\Api\PrayerPointController;
use App\Http\Controllers\Api\RetiredFeatureController;
use App\Http\Controllers\Api\TestimonyController;
use App\Http\Controllers\Api\V1\AdminCommunityPrayerRequestController;
use App\Http\Controllers\Api\V1\CommunityPrayerRequestController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'saveDonation', [RetiredFeatureController::class, 'manualDonation']);
Route::match(['get', 'post'], 'donation_accounts', [RetiredFeatureController::class, 'manualDonation']);
Route::match(['get', 'post'], 'accommodations/{any?}', [RetiredFeatureController::class, 'accommodationBooking'])
    ->where('any', '.*');
Route::match(['get', 'post'], 'accommodation-bookings/{any?}', [RetiredFeatureController::class, 'accommodationBooking'])
    ->where('any', '.*');
Route::match(['get', 'post'], 'my-accommodation-bookings', [RetiredFeatureController::class, 'accommodationBooking']);
Route::match(['get', 'post'], 'paystack/accommodation/verify', [RetiredFeatureController::class, 'accommodationBooking']);

Route::controller(CompatibilityController::class)->group(function () {
    Route::match(['get', 'post'], 'discover', 'discover');
    Route::match(['get', 'post'], 'verse_of_day', 'verseOfDay');
    Route::match(['get', 'post'], 'fetch_categories', 'categories');
    Route::match(['get', 'post'], 'gallery_images', 'gallery');
    Route::match(['get', 'post'], 'fetch_media', 'fetchMedia');
    Route::match(['get', 'post'], 'fetch_categories_media', 'fetchCategoriesMedia');
    Route::match(['get', 'post'], 'search', 'search');
    Route::match(['get', 'post'], 'devotionals', 'devotionals');
    Route::match(['get', 'post'], 'fetch_events', 'events');
    Route::match(['get', 'post'], 'fetch_prayerpoints', 'prayers');
    Route::match(['get', 'post'], 'submitprayer', 'submitPrayer');
    Route::match(['get', 'post'], 'fetch_inbox', 'inbox');
    Route::match(['get', 'post'], 'delete_inbox', 'deleteInbox');
    Route::match(['get', 'post'], 'fetch_user_settings', 'fetchUserSettings');
    Route::match(['get', 'post'], 'update_user_settings', 'updateUserSettings');
    Route::match(['get', 'post'], 'fetch_hymns', 'hymns');
    Route::match(['get', 'post'], 'getBibleVersions', 'bibleVersions');
    Route::match(['get', 'post'], 'church_branches', 'branches');
    Route::match(['get', 'post'], 'church_pastors', 'pastors');
    Route::match(['get', 'post'], 'church_groups', 'churchGroups');
    Route::match(['get', 'post'], 'church_groups/manage', 'manageChurchGroups');
    Route::match(['get', 'post'], 'church_groups/{churchGroup}/join', 'requestChurchGroupJoin')->whereNumber('churchGroup');
    Route::match(['get', 'post'], 'church_group_requests/{joinRequest}/review', 'reviewChurchGroupJoin')->whereNumber('joinRequest');
    Route::match(['get', 'post'], 'church_groups/{churchGroup}/members/{member}/remove', 'removeChurchGroupMember')->whereNumber('churchGroup')->whereNumber('member');
    Route::match(['get', 'post'], 'transportation_arrangements', 'transportationArrangements');
    Route::match(['get', 'post'], 'discoverLivestreams', 'streams');
    Route::match(['get', 'post'], 'discoverTrends', 'discoverTrends');
    Route::match(['get', 'post'], 'getmediatotallikesandcommentsviews', 'mediaTotals');
    Route::match(['get', 'post'], 'update_media_total_views', 'updateMediaViews');
    Route::match(['get', 'post'], 'likeunlikemedia', 'likeUnlikeMedia');
    Route::match(['get', 'post'], 'loginUser', 'loginUser');
    Route::match(['get', 'post'], 'syncMobileSession', 'syncMobileSession');
    Route::match(['get', 'post'], 'registerUser', 'registerUser');
    Route::match(['get', 'post'], 'googleAuth', 'googleAuth');
    Route::post('phoneAuth', 'phoneAuth')->middleware('throttle:6,1');
    Route::match(['get', 'post'], 'verifyMobileEmail', 'verifyMobileEmail');
    Route::match(['get', 'post'], 'resendMobileVerification', 'resendMobileVerification');
    Route::match(['get', 'post'], 'requestPasswordReset', 'requestPasswordReset');
    Route::match(['get', 'post'], 'resetMobilePassword', 'resetMobilePassword');
    Route::match(['get', 'post'], 'resetPassword', 'requestPasswordReset');
    Route::match(['get', 'post'], 'member/me', 'memberMe');
    Route::match(['get', 'post'], 'storefcmtoken', 'storeFcmToken');
    Route::match(['get', 'post'], 'updateUserSocialFcmToken', 'storeFcmToken');
    Route::match(['get', 'post'], 'updateProfile', 'updateProfile');
    Route::match(['get', 'post'], 'deleteaccount', 'deleteAccount');
    Route::match(['get', 'post'], 'content_page/{type}', 'contentPage');
    Route::match(['get', 'post'], 'submit_suggestion', 'submitSuggestion');
    Route::match(['get', 'post'], 'submit_contact', 'submitContact');

    Route::match(['get', 'post'], '{legacyEndpoint}', 'notImplemented')
        ->where('legacyEndpoint', 'makecomment|editcomment|deletecomment|loadcomments|replycomment|editreply|deletereply|loadreplies|reportcomment|counsellingrequest|get_users_to_follow|follow_unfollow_user|userNotifications|fetch_posts_flutter|likeunlikepost|pinunpinpost|post_likes_people|fetchUserPinsFlutter|userBioInfoFlutter|fetchUserPostsflutter|users_follow_people|make_post_flutter|editpost|deletepost|loadpostcomments|makepostcomment|editpostcomment|deletepostcomment|reportpostcomment|loadpostreplies|replypostcomment|editpostreply|deletepostreply|fetch_user_chats|fetch_user_partner_chat|save_user_conversation|on_seen_conversation|on_user_typing|update_user_online_status|delete_selected_chat_messages|clear_user_conversation|blockUnblockUser|load_more_chats|checkfornewmessages|fetch_books');
});

Route::controller(AccommodationController::class)->group(function () {
    Route::get('admin/accommodation-bookings/export-csv', 'exportCsv')->middleware('auth:sanctum');
});

Route::controller(TestimonyController::class)
    ->prefix('testimonies')
    ->group(function () {
        Route::get('status', 'status');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{testimony}/audio', 'audio')->whereNumber('testimony');
    });

Route::controller(GoshenRetreatController::class)
    ->prefix('goshen-retreat')
    ->group(function () {
        Route::get('status', 'status');
        Route::get('events', 'events');
        Route::get('events/{event}', 'event');
        Route::post('events/{event}/registration-status', 'updateRegistrationStatus');
        Route::post('events/{event}/management-summary', 'managementSummary');
        Route::post('events/{event}/setup', 'retreatSetup');
        Route::post('events/{event}/setup/overview', 'updateRetreatSetupOverview');
        Route::post('events/{event}/setup/schedules', 'saveRetreatSetupSchedule');
        Route::post('events/{event}/setup/schedules/{schedule}/delete', 'deleteRetreatSetupSchedule')->whereNumber('schedule');
        Route::post('events/{event}/setup/ticket-types', 'saveRetreatSetupTicketType');
        Route::post('events/{event}/setup/ticket-types/{ticketType}/delete', 'deleteRetreatSetupTicketType');
        Route::post('events/{event}/setup/registration-fields', 'saveRetreatSetupRegistrationField');
        Route::post('events/{event}/setup/registration-fields/{field}/delete', 'deleteRetreatSetupRegistrationField')->whereNumber('field');
        Route::post('events/{event}/accommodation-management', 'accommodationManagement');
        Route::post('accommodation-allocations', 'storeAccommodationAllocation');
        Route::post('accommodation-allocations/{allocation}', 'updateAccommodationAllocation')->whereNumber('allocation');
        Route::post('members/search', 'searchManagedMembers')->middleware('throttle:20,1');
        Route::post('members', 'storeManagedMember')->middleware('throttle:10,1');
        Route::post('bookings', 'storeBooking');
        Route::post('bookings/{booking}/cancel', 'cancelBooking');
        Route::post('bookings/{booking}/wallet-pay', 'payBookingWithWallet');
        Route::post('bookings/{booking}/voucher-pay', 'payBookingWithVoucher')->middleware('throttle:10,1');
        Route::post('bookings/{booking}/payments/{payment}/checkout', 'checkoutPayment');
        Route::post('vouchers/verify', 'verifyVoucher')->middleware('throttle:20,1');
        Route::post('vouchers/generate', 'generateVouchers')->middleware('throttle:10,1');
        Route::post('vouchers/usages', 'voucherUsages')->middleware('throttle:20,1');
        Route::match(['get', 'post'], 'referrals/summary', 'referralSummary');
        Route::post('referrals/convert', 'convertReferralPoints')->middleware('throttle:6,1');
        Route::get('tickets/{ticket}/qr.svg', 'ticketQrSvg');
        Route::match(['get', 'post'], 'tickets/{ticket}/documents/{type}', 'ticketDocument');
        Route::match(['get', 'post'], 'me', 'me');
        Route::post('scanner/status', 'scannerStatus');
        Route::post('scanner/operators', 'scannerOperators');
        Route::post('scanner/operators/{mobileUser}/toggle', 'toggleScannerOperator');
        Route::post('scanner/lookup', 'scannerLookup');
        Route::post('scanner/check-in', 'scannerCheckIn');
        Route::post('scanner/sync', 'scannerSync');
        Route::post('events/{event}/scanner-stats', 'scannerStats');
        Route::post('events/{event}/scanner-manifest', 'scannerManifest');
    });

Route::controller(GoshenRetreatController::class)
    ->prefix('v1/goshen-retreat/internal')
    ->group(function () {
        Route::post('tickets/{ticket}/check-ins', 'legacyScannerCheckIn');
        Route::post('tickets/{ticket}/days/{day}/check-ins', 'legacyScannerCheckIn')
            ->whereNumber('day');
    });

Route::controller(GoshenExperienceController::class)
    ->prefix('goshen-retreat/experience')
    ->group(function () {
        Route::match(['get', 'post'], '/', 'index');
        Route::get('surveys/{survey}', 'show')->whereNumber('survey');
        Route::post('surveys/{survey}/settings', 'updateSurveySettings')->whereNumber('survey');
        Route::post('surveys/{survey}', 'store')->whereNumber('survey');
        Route::post('events/{event}/stats', 'stats');
    });

Route::controller(DynamicFormController::class)
    ->prefix('dynamic-forms')
    ->group(function () {
        Route::match(['get', 'post'], '/', 'index');
        Route::post('stripe/webhook', 'webhook');
        Route::match(['get', 'post'], 'management', 'managementIndex')->middleware('throttle:20,1');
        Route::post('management/forms', 'managementStore')->middleware('throttle:12,1');
        Route::match(['get', 'post'], 'management/forms/{form}', 'managementShow');
        Route::post('management/forms/{form}/save', 'managementUpdate')->middleware('throttle:12,1');
        Route::post('management/forms/{form}/status', 'managementStatus')->middleware('throttle:20,1');
        Route::delete('management/forms/{form}', 'managementDestroy')->middleware('throttle:12,1');
        Route::post('management/forms/{form}/delete', 'managementDestroy')->middleware('throttle:12,1');
        Route::match(['get', 'post'], 'management/forms/{form}/submissions', 'managementSubmissions')->middleware('throttle:20,1');
        Route::match(['get', 'post'], '{form}', 'show');
        Route::post('{form}/submit', 'submit')->middleware('throttle:8,1');
    });

Route::controller(GoshenQuizController::class)
    ->prefix('goshen-quizzes')
    ->group(function () {
        Route::match(['get', 'post'], '/', 'index');
        Route::post('management/summary', 'managementSummary')->middleware('throttle:20,1');
        Route::match(['get', 'post'], '{quiz}', 'show')->whereNumber('quiz');
        Route::post('{quiz}/settings', 'updateSettings')->whereNumber('quiz');
        Route::post('{quiz}/start', 'start')->whereNumber('quiz')->middleware('throttle:12,1');
        Route::post('{quiz}/submit', 'submit')->whereNumber('quiz')->middleware('throttle:12,1');
        Route::get('{quiz}/winners', 'winners')->whereNumber('quiz');
        Route::post('{quiz}/winners/{winner}/wallet-prize', 'payWinnerPrize')
            ->whereNumber('quiz')
            ->whereNumber('winner')
            ->middleware('throttle:6,1');
    });

Route::controller(GoshenWalletController::class)
    ->prefix('goshen-wallet')
    ->group(function () {
        Route::match(['get', 'post'], '/', 'show');
        Route::match(['get', 'post'], 'security-reset/status', 'securityResetStatus');
        Route::post('security-reset/acknowledge', 'acknowledgeSecurityReset')->middleware('throttle:6,1');
        Route::post('goal', 'updateGoal');
        Route::post('goal/cancel', 'cancelGoal');
        Route::post('goals', 'createGoal');
        Route::post('goals/{goal}', 'updateGoalRecord')->whereNumber('goal');
        Route::post('goals/{goal}/cancel', 'cancelGoalRecord')->whereNumber('goal');
        Route::post('transfer', 'transfer')->middleware('throttle:6,1');
        Route::post('top-up/checkout', 'createTopUpCheckout');
        Route::post('top-up/voucher', 'redeemTopUpVoucher')->middleware('throttle:8,1');
        Route::post('withdrawals', 'createWithdrawal')->middleware('throttle:6,1');
        Route::match(['get', 'post'], 'withdrawals/management', 'managementWithdrawals');
        Route::post('withdrawals/{withdrawal}/cancel', 'cancelWithdrawal')->whereNumber('withdrawal');
        Route::post('withdrawals/{withdrawal}/management-status', 'updateWithdrawalStatus')->whereNumber('withdrawal');
        Route::post('savings-plans', 'createSavingsPlan');
        Route::post('savings-plans/{plan}', 'updateSavingsPlan')->whereNumber('plan');
        Route::post('stripe/webhook', 'stripeWebhook');
    });

Route::controller(ControlHubMessagingController::class)
    ->prefix('control-hub/messages')
    ->group(function () {
        Route::match(['get', 'post'], 'options', 'options');
        Route::post('send', 'send')->middleware('throttle:8,1');
    });

Route::controller(ControlHubMobileUserController::class)
    ->prefix('control-hub/mobile-users')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('search', 'index');
        Route::post('/', 'store')->middleware('throttle:10,1');
        Route::post('{mobileUser}', 'update')->whereNumber('mobileUser')->middleware('throttle:20,1');
        Route::delete('{mobileUser}', 'destroy')->whereNumber('mobileUser')->middleware('throttle:10,1');
        Route::post('{mobileUser}/delete', 'destroy')->whereNumber('mobileUser')->middleware('throttle:10,1');
    });

Route::controller(ControlHubChurchEventController::class)
    ->prefix('control-hub/church-events')
    ->group(function () {
        Route::get('/', 'index')->middleware('throttle:20,1');
        Route::post('search', 'index')->middleware('throttle:20,1');
        Route::post('/', 'store')->middleware('throttle:12,1');
        Route::post('{churchEvent}', 'update')->whereNumber('churchEvent')->middleware('throttle:12,1');
        Route::post('{churchEvent}/status', 'status')->whereNumber('churchEvent')->middleware('throttle:20,1');
        Route::delete('{churchEvent}', 'destroy')->whereNumber('churchEvent')->middleware('throttle:12,1');
        Route::post('{churchEvent}/delete', 'destroy')->whereNumber('churchEvent')->middleware('throttle:12,1');
    });

Route::controller(ControlHubVerseOfDayController::class)
    ->prefix('control-hub/verse-of-day')
    ->group(function () {
        Route::get('/', 'index')->middleware('throttle:20,1');
        Route::post('search', 'index')->middleware('throttle:20,1');
        Route::post('/', 'store')->middleware('throttle:12,1');
        Route::post('{verseOfDay}', 'update')->whereNumber('verseOfDay')->middleware('throttle:12,1');
        Route::post('{verseOfDay}/status', 'status')->whereNumber('verseOfDay')->middleware('throttle:20,1');
        Route::delete('{verseOfDay}', 'destroy')->whereNumber('verseOfDay')->middleware('throttle:12,1');
        Route::post('{verseOfDay}/delete', 'destroy')->whereNumber('verseOfDay')->middleware('throttle:12,1');
    });

Route::controller(DonationStripeController::class)
    ->prefix('giving/stripe')
    ->group(function () {
        Route::get('status', 'status');
        Route::match(['get', 'post'], 'history', 'history');
        Route::post('checkout', 'checkout');
        Route::post('webhook', 'webhook');
    });

Route::controller(DonationStripeController::class)
    ->prefix('giving/wallet')
    ->group(function () {
        Route::post('pay', 'payWithWallet')->middleware('throttle:6,1');
    });

Route::controller(PrayerCommunityController::class)
    ->prefix('prayer-community')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('prophetic-decree', 'activePropheticDecree');
        Route::post('prophetic-decree', 'replacePropheticDecree');
        Route::get('prophetic-decree/{propheticDecree}/audio', 'propheticDecreeAudio')->whereNumber('propheticDecree');
        Route::get('{communityPrayerRequest}/audio', 'audio')->whereNumber('communityPrayerRequest');
        Route::get('comments/{communityPrayerRequestComment}/audio', 'commentAudio')->whereNumber('communityPrayerRequestComment');
        Route::get('{communityPrayerRequest}', 'show')->whereNumber('communityPrayerRequest');
        Route::post('/', 'store');
        Route::post('{communityPrayerRequest}/comments', 'comment')->whereNumber('communityPrayerRequest');
        Route::post('{communityPrayerRequest}/flags', 'flag')->whereNumber('communityPrayerRequest');
        Route::post('ai/rewrite', 'aiRewrite');
        Route::post('ai/suggestions', 'aiSuggestions');
        Route::post('ai/bible-explain', 'aiBibleExplain');
        Route::post('ai/bible-search', 'aiBibleSearch');
        Route::post('profile/avatar', 'updateProfileImage');
    });

Route::controller(PrayerPointController::class)
    ->prefix('prayer-points')
    ->group(function () {
        Route::match(['get', 'post'], '/', 'index')->middleware('throttle:30,1');
    });

Route::controller(PrayerPointController::class)
    ->prefix('control-hub/prayer-points')
    ->group(function () {
        Route::get('/', 'managementIndex')->middleware('throttle:20,1');
        Route::post('search', 'managementIndex')->middleware('throttle:20,1');
        Route::post('/', 'store')->middleware('throttle:12,1');
        Route::post('{prayerPoint}', 'update')->whereNumber('prayerPoint')->middleware('throttle:12,1');
        Route::post('{prayerPoint}/status', 'status')->whereNumber('prayerPoint')->middleware('throttle:20,1');
        Route::delete('{prayerPoint}', 'destroy')->whereNumber('prayerPoint')->middleware('throttle:12,1');
        Route::post('{prayerPoint}/delete', 'destroy')->whereNumber('prayerPoint')->middleware('throttle:12,1');
    });

Route::prefix('v1')->group(function () {
    Route::get('health', [CompatibilityController::class, 'health']);
    Route::get('app/splash-media', [AppSplashMediaController::class, 'show'])
        ->middleware('throttle:60,1');

    Route::middleware('auth:mobile')->prefix('prayer-community')->group(function () {
        Route::get('requests', [CommunityPrayerRequestController::class, 'index']);
        Route::post('requests', [CommunityPrayerRequestController::class, 'store']);
        Route::get('prophetic-decree', [PrayerCommunityController::class, 'activePropheticDecree']);
        Route::post('prophetic-decree', [PrayerCommunityController::class, 'replacePropheticDecree']);
        Route::post('requests/{communityPrayerRequest}/comments', [CommunityPrayerRequestController::class, 'comment']);
        Route::get('requests/{communityPrayerRequest}/suggestions', [CommunityPrayerRequestController::class, 'suggestions']);
        Route::post('requests/{communityPrayerRequest}/flags', [CommunityPrayerRequestController::class, 'flag']);
    });

    Route::middleware('auth:sanctum')->prefix('admin/prayer-community')->group(function () {
        Route::get('requests', [AdminCommunityPrayerRequestController::class, 'index']);
        Route::post('requests/{communityPrayerRequest}/hide', [AdminCommunityPrayerRequestController::class, 'hide']);
        Route::post('requests/{communityPrayerRequest}/restore', [AdminCommunityPrayerRequestController::class, 'restore']);
        Route::get('requests/export', [AdminCommunityPrayerRequestController::class, 'export']);
    });
});
