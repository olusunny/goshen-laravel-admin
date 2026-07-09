<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccommodationBooking;
use App\Models\AccommodationCategory;
use App\Models\AccommodationPayment;
use App\Models\AppSetting;
use App\Services\AccommodationBookingService;
use App\Services\PaystackAccommodationService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class AccommodationController extends Controller
{
    public function __construct(
        private readonly AccommodationBookingService $bookings,
        private readonly PaystackAccommodationService $paystack,
    ) {
    }

    public function index()
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $this->bookings->expireOldPendingBookings();

        $categories = AccommodationCategory::with(['facilities', 'services', 'units'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AccommodationCategory $category) => $this->categoryPayload($category, false));

        return response()->json(['status' => 'ok', 'accommodations' => $categories]);
    }

    public function show(AccommodationCategory $accommodation)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        abort_unless($accommodation->is_active, 404);

        return response()->json([
            'status' => 'ok',
            'accommodation' => $this->categoryPayload($accommodation->load(['facilities', 'services', 'units']), true),
        ]);
    }

    public function checkAvailability(Request $request, AccommodationCategory $accommodation)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $validated = $this->validatedBookingData($request, false);

        try {
            $quote = $this->bookings->quote($accommodation, $validated);
            return response()->json(['status' => 'ok'] + $quote);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage(), 'available' => false], 422);
        }
    }

    public function createBooking(Request $request, AccommodationCategory $accommodation)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Please sign in to book accommodation.'], 401);
        }

        if ($response = $this->incompleteAccommodationProfileResponse($user)) {
            return $response;
        }

        $validated = $this->validatedBookingData($request, true);

        try {
            $booking = $this->bookings->createPendingBooking($accommodation, $user, $validated);
            return response()->json(['status' => 'ok', 'booking' => $this->bookingPayload($booking->load(['category', 'unit', 'user']))]);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function initializePayment(Request $request, AccommodationBooking $booking)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user || (int) $booking->user_id !== (int) $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Booking not found.'], 404);
        }

        if ($response = $this->incompleteAccommodationProfileResponse($user)) {
            return $response;
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'This booking has already been paid.'], 422);
        }

        try {
            $payment = $this->paystack->initialize($booking->load('user'));
            return response()->json(['status' => 'ok', 'payment' => $payment, 'booking' => $this->bookingPayload($booking->fresh(['category', 'unit', 'user']))]);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function cancelBooking(Request $request, AccommodationBooking $booking)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user || (int) $booking->user_id !== (int) $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Booking not found.'], 404);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Paid accommodation bookings cannot be cancelled from the app. Please contact accommodation support.',
            ], 422);
        }

        if (in_array($booking->booking_status, ['cancelled', 'expired', 'checked_in', 'checked_out'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This accommodation booking can no longer be cancelled from the app.',
            ], 422);
        }

        $booking->forceFill([
            'booking_status' => 'cancelled',
            'payment_status' => in_array($booking->payment_status, ['pending', 'failed'], true)
                ? 'cancelled'
                : $booking->payment_status,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Your unpaid accommodation booking has been cancelled.',
            'booking' => $this->bookingPayload($booking->fresh(['category', 'unit', 'user'])),
        ]);
    }

    public function verifyPayment(Request $request)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $reference = $this->payload($request)['reference'] ?? $request->input('reference');
        if (! $reference) {
            return response()->json(['status' => 'error', 'message' => 'Payment reference is required.'], 422);
        }

        try {
            $booking = $this->paystack->verify($reference);
            return response()->json(['status' => 'ok', 'booking' => $this->bookingPayload($booking)]);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function myBookings(Request $request)
    {
        if ($response = $this->legacyBookingRetiredResponse()) {
            return $response;
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Please sign in to view your bookings.'], 401);
        }

        $items = AccommodationBooking::with(['category', 'unit'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (AccommodationBooking $booking) => $this->bookingPayload($booking));

        return response()->json(['status' => 'ok', 'bookings' => $items]);
    }

    public function exportCsv(Request $request)
    {
        $rows = AccommodationBooking::with(['user', 'category', 'unit'])->latest()->get();

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Name', 'Email', 'Phone', 'Accommodation', 'Unit', 'Check-in', 'Checkout', 'Nights', 'Adults', 'Children', 'Amount', 'Payment', 'Booking', 'Check-in status', 'Checkout status', 'Created']);
            foreach ($rows as $booking) {
                fputcsv($handle, [
                    $booking->booking_reference,
                    $booking->user?->name,
                    $booking->user?->email,
                    $booking->user?->phone,
                    $booking->category?->name,
                    $booking->unit?->unit_name,
                    $booking->check_in_date?->toDateString(),
                    $booking->checkout_date?->toDateString(),
                    $booking->nights,
                    $booking->adults,
                    $booking->children,
                    $booking->total_amount,
                    $booking->payment_status,
                    $booking->booking_status,
                    $booking->check_in_status,
                    $booking->checkout_status,
                    $booking->created_at?->toDateTimeString(),
                ]);
            }
            fclose($handle);
        };

        return Response::streamDownload($callback, 'accommodation-bookings-' . now()->format('Ymd-His') . '.csv');
    }

    public function printReceipt(AccommodationBooking $booking)
    {
        abort_unless(auth()->check(), 403);

        $booking->load(['user', 'category', 'unit', 'payments']);
        $payment = $booking->payments->sortByDesc('created_at')->first();
        $support = [
            'name' => AppSetting::value('accommodation_booking_support_name', 'Accommodation Support'),
            'email' => AppSetting::value('accommodation_booking_support_email', ''),
            'phone' => AppSetting::value('accommodation_booking_support_phone', ''),
            'whatsapp' => AppSetting::value('accommodation_booking_support_whatsapp', ''),
            'instructions' => AppSetting::value('accommodation_booking_support_instructions', ''),
        ];

        $money = fn ($amount): string => 'NGN ' . number_format((float) $amount, 2);
        $date = fn ($value): string => $value ? e($value->format('M j, Y')) : 'Not set';
        $dateTime = fn ($value): string => $value ? e($value->format('M j, Y g:i A')) : 'Not set';

        $rows = [
            'Booking reference' => $booking->booking_reference,
            'Guest name' => $booking->user?->name,
            'Guest email' => $booking->user?->email,
            'Guest phone' => $booking->user?->phone,
            'Accommodation' => $booking->category?->name,
            'Unit' => $booking->unit?->unit_name,
            'Check in' => $date($booking->check_in_date),
            'Checkout' => $date($booking->checkout_date),
            'Nights' => $booking->nights,
            'Adults' => $booking->adults,
            'Children' => $booking->children,
            'Total occupants' => $booking->total_occupants,
            'Booking status' => str_replace('_', ' ', (string) $booking->booking_status),
            'Payment status' => str_replace('_', ' ', (string) $booking->payment_status),
            'Total amount' => $money($booking->total_amount),
            'Payment reference' => $payment?->paystack_reference,
            'Payment method' => $payment?->channel,
            'Payment date' => $dateTime($payment?->paid_at),
        ];

        $detailRows = collect($rows)
            ->map(fn ($value, $label): string => '<tr><th>' . e($label) . '</th><td>' . e((string) ($value ?: 'Not available')) . '</td></tr>')
            ->implode('');

        $supportLines = collect([
            $support['name'],
            $support['phone'] ? 'Phone: ' . $support['phone'] : null,
            $support['whatsapp'] ? 'WhatsApp: ' . $support['whatsapp'] : null,
            $support['email'] ? 'Email: ' . $support['email'] : null,
            $support['instructions'],
        ])->filter()->map(fn ($line) => '<p>' . e($line) . '</p>')->implode('');

        return response(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accommodation Receipt {$booking->booking_reference}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: #f4f7f8; color: #0c2230; }
        .page { max-width: 860px; margin: 32px auto; padding: 0 18px; }
        .receipt { background: #fff; border-radius: 22px; box-shadow: 0 24px 60px rgba(12, 34, 48, .12); overflow: hidden; }
        .hero { background: linear-gradient(135deg, #0c2230, #124253); color: #fff; padding: 32px; }
        .hero small { display: block; color: #f4c95d; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .hero h1 { margin: 8px 0 6px; font-size: 32px; line-height: 1.1; }
        .hero p { margin: 0; color: rgba(255,255,255,.78); }
        .section { padding: 28px 32px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 12px; border-bottom: 1px solid #e8eef1; text-align: left; vertical-align: top; }
        th { width: 38%; color: #61717a; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
        td { font-weight: 700; color: #0c2230; }
        .support { margin-top: 24px; padding: 18px; border-radius: 18px; background: #f7fafb; border: 1px solid #e4ecef; }
        .support h2 { margin: 0 0 10px; font-size: 18px; }
        .support p { margin: 4px 0; color: #41515a; }
        .actions { display: flex; gap: 12px; justify-content: flex-end; margin: 18px 0; }
        button { border: 0; border-radius: 999px; padding: 12px 20px; background: #f9b321; color: #0c2230; font-weight: 800; cursor: pointer; }
        .muted { background: #e9eff2; }
        @media print {
            body { background: #fff; }
            .page { margin: 0; max-width: none; padding: 0; }
            .receipt { box-shadow: none; border-radius: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="actions">
            <button class="muted" onclick="window.close()">Close</button>
            <button onclick="window.print()">Print receipt</button>
        </div>
        <article class="receipt">
            <header class="hero">
                <small>MFM Triumphant Church Accommodation</small>
                <h1>Booking Receipt</h1>
                <p>Reference: {$booking->booking_reference}</p>
            </header>
            <section class="section">
                <table>{$detailRows}</table>
                <div class="support">
                    <h2>Booking support</h2>
                    {$supportLines}
                </div>
            </section>
        </article>
    </main>
</body>
</html>
HTML);
    }

    private function validatedBookingData(Request $request, bool $requireRules): array
    {
        $data = $this->payload($request) ?: $request->all();
        $rules = [
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'checkout_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['nullable', 'integer', 'min:1'],
        ];
        if ($requireRules) {
            $rules['rules_accepted'] = ['accepted'];
        }

        return Validator::make($data, $rules)->validate();
    }

    private function categoryPayload(AccommodationCategory $category, bool $full): array
    {
        $images = collect($category->gallery_images ?? [])
            ->prepend($category->featured_image)
            ->filter()
            ->map(fn ($path) => MediaUrl::resolve($path))
            ->filter()
            ->values();
        $uploadedVideoUrl = MediaUrl::resolve($category->video_path);
        $externalVideoUrl = trim((string) ($category->video_url ?? ''));
        $videoUrl = $uploadedVideoUrl ?: $externalVideoUrl;
        $availableUnits = $category->units->where('is_active', true)->where('status', 'available')->count();

        return [
            'id' => $category->id,
            'name' => $category->name,
            'short_description' => $category->short_description ?? '',
            'description' => $category->description ?? '',
            'featured_image' => MediaUrl::resolve($category->featured_image) ?: '',
            'gallery_images' => $images,
            'video_url' => $videoUrl,
            'external_video_url' => $externalVideoUrl,
            'video_title' => $category->video_title ?: 'Watch facility video',
            'has_video' => filled($videoUrl),
            'price' => (float) $category->price,
            'price_label' => $this->money($category->price, $category->currency) . ($category->price_type === 'per_night' ? ' / night' : ''),
            'price_type' => $category->price_type,
            'currency' => $category->currency,
            'capacity' => $category->capacity,
            'max_adults' => $category->max_adults,
            'max_children' => $category->max_children,
            'children_allowed' => (bool) $category->children_allowed,
            'max_stay_days' => $category->max_stay_days,
            'check_in_time' => $category->check_in_time ?? '',
            'checkout_time' => $category->checkout_time ?? '',
            'rules' => $category->rules ?? '',
            'availability_label' => $availableUnits <= 0 ? 'Fully Booked' : ($availableUnits <= 2 ? 'Few Left' : 'Available'),
            'available_units' => $availableUnits,
            'facilities' => $full ? $category->facilities->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'icon' => $item->icon])->values() : [],
            'services' => $full ? $category->services->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'icon' => $item->icon, 'description' => $item->description])->values() : [],
        ];
    }

    private function bookingPayload(AccommodationBooking $booking): array
    {
        $payment = $booking->relationLoaded('payments')
            ? $booking->payments->sortByDesc('created_at')->first()
            : $booking->payments()->latest()->first();

        return [
            'id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'accommodation_id' => $booking->accommodation_category_id,
            'accommodation_category_id' => $booking->accommodation_category_id,
            'accommodation_name' => $booking->category?->name ?? '',
            'unit_name' => $booking->unit?->unit_name ?? '',
            'check_in_date' => $booking->check_in_date?->toDateString(),
            'checkout_date' => $booking->checkout_date?->toDateString(),
            'nights' => $booking->nights,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total_occupants' => $booking->total_occupants,
            'price_per_night' => (float) $booking->price_per_night,
            'total_amount' => (float) $booking->total_amount,
            'currency' => $booking->currency,
            'booking_status' => $booking->booking_status,
            'payment_status' => $booking->payment_status,
            'payment_reference' => $payment?->paystack_reference ?? '',
            'payment_method' => $payment?->channel ?? '',
            'payment_date' => $payment?->paid_at?->toDateTimeString() ?? '',
            'check_in_status' => $booking->check_in_status,
            'checkout_status' => $booking->checkout_status,
            'created_at' => $booking->created_at?->toDateTimeString(),
        ];
    }

    private function mobileUserFromRequest(Request $request)
    {
        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();
        $user = null;

        if ($token) {
            $user = \App\Models\MobileUser::where('api_token_hash', hash('sha256', $token))->first();
        } elseif (! empty($data['email'])) {
            $user = \App\Models\MobileUser::where('email', $data['email'])->first();
        }

        $user?->markApiSeen();

        return $user;
    }

    private function incompleteAccommodationProfileResponse($user)
    {
        $missing = $this->missingAccommodationProfileFields($user);
        if ($missing === []) {
            return null;
        }

        return response()->json([
            'status' => 'error',
            'code' => 'profile_incomplete',
            'message' => 'Please complete your profile before booking accommodation. Required: ' . implode(', ', $missing) . '.',
            'missing_fields' => $missing,
        ], 422);
    }

    private function missingAccommodationProfileFields($user): array
    {
        $missing = [];

        if (! filled($user->name)) {
            $missing[] = 'full name';
        }

        if (! filled($user->avatar)) {
            $missing[] = 'profile image';
        }

        if (! filled($user->cover_photo)) {
            $missing[] = 'profile cover image';
        }

        if (! $user->is_verified || ! $user->email_verified_at) {
            $missing[] = 'verified email address';
        }

        if (! filled($user->gender)) {
            $missing[] = 'gender';
        }

        if (! filled($user->country_of_residence)) {
            $missing[] = 'country of residence';
        }

        if (! filled($user->state_county_province)) {
            $missing[] = 'state/county/province';
        }

        if (! filled($user->phone)) {
            $missing[] = 'phone number';
        }

        return $missing;
    }

    private function payload(Request $request): array
    {
        $data = $request->input('data', []);
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($data) ? $data : [];
    }

    private function money($amount, string $currency): string
    {
        $symbol = $currency === 'NGN' ? '₦' : "{$currency} ";
        return $symbol . number_format((float) $amount);
    }

    private function legacyBookingRetiredResponse()
    {
        return response()->json([
            'status' => 'error',
            'code' => 'legacy_accommodation_retired',
            'message' => 'Self-service accommodation booking has been retired for this app. Goshen Retreat accommodation is assigned by authorized retreat staff after registration.',
        ], 410);
    }
}
