# MFM Triumphant Church Laravel Admin Feature Inventory

This Laravel rebuild replaces the old CodeIgniter admin/API while keeping the Flutter mobile app on Flutter. The old app remains the reference source at `../ChurchApp-Web-Project`.

## Included In This Rebuild

- Admin dashboard with Filament sidebar navigation.
- Role-based admin access: `super_admin`, `content_manager`, `moderator`, `finance`.
- Settings for app name, website URL, social links, donation settings, Firebase service account path, and mobile runtime options.
- Content pages: privacy, terms, about, and custom pages.
- Categories and subcategories.
- Audio, video, and music media library.
- Bible versions and JSON path tracking.
- Hymns.
- Devotionals.
- Prayer points and mobile prayer submissions.
- Events.
- Inbox messages.
- Branches.
- Livestream and radio stream metadata.
- FCM token storage and Firebase package configuration.
- Donations: donation records and donation settings only.
- Mobile-user records for fresh/hybrid import.
- Comment/moderation table for retained content interactions.
- Root-level legacy Flutter API compatibility routes plus `/api/v1` for future features.

## Explicitly Excluded

- CodeCanyon/envisionapps purchase code fields, invalidation toggles, and external license validation URLs.
- Subscription backend.
- In-app purchase backend.
- Coins.
- Paid media purchases.
- Paid book purchase flow.
- Stripe subscription controller.
- Legacy vendor gating of any kind.

## Flutter Compatibility Routes

The Flutter app currently builds URLs directly from `ApiUrl.BASEURL`, so root-level routes are provided for compatibility:

- `discover`
- `fetch_categories`
- `fetch_media`
- `fetch_categories_media`
- `search`
- `devotionals`
- `fetch_events`
- `fetch_prayerpoints`
- `submitprayer`
- `fetch_inbox`
- `fetch_hymns`
- `getBibleVersions`
- `church_branches`
- `discoverLivestreams`
- `loginUser`
- `registerUser`
- `storefcmtoken`
- `updateUserSocialFcmToken`
- `saveDonation`

Additional old social/chat/comment endpoints are reserved under `routes/api.php` so Flutter screens can fail softly while the fresh social/chat feature set is rebuilt.

## Migration Notes

- Hybrid import is the chosen strategy: import content, media, settings, donations, Bible, hymns, devotionals, events, inbox, prayers, branches, categories, livestreams, and radio.
- Reset admin users, mobile users, chats, social posts, follows, and old social/comment history unless explicitly requested later.
- Before production import, export the old MySQL schema with `SHOW CREATE TABLE` for all `tbl_*` tables and `settings`; the CodeIgniter installer is incomplete.
- Move media to Laravel storage for new uploads, but preserve `/uploads/...` links during transition for Flutter compatibility.

## Production Checklist

- Set `APP_URL`, database credentials, mail credentials, Firebase service account path, and admin bootstrap credentials in `.env`.
- Run `php artisan storage:link`.
- Run `php artisan migrate --force --seed`.
- Run API parity checks against the Flutter app before changing `ApiUrl.BASEURL`.
- Keep the old CodeIgniter app read-only until the Laravel cutover is proven.
