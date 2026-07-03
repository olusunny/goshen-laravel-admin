<?php

use App\Http\Controllers\Api\CompatibilityController;
use App\Http\Controllers\Api\AccommodationController;
use App\Http\Controllers\Api\PrayerCommunityController;
use App\Http\Controllers\Api\TestimonyController;
use App\Http\Controllers\Api\RetiredFeatureController;
use App\Http\Controllers\MemberAppController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('app/{path?}', MemberAppController::class)
    ->where('path', '.*')
    ->name('member.app');

Route::get('privacy', [CompatibilityController::class, 'page'])->defaults('type', 'privacy');
Route::get('terms', [CompatibilityController::class, 'page'])->defaults('type', 'terms');
Route::get('aboutus', [CompatibilityController::class, 'page'])->defaults('type', 'about');
Route::redirect('donate', '/admin/donations');

Route::withoutMiddleware([VerifyCsrfToken::class])->group(function () {
    Route::match(['get', 'post'], 'saveDonation', [RetiredFeatureController::class, 'manualDonation']);
    Route::match(['get', 'post'], 'donation_accounts', [RetiredFeatureController::class, 'manualDonation']);
    Route::match(['get', 'post'], 'accommodations/{any?}', [RetiredFeatureController::class, 'accommodationBooking'])
        ->where('any', '.*');
    Route::match(['get', 'post'], 'accommodation-bookings/{any?}', [RetiredFeatureController::class, 'accommodationBooking'])
        ->where('any', '.*');
    Route::match(['get', 'post'], 'my-accommodation-bookings', [RetiredFeatureController::class, 'accommodationBooking']);
    Route::match(['get', 'post'], 'paystack/accommodation/verify', [RetiredFeatureController::class, 'accommodationBooking']);
});

Route::controller(CompatibilityController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
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
        Route::match(['get', 'post'], 'verifyMobileEmail', 'verifyMobileEmail');
        Route::match(['get', 'post'], 'resendMobileVerification', 'resendMobileVerification');
        Route::match(['get', 'post'], 'requestPasswordReset', 'requestPasswordReset');
        Route::match(['get', 'post'], 'resetMobilePassword', 'resetMobilePassword');
        Route::match(['get', 'post'], 'resetPassword', 'requestPasswordReset');
        Route::match(['get', 'post'], 'member/me', 'memberMe');
        Route::match(['get', 'post'], 'storefcmtoken', 'storeFcmToken');
        Route::match(['get', 'post'], 'updateUserSocialFcmToken', 'storeFcmToken');
        Route::match(['get', 'post'], 'updateProfile', 'updateProfile');
        Route::match(['get', 'post'], 'content_page/{type}', 'contentPage');
        Route::match(['get', 'post'], 'submit_suggestion', 'submitSuggestion');
        Route::match(['get', 'post'], 'submit_contact', 'submitContact');
    });

Route::controller(AccommodationController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
        Route::get('admin/accommodation-bookings/export-csv', 'exportCsv')->middleware('auth');
        Route::get('admin/accommodation-bookings/{booking}/receipt', 'printReceipt')->whereNumber('booking')->middleware('auth');
    });

Route::controller(TestimonyController::class)
    ->prefix('testimonies')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
        Route::get('status', 'status');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{testimony}/audio', 'audio')->whereNumber('testimony');
    });

Route::controller(PrayerCommunityController::class)
    ->prefix('prayer-community')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
        Route::get('/', 'index');
        Route::get('prophetic-decree', 'activePropheticDecree');
        Route::post('prophetic-decree', 'replacePropheticDecree');
        Route::get('prophetic-decree/{propheticDecree}/audio', 'propheticDecreeAudio')->whereNumber('propheticDecree');
        Route::get('{communityPrayerRequest}/audio', 'audio')->whereNumber('communityPrayerRequest');
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
