<?php

namespace App\Http\Controllers\Api;

use App\Models\AppSetting;
use App\Models\GoshenAccommodationAllocation;
use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenAccommodationEligibility;
use App\Services\GoshenBookingLifecycleService;
use App\Services\GoshenReferralService;
use App\Services\GoshenRegistrationAvailabilityService;
use App\Services\GoshenRegistrationFieldService;
use App\Services\GoshenRetreatNotificationService;
use App\Services\GoshenSingleFullPaymentService;
use App\Services\GoshenVoucherService;
use App\Services\GoshenWalletService;
use App\Services\WalletSecurityResetService;
use App\Support\MediaUrl;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketDocument;
use Personal\EventInstallments\Services\CheckInService;
use Personal\EventInstallments\Services\QrPayloadService;
use Personal\EventInstallments\Services\TicketDocumentService;
use Personal\EventInstallments\Services\TicketIssuer;
use RuntimeException;
use Sunny\Fundraising\Contracts\PermissionResolverContract;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GoshenRetreatController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->enabled(),
            'scanner_enabled' => $this->flag('goshen_scanner_enabled', true),
            'giving_enabled' => $this->flag('goshen_stripe_giving_enabled', true),
        ]);
    }

    public function events(): JsonResponse
    {
        abort_unless($this->enabled(), 404, 'Goshen Retreat is not currently available.');

        $events = $this->goshenEventsQuery()
            ->with(['schedules', 'ticketTypes', 'paymentPlans', 'attendeeFields'])
            ->where('status', 'published')
            ->orderBy('sales_start_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Event $event): array => $this->eventPayload($event));

        return response()->json(['data' => $events]);
    }

    public function event(string $event): JsonResponse
    {
        abort_unless($this->enabled(), 404, 'Goshen Retreat is not currently available.');
        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($event->status === 'published', 404);
        abort_unless($this->isGoshenEvent($event), 404);

        return response()->json(['data' => $this->eventPayload($event->load(['schedules', 'ticketTypes', 'paymentPlans', 'attendeeFields']))]);
    }

    public function updateRegistrationStatus(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($event->status === 'published', 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'registration_open' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $actor = $this->mobileUserFromToken($request);
        $settings = is_array($event->settings) ? $event->settings : [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];

        if ((bool) $validated['registration_open']) {
            $registration['override'] = 'open';
            $registration['reopened_at'] = now()->toIso8601String();
            $registration['reopened_by_mobile_user_id'] = $actor?->id;
            $registration['closed_at'] = null;
            $registration['closed_by_mobile_user_id'] = null;
            $registration['close_reason'] = null;
        } else {
            $registration['override'] = 'closed';
            $registration['closed_at'] = now()->toIso8601String();
            $registration['closed_by_mobile_user_id'] = $actor?->id;
            $registration['close_reason'] = trim((string) ($validated['reason'] ?? 'Registration closed by event manager.'));
        }

        $settings['registration'] = $registration;
        $event->forceFill(['settings' => $settings])->save();

        return response()->json([
            'status' => 'ok',
            'message' => (bool) $validated['registration_open']
                ? 'Registration has been reopened for this retreat edition.'
                : 'Registration has been closed for this retreat edition.',
            'data' => [
                'event' => $this->eventPayload($event->fresh(['schedules', 'ticketTypes', 'paymentPlans', 'attendeeFields'])),
            ],
        ]);
    }

    public function managementSummary(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($event->status === 'published', 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $event->loadMissing('ticketTypes');

        $bookings = Booking::query()
            ->with(['attendees.ticketType', 'installments'])
            ->where('event_id', $event->id)
            ->latest()
            ->get();

        $bookingsById = $bookings->keyBy('id');
        $bookingIds = $bookings->pluck('id');
        $transactions = PaymentTransaction::query()
            ->whereIn('booking_id', $bookingIds)
            ->latest('paid_at')
            ->latest()
            ->get();
        $transactionsByBooking = $transactions->groupBy('booking_id');
        $paidTransactions = $transactions->filter(fn (PaymentTransaction $transaction): bool => $this->paymentTransactionIsPaid($transaction));
        $attendees = $bookings->flatMap(fn (Booking $booking) => $booking->attendees)->values();
        $financialBookings = $bookings
            ->reject(fn (Booking $booking): bool => in_array($this->managementBookingStatus($booking), [
                BookingStatus::Cancelled->value,
                BookingStatus::Refunded->value,
            ], true))
            ->values();
        $financialBookingIds = $financialBookings->pluck('id')->all();
        $financialPaidTransactions = $paidTransactions
            ->filter(fn (PaymentTransaction $transaction): bool => in_array((int) $transaction->booking_id, $financialBookingIds, true));

        $paidAmount = $financialBookings->sum(fn (Booking $booking): float => min((float) $booking->total, $this->managementPaidTotal($booking)));
        $totalAmount = (float) $financialBookings->sum(fn (Booking $booking): float => (float) $booking->total);
        $balanceAmount = $financialBookings->sum(fn (Booking $booking): float => $this->managementBalanceAmount($booking));

        return response()->json([
            'status' => 'ok',
            'data' => [
                'event' => [
                    'public_id' => $event->public_id,
                    'name' => $event->name,
                    'currency' => $event->ticketTypes->first()?->currency ?? 'GBP',
                    'registration' => $this->registrationPayload($event),
                ],
                'totals' => [
                    'currency' => $event->ticketTypes->first()?->currency ?? 'GBP',
                    'registrations' => $bookings->count(),
                    'attendees' => $attendees->count(),
                    'paid_registrations' => $bookings
                        ->filter(fn (Booking $booking): bool => $this->managementBookingStatus($booking) === BookingStatus::Paid->value)
                        ->count(),
                    'pending_registrations' => $bookings
                        ->filter(fn (Booking $booking): bool => in_array($this->managementBookingStatus($booking), [
                            BookingStatus::Pending->value,
                            BookingStatus::DepositPaid->value,
                            BookingStatus::PartiallyPaid->value,
                        ], true))
                        ->count(),
                    'cancelled_registrations' => $bookings
                        ->filter(fn (Booking $booking): bool => $this->managementBookingStatus($booking) === BookingStatus::Cancelled->value)
                        ->count(),
                    'total_amount' => round($totalAmount, 2),
                    'paid_amount' => round($paidAmount, 2),
                    'balance_amount' => round($balanceAmount, 2),
                    'wallet_paid_amount' => round((float) $financialPaidTransactions
                        ->filter(fn (PaymentTransaction $transaction): bool => strtolower((string) $transaction->gateway) === 'wallet')
                        ->sum(fn (PaymentTransaction $transaction): float => (float) $transaction->amount), 2),
                    'voucher_paid_amount' => round((float) $financialPaidTransactions
                        ->filter(fn (PaymentTransaction $transaction): bool => strtolower((string) $transaction->gateway) === 'voucher')
                        ->sum(fn (PaymentTransaction $transaction): float => (float) $transaction->amount), 2),
                    'online_paid_amount' => round((float) $financialPaidTransactions
                        ->reject(fn (PaymentTransaction $transaction): bool => in_array(strtolower((string) $transaction->gateway), ['wallet', 'voucher'], true))
                        ->sum(fn (PaymentTransaction $transaction): float => (float) $transaction->amount), 2),
                ],
                'breakdowns' => [
                    'gender' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeCustomCode($attendee, 'gender', 'not_specified'),
                        fn (string $code): string => $this->genderLabel($code),
                    ),
                    'age_group' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeCustomCode($attendee, 'age_group', 'not_specified'),
                        fn (string $code): string => $this->ageGroupLabel($code),
                    ),
                    'free_church_bus_interest' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeCustomCode($attendee, 'free_church_bus_interest', 'no_thanks'),
                        fn (string $code): string => $this->freeChurchBusInterestLabel($code),
                    ),
                    'volunteer_department' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeCustomCode($attendee, 'volunteer_department', 'no_chance_at_the_moment'),
                        fn (string $code): string => $this->volunteerDepartmentLabel($code),
                    ),
                    'ticket_type' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeTextCode($attendee->ticketType?->name, 'not_provided'),
                        fn (string $code): string => $this->optionalTextLabel($code),
                    ),
                    'company' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeTextCode($attendee->company, 'not_provided'),
                        fn (string $code): string => $this->optionalTextLabel($code),
                    ),
                    'designation' => $this->breakdownRows(
                        $attendees,
                        fn (Attendee $attendee): string => $this->attendeeTextCode($attendee->designation, 'not_provided'),
                        fn (string $code): string => $this->optionalTextLabel($code),
                    ),
                    'booking_status' => $this->breakdownRows(
                        $bookings,
                        fn (Booking $booking): string => $this->managementBookingStatus($booking),
                        fn (string $code): string => $this->bookingStatusLabel($code),
                        fn (Booking $booking): float => $this->managementPaidTotal($booking),
                    ),
                    'payment_mode' => $this->breakdownRows(
                        $bookings,
                        fn (Booking $booking): string => $this->managementPaymentMode($booking, $transactionsByBooking->get($booking->id, collect())),
                        fn (string $code): string => $this->paymentModeLabel($code),
                        fn (Booking $booking): float => $this->managementPaidTotal($booking),
                    ),
                    'privacy_consent' => $this->breakdownRows(
                        $bookings,
                        fn (Booking $booking): string => $this->privacyConsentCode($booking),
                        fn (string $code): string => $this->privacyConsentLabel($code),
                    ),
                ],
                'registrations' => $bookings
                    ->take(100)
                    ->map(function (Booking $booking) use ($transactionsByBooking): array {
                        $status = $this->managementBookingStatus($booking);
                        $paymentMode = $this->managementPaymentMode($booking, $transactionsByBooking->get($booking->id, collect()));
                        $privacyConsent = $this->privacyConsentCode($booking);

                        return [
                            'public_id' => $booking->public_id,
                            'customer_name' => $booking->customer_name,
                            'customer_email' => $booking->customer_email,
                            'customer_phone' => $booking->customer_phone,
                            'currency' => $booking->currency,
                            'total' => (float) $booking->total,
                            'paid_total' => $this->managementPaidTotal($booking),
                            'balance' => $this->managementBalanceAmount($booking),
                            'attendees_count' => $booking->attendees->count(),
                            'status' => $status,
                            'status_label' => $this->bookingStatusLabel($status),
                            'payment_mode' => $paymentMode,
                            'payment_mode_label' => $this->paymentModeLabel($paymentMode),
                            'privacy_consent' => $privacyConsent,
                            'privacy_consent_label' => $this->privacyConsentLabel($privacyConsent),
                            'created_at' => $this->isoTimestamp($booking->created_at),
                        ];
                    })
                    ->values(),
                'attendees' => $attendees
                    ->take(250)
                    ->map(function (Attendee $attendee) use ($bookingsById): array {
                        $booking = $bookingsById->get($attendee->booking_id);
                        $customFields = is_array($attendee->custom_fields) ? $attendee->custom_fields : [];

                        return [
                            'public_id' => $attendee->public_id,
                            'name' => trim(($attendee->first_name ?? '').' '.($attendee->last_name ?? '')),
                            'email' => $attendee->email,
                            'phone' => $attendee->phone,
                            'company' => $attendee->company,
                            'designation' => $attendee->designation,
                            'ticket_type' => $attendee->ticketType?->name,
                            'booking_public_id' => $booking?->public_id,
                            'booking_status' => $booking ? $this->managementBookingStatus($booking) : null,
                            'gender' => $customFields['gender'] ?? 'not_specified',
                            'gender_label' => $this->genderLabel((string) ($customFields['gender'] ?? 'not_specified')),
                            'age_group' => $customFields['age_group'] ?? 'not_specified',
                            'age_group_label' => $this->ageGroupLabel((string) ($customFields['age_group'] ?? 'not_specified')),
                            'free_church_bus_interest' => $customFields['free_church_bus_interest'] ?? 'no_thanks',
                            'free_church_bus_interest_label' => $this->freeChurchBusInterestLabel((string) ($customFields['free_church_bus_interest'] ?? 'no_thanks')),
                            'volunteer_department' => $customFields['volunteer_department'] ?? 'no_chance_at_the_moment',
                            'volunteer_department_label' => $this->volunteerDepartmentLabel((string) ($customFields['volunteer_department'] ?? 'no_chance_at_the_moment')),
                        ];
                    })
                    ->values(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function retreatSetup(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        return $this->retreatSetupResponse($event, 'Retreat setup loaded.');
    }

    public function updateRetreatSetupOverview(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ei_events', 'slug')->ignore($event->id),
            ],
            'type' => ['nullable', Rule::in(array_column(EventType::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'timezone' => ['required', 'string', 'max:80'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'inquiry_phone' => ['nullable', 'string', 'max:50'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'registration_override' => ['required', 'string', Rule::in(['auto', 'open', 'closed'])],
            'registration_close_reason' => ['nullable', 'string', 'max:500'],
            'pay_in_full_discount' => ['nullable', 'array'],
            'pay_in_full_discount.enabled' => ['nullable', 'boolean'],
            'pay_in_full_discount.label' => ['nullable', 'string', 'max:120'],
            'pay_in_full_discount.type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'pay_in_full_discount.value' => ['nullable', 'numeric', 'min:0'],
            'pay_in_full_discount.starts_at' => ['nullable', 'date'],
            'pay_in_full_discount.ends_at' => ['nullable', 'date', 'after_or_equal:pay_in_full_discount.starts_at'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();
        $settings = is_array($event->settings) ? $event->settings : [];
        $settings['module'] = $settings['module'] ?? 'goshen_retreat';
        $settings['inquiry_phone'] = trim((string) ($validated['inquiry_phone'] ?? ''));

        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $registration['override'] = (string) $validated['registration_override'];
        $registration['close_reason'] = trim((string) ($validated['registration_close_reason'] ?? ''));
        if ($registration['override'] === 'closed') {
            $registration['closed_at'] = $registration['closed_at'] ?? now()->toIso8601String();
        } elseif ($registration['override'] === 'open') {
            $registration['reopened_at'] = $registration['reopened_at'] ?? now()->toIso8601String();
            $registration['closed_at'] = null;
        } else {
            $registration['closed_at'] = null;
            $registration['reopened_at'] = null;
        }
        $settings['registration'] = $registration;

        $discount = is_array($validated['pay_in_full_discount'] ?? null) ? $validated['pay_in_full_discount'] : [];
        $settings['pay_in_full_discount'] = [
            'enabled' => (bool) ($discount['enabled'] ?? false),
            'label' => trim((string) ($discount['label'] ?? 'Pay in full discount')),
            'type' => in_array(($discount['type'] ?? 'percentage'), ['percentage', 'fixed'], true)
                ? $discount['type']
                : 'percentage',
            'value' => round(max(0, (float) ($discount['value'] ?? 0)), 2),
            'starts_at' => $this->nullableIsoTimestamp($discount['starts_at'] ?? null),
            'ends_at' => $this->nullableIsoTimestamp($discount['ends_at'] ?? null),
        ];

        $event->forceFill([
            'name' => $validated['name'],
            'slug' => trim((string) ($validated['slug'] ?? '')) !== ''
                ? $validated['slug']
                : Str::slug((string) $validated['name']),
            'type' => $validated['type'] ?? ($event->type?->value ?? EventType::Sequential->value),
            'description' => $validated['description'] ?? null,
            'timezone' => $validated['timezone'],
            'venue_name' => $validated['venue_name'] ?? null,
            'venue_address' => $validated['venue_address'] ?? null,
            'start_date' => $this->nullableCarbon($validated['start_date'] ?? null),
            'end_date' => $this->nullableCarbon($validated['end_date'] ?? null),
            'support_email' => $validated['support_email'] ?? null,
            'sales_start_at' => $this->nullableCarbon($validated['sales_start_at'] ?? null),
            'sales_end_at' => $this->nullableCarbon($validated['sales_end_at'] ?? null),
            'settings' => $settings,
        ])->save();

        return $this->retreatSetupResponse($event, 'Retreat setup has been saved.');
    }

    public function saveRetreatSetupSchedule(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $data = $this->payload($request);
        $schedule = $this->scheduleFromEvent($event, $data['id'] ?? null);
        $validator = Validator::make($data, [
            'id' => ['nullable', 'integer'],
            'day_number' => ['required', 'integer', 'min:1', 'max:366'],
            'title' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        if (($data['id'] ?? null) && ! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Schedule could not be found for this retreat edition.'], 404);
        }

        $validated = $validator->validated();
        $schedule ??= new EventSchedule(['event_id' => $event->id]);
        $metadata = is_array($schedule->metadata) ? $schedule->metadata : [];
        $metadata['title'] = trim((string) ($validated['title'] ?? ''));

        $schedule->forceFill([
            'event_id' => $event->id,
            'day_number' => (int) $validated['day_number'],
            'starts_at' => Carbon::parse((string) $validated['starts_at']),
            'ends_at' => $this->nullableCarbon($validated['ends_at'] ?? null),
            'capacity' => array_key_exists('capacity', $validated) ? $validated['capacity'] : null,
            'metadata' => $metadata,
        ])->save();

        return $this->retreatSetupResponse($event, 'Schedule has been saved.');
    }

    public function deleteRetreatSetupSchedule(Request $request, string $event, int $schedule): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $schedule = $this->scheduleFromEvent($event, $schedule);
        abort_unless($schedule, 404);
        $schedule->delete();

        return $this->retreatSetupResponse($event, 'Schedule has been deleted.');
    }

    public function saveRetreatSetupTicketType(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $data = $this->payload($request);
        $ticketType = $this->ticketTypeFromEvent($event, $data['id'] ?? $data['public_id'] ?? null);
        $validator = Validator::make($data, [
            'id' => ['nullable'],
            'public_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'price' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'min_per_booking' => ['required', 'integer', 'min:1'],
            'max_per_booking' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        if (($data['id'] ?? $data['public_id'] ?? null) && ! $ticketType) {
            return response()->json(['status' => 'error', 'message' => 'Ticket type could not be found for this retreat edition.'], 404);
        }

        $validated = $validator->validated();
        $minPerBooking = (int) $validated['min_per_booking'];
        $maxPerBooking = array_key_exists('max_per_booking', $validated) && $validated['max_per_booking'] !== null
            ? (int) $validated['max_per_booking']
            : $minPerBooking;
        if ($maxPerBooking < $minPerBooking) {
            return response()->json(['status' => 'error', 'message' => 'Maximum per booking must be greater than or equal to the minimum per booking.'], 422);
        }

        $ticketType ??= new EventTicketType(['event_id' => $event->id]);
        $ticketType->forceFill([
            'event_id' => $event->id,
            'name' => $validated['name'],
            'sku' => trim((string) ($validated['sku'] ?? '')),
            'currency' => strtoupper((string) $validated['currency']),
            'price' => round((float) $validated['price'], 2),
            'capacity' => array_key_exists('capacity', $validated) ? $validated['capacity'] : null,
            'min_per_booking' => $minPerBooking,
            'max_per_booking' => $maxPerBooking,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ])->save();

        return $this->retreatSetupResponse($event, 'Ticket type has been saved.');
    }

    public function deleteRetreatSetupTicketType(Request $request, string $event, string $ticketType): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $ticketType = $this->ticketTypeFromEvent($event, $ticketType);
        abort_unless($ticketType, 404);

        $isUsed = BookingLine::query()->where('ticket_type_id', $ticketType->id)->exists()
            || Attendee::query()->where('ticket_type_id', $ticketType->id)->exists()
            || Ticket::query()->where('ticket_type_id', $ticketType->id)->exists();
        if ($isUsed) {
            return response()->json([
                'status' => 'error',
                'message' => 'This ticket type already has registrations or tickets. Deactivate it instead so historic records remain intact.',
            ], 422);
        }

        $ticketType->delete();

        return $this->retreatSetupResponse($event, 'Ticket type has been deleted.');
    }

    public function saveRetreatSetupRegistrationField(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $data = $this->payload($request);
        $field = $this->registrationFieldFromEvent($event, $data['id'] ?? null);
        $fieldId = $field?->id;
        $validator = Validator::make($data, [
            'id' => ['nullable', 'integer'],
            'key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('ei_event_attendee_fields', 'key')
                    ->where('event_id', $event->id)
                    ->ignore($fieldId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['text', 'textarea', 'select', 'single_select', 'image_select', 'color_select'])],
            'is_required' => ['nullable', 'boolean'],
            'is_unique' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['nullable', 'string', 'max:120'],
            'options.*.value' => ['nullable', 'string', 'max:120'],
            'options.*.image_path' => ['nullable', 'string', 'max:255'],
            'options.*.color_hex' => ['nullable', 'string', 'max:20'],
            'options.*.fee_amount' => ['nullable', 'numeric', 'min:0'],
            'options.*.fee_label' => ['nullable', 'string', 'max:120'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        if (($data['id'] ?? null) && ! $field) {
            return response()->json(['status' => 'error', 'message' => 'Registration field could not be found for this retreat edition.'], 404);
        }

        $validated = $validator->validated();
        $type = $this->normalizedSetupFieldType((string) $validated['type']);
        $options = $this->normalizedSetupFieldOptions($validated['options'] ?? []);
        if (in_array($type, GoshenRegistrationFieldService::OPTION_FIELD_TYPES, true) && $options === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Add at least one option for this registration field type.',
            ], 422);
        }

        $field ??= new EventAttendeeField(['event_id' => $event->id]);
        $field->forceFill([
            'event_id' => $event->id,
            'key' => $validated['key'],
            'label' => $validated['label'],
            'type' => $type,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_unique' => (bool) ($validated['is_unique'] ?? false),
            'options' => $options,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ])->save();

        return $this->retreatSetupResponse($event, 'Registration field has been saved.');
    }

    public function deleteRetreatSetupRegistrationField(Request $request, string $event, int $field): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $field = $this->registrationFieldFromEvent($event, $field);
        abort_unless($field, 404);
        $field->delete();

        return $this->retreatSetupResponse($event, 'Registration field has been deleted.');
    }

    public function accommodationManagement(Request $request, string $event): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($event->status === 'published', 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $allocations = GoshenAccommodationAllocation::query()
            ->with(['event', 'attendee.booking', 'attendee.ticketType', 'ticket.ticketType'])
            ->where('event_id', $event->id)
            ->latest('updated_at')
            ->get();

        $eligibleAttendees = Attendee::query()
            ->with(['booking.installments', 'ticket', 'ticketType'])
            ->whereHas('booking', function ($query) use ($event): void {
                $query
                    ->where('event_id', $event->id)
                    ->whereIn('status', [
                        BookingStatus::DepositPaid->value,
                        BookingStatus::PartiallyPaid->value,
                        BookingStatus::Paid->value,
                    ]);
            })
            ->whereHas('ticket', function ($query) use ($event): void {
                $query
                    ->where('event_id', $event->id)
                    ->whereIn('status', [
                        TicketStatus::NotCheckedIn->value,
                        TicketStatus::CheckedIn->value,
                        TicketStatus::Provisional->value,
                    ]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $allocationsByAttendee = $allocations->keyBy('attendee_id');
        $eligibleAttendeeIds = $eligibleAttendees->pluck('id')->all();
        $activeAllocations = $allocations
            ->reject(fn (GoshenAccommodationAllocation $allocation): bool => $allocation->status === 'removed')
            ->filter(fn (GoshenAccommodationAllocation $allocation): bool => in_array((int) $allocation->attendee_id, $eligibleAttendeeIds, true));

        return response()->json([
            'status' => 'ok',
            'data' => [
                'event' => [
                    'public_id' => $event->public_id,
                    'name' => $event->name,
                ],
                'totals' => [
                    'eligible_attendees' => $eligibleAttendees->count(),
                    'allocations' => $allocations->count(),
                    'allocated' => $activeAllocations->unique('attendee_id')->count(),
                    'unallocated' => max(0, $eligibleAttendees->count() - $activeAllocations->unique('attendee_id')->count()),
                    'assigned' => $allocations->where('status', 'assigned')->count(),
                    'changed' => $allocations->where('status', 'changed')->count(),
                    'removed' => $allocations->where('status', 'removed')->count(),
                ],
                'status_breakdown' => $this->breakdownRows(
                    $allocations,
                    fn (GoshenAccommodationAllocation $allocation): string => (string) ($allocation->status ?: 'assigned'),
                    fn (string $code): string => $this->accommodationStatusLabel($code),
                ),
                'eligible_attendees' => $eligibleAttendees
                    ->map(fn (Attendee $attendee): array => $this->accommodationEligibleAttendeePayload(
                        $attendee,
                        $allocationsByAttendee->get($attendee->id),
                    ))
                    ->values(),
                'allocations' => $allocations
                    ->map(fn (GoshenAccommodationAllocation $allocation): array => $this->accommodationManagementAllocationPayload($allocation))
                    ->values(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function storeAccommodationAllocation(Request $request, GoshenAccommodationEligibility $eligibility): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $manager = $this->mobileUserFromToken($request);
        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'event_id' => ['required', 'string'],
            'attendee_id' => ['required', 'integer'],
            'ticket_id' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'in:assigned,changed,removed'],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'bed' => ['nullable', 'string', 'max:255'],
            'check_in_note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $event = $this->eventFromKey((string) $validated['event_id']);
        if (! $event || $event->status !== 'published' || ! $this->isGoshenEvent($event)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected Goshen Retreat edition could not be found.',
            ], 404);
        }

        $validated['event_id'] = $event->id;

        try {
            $allocationData = $eligibility->validateAndHydrateAllocationData($validated);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?: 'The accommodation allocation could not be saved.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $allocationData['attendee_visible_details'] = $this->accommodationVisibleDetails($allocationData);
        $allocationData['assigned_by'] = $manager?->id;

        $allocation = GoshenAccommodationAllocation::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'attendee_id' => (int) $allocationData['attendee_id'],
            ],
            $allocationData,
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Accommodation allocation has been saved.',
            'allocation' => $this->accommodationManagementAllocationPayload($allocation->refresh()),
        ], 201);
    }

    public function updateAccommodationAllocation(
        Request $request,
        string $allocation,
        GoshenAccommodationEligibility $eligibility,
    ): JsonResponse {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $record = GoshenAccommodationAllocation::query()
            ->with(['event', 'attendee', 'ticket'])
            ->find($allocation);

        if (! $record || ! $record->event || ! $this->isGoshenEvent($record->event)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected accommodation allocation could not be found.',
            ], 404);
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'ticket_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:assigned,changed,removed'],
            'building' => ['sometimes', 'nullable', 'string', 'max:255'],
            'room' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bed' => ['sometimes', 'nullable', 'string', 'max:255'],
            'check_in_note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if ($validated === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Choose at least one accommodation field to update.',
            ], 422);
        }

        $allocationData = array_merge([
            'event_id' => $record->event_id,
            'attendee_id' => $record->attendee_id,
            'ticket_id' => $record->ticket_id,
            'status' => $record->status,
            'building' => $record->building,
            'room' => $record->room,
            'bed' => $record->bed,
            'check_in_note' => $record->check_in_note,
            'assigned_at' => $record->assigned_at,
        ], $validated);

        try {
            $allocationData = $eligibility->validateAndHydrateAllocationData($allocationData);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?: 'The accommodation allocation could not be saved.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $record->forceFill([
            'ticket_id' => $allocationData['ticket_id'] ?? null,
            'status' => $allocationData['status'],
            'building' => $allocationData['building'] ?? null,
            'room' => $allocationData['room'] ?? null,
            'bed' => $allocationData['bed'] ?? null,
            'check_in_note' => $allocationData['check_in_note'] ?? null,
            'attendee_visible_details' => $this->accommodationVisibleDetails($allocationData),
            'assigned_at' => $allocationData['assigned_at'],
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Accommodation allocation has been updated.',
            'allocation' => $this->accommodationManagementAllocationPayload($record->refresh()),
        ]);
    }

    public function me(GoshenReferralService $referrals): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = request()->user('mobile') ?? $this->mobileUserFromRequest(request());

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to view your Goshen Retreat registrations.',
            ], 401);
        }

        $bookings = Booking::query()
            ->with(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets.attendee', 'tickets.ticketType'])
            ->where('customer_id', $user->id)
            ->latest()
            ->get();

        $bookingIds = $bookings->pluck('id');

        return response()->json([
            'status' => 'ok',
            'data' => [
                'user' => [
                    'id' => $user?->id,
                    'triumphant_id' => $user?->triumphant_id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'phone' => $user?->phone,
                    'title' => $user?->title,
                    'gender' => $user?->gender,
                    'marital_status' => $user?->marital_status,
                    'country_of_residence' => $user?->country_of_residence,
                    'state_county_province' => $user?->state_county_province,
                    'profile_missing_fields' => $this->profileMissingFields($user),
                    'profile_needs_update' => $this->profileMissingFields($user) !== [],
                    'roles' => $user?->roles()->pluck('name')->values() ?? [],
                    'can_manage_goshen_registration' => $this->canManageGoshenRegistration($user),
                    'can_view_goshen_registration_stats' => $this->canManageGoshenRegistration($user),
                    'can_manage_goshen_vouchers' => $this->canManageGoshenVouchers($user),
                    'can_manage_goshen_quiz' => $this->canManageGoshenQuiz($user),
                    'can_manage_fundraising' => $this->canManageFundraising($user),
                    'can_manage_dynamic_forms' => $this->canManageDynamicForms($user),
                    'can_manage_mobile_users' => $this->canManageMobileUsers($user),
                ],
                'registrations' => $bookings
                    ->map(fn (Booking $booking): array => $this->bookingPayload($booking))
                    ->values(),
                'payment_history' => PaymentTransaction::query()
                    ->with(['booking.event', 'installment'])
                    ->whereIn('booking_id', $bookingIds)
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->map(fn (PaymentTransaction $transaction): array => $this->paymentTransactionPayload($transaction))
                    ->values(),
                'tickets' => Ticket::query()
                    ->with(['event', 'booking', 'attendee', 'ticketType'])
                    ->whereHas('booking', fn ($query) => $query->where('customer_id', $user->id))
                    ->latest('issued_at')
                    ->get()
                    ->map(fn (Ticket $ticket): array => $this->ticketPayload($ticket))
                    ->values(),
                'accommodation_allocations' => GoshenAccommodationAllocation::query()
                    ->with(['event', 'attendee.booking', 'ticket'])
                    ->where('status', '!=', 'removed')
                    ->whereHas('attendee.booking', fn ($query) => $query->where('customer_id', $user->id))
                    ->latest('assigned_at')
                    ->latest()
                    ->get()
                    ->map(fn (GoshenAccommodationAllocation $allocation): array => $this->accommodationAllocationPayload($allocation))
                    ->values(),
                'referral' => $referrals->summaryFor($user),
            ],
        ]);
    }

    public function storeBooking(
        Request $request,
        TicketIssuer $ticketIssuer,
        GoshenWalletService $wallets,
        WalletSecurityResetService $walletSecurityResets,
        GoshenReferralService $referrals,
        GoshenVoucherService $vouchers,
        GoshenRegistrationAvailabilityService $availability,
    ): JsonResponse {
        abort_unless($this->enabled(), 404, 'Goshen Retreat is not currently available.');

        $actor = $this->mobileUserFromRequest($request);
        if (! $actor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before registering for Goshen Retreat.',
            ], 401);
        }

        if (! $actor->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before registering for Goshen Retreat.',
            ], 403);
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'event_id' => ['required', 'string'],
            'managed_member_id' => ['nullable', 'integer', 'exists:mobile_users,id'],
            'payment_mode' => ['nullable', 'string', 'in:outright,wallet,voucher'],
            'voucher_code' => ['nullable', 'string', 'max:80'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'ticket_type_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'free_church_bus_consent' => ['nullable', 'boolean'],
            'uk_privacy_consent' => ['accepted'],
            'privacy_policy_version' => ['nullable', 'string', 'max:80'],
            'apply_pay_in_full_discount' => ['nullable', 'boolean'],
            'attendees' => ['required', 'array', 'min:1'],
            'attendees.*.first_name' => ['nullable', 'string', 'max:100'],
            'attendees.*.last_name' => ['nullable', 'string', 'max:100'],
            'attendees.*.email' => ['nullable', 'email', 'max:255'],
            'attendees.*.phone' => ['nullable', 'string', 'max:50'],
            'attendees.*.company' => ['nullable', 'string', 'max:255'],
            'attendees.*.designation' => ['nullable', 'string', 'max:255'],
            'attendees.*.gender' => ['nullable', 'string', 'max:80'],
            'attendees.*.age_group' => ['nullable', 'string', 'max:80'],
            'attendees.*.free_church_bus_interest' => ['nullable', 'string', 'max:80'],
            'attendees.*.volunteer_department' => ['nullable', 'string', 'max:120'],
            'attendees.*.custom_fields' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $user = $actor;
        if (! empty($validated['managed_member_id'])) {
            if (! $this->canManageGoshenRegistration($actor)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your account is not authorized to register members for Goshen Retreat.',
                ], 403);
            }

            $user = MobileUser::query()->find((int) $validated['managed_member_id']);
            if (! $user || ! $user->canUseCommunity()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected member account is not available for registration.',
                ], 422);
            }
        }

        $missingProfileFields = $this->profileMissingFields($user);
        if ($missingProfileFields !== []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please complete the member profile before registering for Goshen Retreat: '.implode(', ', $missingProfileFields).'.',
                'missing_profile_fields' => $missingProfileFields,
            ], 422);
        }

        try {
            return DB::transaction(function () use ($validated, $actor, $user, $ticketIssuer, $wallets, $walletSecurityResets, $referrals, $vouchers, $availability, $request): JsonResponse {
                $event = $this->goshenEventsQuery()
                    ->where('public_id', $validated['event_id'])
                    ->where('status', 'published')
                    ->first();

                if (! $event) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This Goshen Retreat edition is no longer available.',
                    ], 404);
                }

                $event->loadMissing('attendeeFields');
                if ($event->attendeeFields->isEmpty()) {
                    app(GoshenRegistrationFieldService::class)->ensureDefaultsForEvent($event);
                    $event->unsetRelation('attendeeFields');
                    $event->load('attendeeFields');
                }

                $registrationFields = app(GoshenRegistrationFieldService::class);
                [$attendees, $fieldErrors] = $registrationFields->normalizeSubmittedAttendees($event, $validated['attendees'] ?? []);

                if ($fieldErrors !== []) {
                    return response()->json([
                        'status' => 'error',
                        'message' => collect($fieldErrors)->first(),
                        'errors' => $fieldErrors,
                    ], 422);
                }

                if (! $this->registrationIsOpen($event)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $this->registrationClosedMessage($event),
                        'registration' => $this->registrationPayload($event),
                    ], 422);
                }

                $ticketType = EventTicketType::query()
                    ->where('event_id', $event->id)
                    ->where('public_id', $validated['ticket_type_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $ticketType) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please select a valid retreat ticket type.',
                    ], 422);
                }

                $quantity = (int) $validated['quantity'];
                if ($ticketType->max_per_booking && $quantity > (int) $ticketType->max_per_booking) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "You can only register up to {$ticketType->max_per_booking} attendee(s) for this ticket type at once.",
                    ], 422);
                }

                if ($quantity < (int) $ticketType->min_per_booking) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Please register at least {$ticketType->min_per_booking} attendee(s) for this ticket type.",
                    ], 422);
                }

                try {
                    [$user, $ticketType] = $availability
                        ->lockAndAssertAvailable($user, $ticketType, $quantity);
                } catch (ValidationException $exception) {
                    return response()->json([
                        'status' => 'error',
                        'message' => collect($exception->errors())->flatten()->first()
                            ?: 'This retreat ticket is no longer available.',
                        'errors' => $exception->errors(),
                    ], 422);
                }

                $paymentMode = in_array(($validated['payment_mode'] ?? ''), ['wallet', 'voucher'], true)
                    ? (string) $validated['payment_mode']
                    : 'outright';
                $isManagerAssisted = (int) $actor->id !== (int) $user->id;
                if ($isManagerAssisted && ! in_array($paymentMode, ['voucher'], true)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Manager-assisted registrations must be completed with a voucher code.',
                    ], 422);
                }

                try {
                    $referralCode = $referrals->acceptedCodeForReferee($user, $validated['referral_code'] ?? null);
                } catch (RuntimeException $exception) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $exception->getMessage(),
                    ], 422);
                }

                if (count($attendees) < $quantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please complete every required attendee field for every attendee.',
                    ], 422);
                }

                $freeChurchBusInterested = collect($attendees)
                    ->contains(fn (array $attendee): bool => ($attendee['free_church_bus_interest'] ?? 'no_thanks') === 'yes');

                $ticketSubtotal = (float) $ticketType->price * $quantity;
                $selectedOptionFees = $registrationFields->selectedOptionFees($event, $attendees, (string) $ticketType->currency);
                $optionFeeTotal = (float) ($selectedOptionFees['total'] ?? 0);
                $subtotal = round($ticketSubtotal + $optionFeeTotal, 2);
                $discount = $this->payInFullDiscount($event, $ticketSubtotal, (bool) ($validated['apply_pay_in_full_discount'] ?? true));
                $total = round(max(0, $subtotal - $discount['amount']), 2);
                $isFreeRegistration = $total <= 0;

                if (! $isFreeRegistration && $paymentMode === 'voucher') {
                    $voucherCode = trim((string) ($validated['voucher_code'] ?? ''));
                    if ($voucherCode === '') {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Please enter a valid voucher code to complete this registration.',
                        ], 422);
                    }

                    $verification = $vouchers->verify($voucherCode, $event, $total, (string) $ticketType->currency);
                    if (! ($verification['valid'] ?? false)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $verification['message'] ?? 'This voucher cannot be used for this registration.',
                            'voucher' => $verification['voucher'] ?? null,
                        ], 422);
                    }
                }

                if (! $isFreeRegistration && $paymentMode === 'wallet') {
                    try {
                        $walletSecurityResets->assertWalletActionsAllowed($user);
                    } catch (RuntimeException $exception) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $exception->getMessage(),
                            'wallet_security_reset' => $walletSecurityResets->statusPayload($user),
                        ], 423);
                    }

                    $wallet = $wallets->walletFor($user);
                    $walletCurrency = strtoupper((string) $wallet->currency);
                    $ticketCurrency = strtoupper((string) $ticketType->currency);

                    if ($walletCurrency !== $ticketCurrency) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Your wallet is in {$wallet->currency}, but this ticket is charged in {$ticketType->currency}. Please choose card payment or use a matching wallet currency.",
                        ], 422);
                    }

                    if ((float) $wallet->balance + 0.01 < $total) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Your wallet balance is not enough to complete this registration. Please top up your wallet or choose card payment.',
                        ], 422);
                    }
                }

                $bookingMetadata = [
                    'source' => 'flutter_mobile',
                    'payment_mode' => $paymentMode,
                    'country_of_residence' => $user->country_of_residence,
                    'state_county_province' => $user->state_county_province,
                    'free_church_bus_interest' => $freeChurchBusInterested ? 'yes' : 'no_thanks',
                    'free_church_bus_interest_at' => now()->toIso8601String(),
                    'uk_privacy_consent' => true,
                    'uk_privacy_consent_at' => now()->toIso8601String(),
                    'privacy_policy_version' => $validated['privacy_policy_version'] ?? 'uk-gdpr-2026-06',
                    'privacy_jurisdiction' => 'UK',
                    'pay_in_full_discount' => $discount,
                    'ticket_subtotal' => round($ticketSubtotal, 2),
                    'selected_option_fees' => $selectedOptionFees['items'] ?? [],
                    'selected_option_fee_total' => $optionFeeTotal,
                    'registered_by_mobile_user_id' => $actor->id,
                    'manager_assisted' => $isManagerAssisted,
                ];

                if ($referralCode) {
                    $bookingMetadata = array_merge($bookingMetadata, [
                        'referral_code' => $referralCode->code,
                        'referral_code_id' => $referralCode->id,
                        'referrer_mobile_user_id' => $referralCode->mobile_user_id,
                        'referral_status' => 'accepted_pending_payment',
                    ]);
                }

                $booking = Booking::query()->create([
                    'event_id' => $event->id,
                    'payment_plan_id' => null,
                    'customer_id' => $user->id,
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone,
                    'currency' => $ticketType->currency,
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'paid_total' => 0,
                    'status' => $isFreeRegistration ? BookingStatus::Paid : BookingStatus::Pending,
                    'payment_expires_at' => $isFreeRegistration ? null : now()->addDay(),
                    'metadata' => $bookingMetadata,
                ]);

                BookingLine::query()->create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'currency' => $booking->currency,
                    'unit_price' => $ticketType->price,
                    'line_total' => $ticketSubtotal,
                    'metadata' => ($discount['amount'] > 0 || $optionFeeTotal > 0) ? [
                        'discount_amount' => $discount['amount'],
                        'payable_total' => $total,
                        'selected_option_fees' => $selectedOptionFees['items'] ?? [],
                        'selected_option_fee_total' => $optionFeeTotal,
                    ] : null,
                ]);

                for ($index = 0; $index < $quantity; $index++) {
                    $attendee = $attendees[$index] ?? $attendees[0];
                    $customFields = is_array($attendee['_registration_custom_fields'] ?? null)
                        ? $attendee['_registration_custom_fields']
                        : [];
                    Attendee::query()->create([
                        'booking_id' => $booking->id,
                        'ticket_type_id' => $ticketType->id,
                        'first_name' => $attendee['first_name'] ?? null,
                        'last_name' => $attendee['last_name'] ?? null,
                        'email' => $attendee['email'] ?? $user->email,
                        'phone' => $attendee['phone'] ?? $user->phone,
                        'company' => $attendee['company'] ?? null,
                        'designation' => $attendee['designation'] ?? null,
                        'custom_fields' => array_merge([
                            'source' => 'flutter_mobile',
                            'attendee_index' => $index + 1,
                        ], $customFields),
                    ]);
                }

                if (! $isFreeRegistration) {
                    $installment = app(GoshenSingleFullPaymentService::class)->createForBooking($booking);
                    $installment->forceFill([
                        'metadata' => array_merge($installment->metadata ?? [], [
                            'payment_mode' => $paymentMode,
                            'label' => $optionFeeTotal > 0
                                ? 'Full ticket and selected option payment'
                                : ($discount['amount'] > 0 ? 'Full ticket payment after discount' : 'Full ticket payment'),
                            'discount_amount' => $discount['amount'],
                            'selected_option_fee_total' => $optionFeeTotal,
                        ]),
                    ])->save();
                }
                if (! $isFreeRegistration && $paymentMode === 'wallet') {
                    app(GoshenSingleFullPaymentService::class)->assertPayable($booking, $installment);
                    $wallet = $wallets->walletFor($user);
                    $wallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

                    if (strtoupper((string) $wallet->currency) !== strtoupper((string) $booking->currency)) {
                        throw new RuntimeException('Your wallet currency does not match this registration.');
                    }

                    if ((float) $wallet->balance + 0.01 < $total) {
                        throw new RuntimeException('Your wallet balance is not enough to complete this registration.');
                    }

                    $reference = 'gw_retreat_'.Str::ulid();
                    $wallet->forceFill([
                        'balance' => round(((float) $wallet->balance) - $total, 2),
                    ])->save();

                    $wallet->ledgerEntries()->create([
                        'type' => 'retreat_payment',
                        'status' => 'paid',
                        'currency' => $booking->currency,
                        'amount' => $total,
                        'gateway' => 'wallet',
                        'provider_reference' => $reference,
                        'metadata' => [
                            'booking_id' => $booking->id,
                            'booking_public_id' => $booking->public_id,
                            'event_name' => $booking->event?->name,
                            'request_ip' => $request->ip(),
                            'request_user_agent' => $request->userAgent(),
                        ],
                        'settled_at' => now(),
                    ]);

                    $installment->forceFill([
                        'paid_amount' => (float) $installment->amount,
                        'paid_at' => now(),
                        'status' => InstallmentStatus::Paid,
                        'metadata' => array_merge($installment->metadata ?? [], [
                            'payment_mode' => 'wallet',
                            'label' => 'Full ticket payment',
                            'wallet_reference' => $reference,
                        ]),
                    ])->save();

                    PaymentTransaction::query()->create([
                        'booking_id' => $booking->id,
                        'installment_id' => $installment->id,
                        'gateway' => 'wallet',
                        'provider_reference' => $reference,
                        'currency' => $booking->currency,
                        'amount' => $total,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payload' => [
                            'source' => 'goshen_wallet',
                            'wallet_id' => $wallet->id,
                            'ledger_reference' => $reference,
                            'request_ip' => $request->ip(),
                            'request_user_agent' => $request->userAgent(),
                        ],
                    ]);

                    $booking->forceFill([
                        'paid_total' => $total,
                        'status' => BookingStatus::Paid,
                        'payment_expires_at' => null,
                        'metadata' => array_merge($booking->metadata ?? [], ['paid_from_wallet' => true]),
                    ])->save();

                    $ticketIssuer->issueForBooking($booking->refresh());
                    $referrals->createPendingAwardForPaidBooking($booking->refresh());
                } elseif (! $isFreeRegistration && $paymentMode === 'voucher') {
                    $vouchers->redeemForBooking(
                        $booking,
                        $installment,
                        (string) $validated['voucher_code'],
                        $user,
                        $actor,
                        $isManagerAssisted ? 'control_hub_registration' : 'mobile_registration',
                        null,
                        [
                            'request_ip' => $request->ip(),
                            'request_user_agent' => $request->userAgent(),
                        ],
                    );
                } elseif ($isFreeRegistration) {
                    $ticketIssuer->issueForBooking($booking->refresh());
                    $referrals->createPendingAwardForPaidBooking($booking->refresh());
                }

                $booking = $booking->fresh(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets.event', 'tickets.booking', 'tickets.attendee', 'tickets.ticketType']) ?? $booking;

                return response()->json([
                    'status' => 'ok',
                    'message' => $isFreeRegistration || in_array($paymentMode, ['wallet', 'voucher'], true)
                        ? 'Your Goshen Retreat registration is confirmed. Your ticket is ready.'
                        : 'Your Goshen Retreat registration has been started. Please continue to payment when ready.',
                    'booking' => $this->bookingPayload($booking),
                ]);
            });
        } catch (RuntimeException $exception) {
            if (($validated['payment_mode'] ?? null) === 'voucher') {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 422);
            }

            throw $exception;
        }
    }

    public function referralSummary(Request $request, GoshenReferralService $referrals): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to view your Goshen referral code.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using Goshen Retreat referrals.',
            ], 403);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $referrals->summaryFor($user),
        ]);
    }

    public function searchManagedMembers(Request $request): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'query' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = trim((string) ($validator->validated()['query'] ?? ''));
        $members = MobileUser::query()
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($search) use ($query): void {
                    $search->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('triumphant_id', 'like', "%{$query}%");
                });
            })
            ->where('is_deleted', false)
            ->latest('last_seen_at')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'members' => $members
                    ->map(fn (MobileUser $member): array => $this->managedMemberPayload($member))
                    ->values(),
            ],
        ]);
    }

    public function storeManagedMember(Request $request): JsonResponse
    {
        if ($response = $this->registrationManagerAccessError($request)) {
            return $response;
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'title' => ['required', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:mobile_users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'string', 'in:male,female'],
            'marital_status' => ['required', 'string', Rule::in(array_keys(MobileUser::MARITAL_STATUS_OPTIONS))],
            'member_type' => ['required', 'string', 'max:80'],
            'country_of_residence' => ['required', 'string', 'max:120'],
            'state_county_province' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $name = trim($validated['first_name'].' '.$validated['last_name']);
        $member = MobileUser::query()->create([
            'name' => $name,
            'title' => $validated['title'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'gender' => $validated['gender'],
            'marital_status' => $validated['marital_status'],
            'member_type' => $validated['member_type'],
            'country_of_residence' => $validated['country_of_residence'],
            'state_county_province' => $validated['state_county_province'],
            'address' => $validated['address'],
            'password' => null,
            'login_type' => 'manager_assisted',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Member profile created.',
            'data' => [
                'member' => $this->managedMemberPayload($member),
            ],
        ]);
    }

    public function convertReferralPoints(
        Request $request,
        GoshenReferralService $referrals,
        GoshenWalletService $wallets,
        WalletSecurityResetService $walletSecurityResets,
    ): JsonResponse {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to convert your Goshen referral points.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before converting Goshen referral points.',
            ], 403);
        }

        try {
            $walletSecurityResets->assertWalletActionsAllowed($user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'wallet_security_reset' => $walletSecurityResets->statusPayload($user),
            ], 423);
        }

        try {
            $conversion = $referrals->convertValidatedPointsToWallet($user);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'data' => $referrals->summaryFor($user),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Your validated Goshen referral points have been converted to wallet fund.',
            'conversion' => [
                'reference' => $conversion['reference'],
                'points_converted' => $conversion['points_converted'],
                'wallet_amount' => $conversion['wallet_amount'],
                'currency' => $conversion['wallet']->currency,
            ],
            'data' => $referrals->summaryFor($user),
            'wallet' => $wallets->payload($conversion['wallet']),
        ]);
    }

    public function cancelBooking(Request $request, string $booking, GoshenBookingLifecycleService $lifecycle): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before cancelling this registration.',
            ], 401);
        }

        $booking = $this->bookingFromKey($booking);

        if (! $booking || (int) $booking->customer_id !== (int) $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration could not be found on your account.',
            ], 404);
        }

        $booking->loadMissing(['event', 'installments', 'tickets']);
        $status = $booking->status?->value ?? $booking->status;

        if ($status !== BookingStatus::Pending->value || (float) $booking->paid_total > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only unpaid pending registrations can be cancelled from the app.',
            ], 422);
        }

        $data = $this->payload($request);
        $reason = trim((string) ($data['reason'] ?? 'Cancelled by the attendee from the mobile app.'));

        $cancelled = $lifecycle->cancelBooking($booking, $reason, null, true);

        return response()->json([
            'status' => 'ok',
            'message' => 'Your pending Goshen Retreat registration has been cancelled.',
            'booking' => $this->bookingPayload($cancelled),
        ]);
    }

    public function payBookingWithWallet(
        Request $request,
        string $booking,
        GoshenWalletService $wallets,
        TicketIssuer $ticketIssuer,
        GoshenRetreatNotificationService $notifications,
        WalletSecurityResetService $walletSecurityResets,
        GoshenReferralService $referrals,
    ): JsonResponse {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before paying for this registration.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before paying for Goshen Retreat.',
            ], 403);
        }

        try {
            $walletSecurityResets->assertWalletActionsAllowed($user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'wallet_security_reset' => $walletSecurityResets->statusPayload($user),
            ], 423);
        }

        $bookingModel = $this->bookingFromKey($booking);
        if (! $bookingModel || (int) $bookingModel->customer_id !== (int) $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration could not be found on your account.',
            ], 404);
        }

        try {
            $paidBooking = DB::transaction(function () use ($bookingModel, $user, $wallets, $ticketIssuer, $referrals, $request) {
                $booking = Booking::query()
                    ->whereKey($bookingModel->id)
                    ->with(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $booking->event || ! $this->isGoshenEvent($booking->event)) {
                    throw new RuntimeException('This payment does not belong to a Goshen Retreat registration.');
                }

                $status = $booking->status?->value ?? (string) $booking->status;
                if (in_array($status, [BookingStatus::Paid->value, BookingStatus::Cancelled->value, BookingStatus::Refunded->value], true)) {
                    throw new RuntimeException('This registration is not open for wallet payment.');
                }

                $installment = PaymentInstallment::query()
                    ->where('booking_id', $booking->id)
                    ->orderBy('sequence')
                    ->lockForUpdate()
                    ->first();
                if (! $installment) {
                    throw new RuntimeException('This registration does not have one complete payment record.');
                }

                $fullPayments = app(GoshenSingleFullPaymentService::class);
                $fullPayments->assertPayable($booking, $installment);
                $fullPayments->assertNoLiveExternalCheckout($installment);
                $total = (float) $booking->total;
                $amountDue = $total;

                $wallet = $wallets->walletFor($user);
                $wallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

                if (strtoupper((string) $wallet->currency) !== strtoupper((string) $booking->currency)) {
                    throw new RuntimeException('Your wallet currency does not match this registration.');
                }

                if ((float) $wallet->balance + 0.01 < $amountDue) {
                    throw new RuntimeException('Your wallet balance is not enough to complete this registration.');
                }

                $reference = 'gw_retreat_'.Str::ulid();
                $wallet->forceFill([
                    'balance' => round(((float) $wallet->balance) - $amountDue, 2),
                ])->save();

                $wallet->ledgerEntries()->create([
                    'type' => 'retreat_payment',
                    'status' => 'paid',
                    'currency' => $booking->currency,
                    'amount' => $amountDue,
                    'gateway' => 'wallet',
                    'provider_reference' => $reference,
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'booking_public_id' => $booking->public_id,
                        'event_name' => $booking->event?->name,
                        'request_ip' => $request->ip(),
                        'request_user_agent' => $request->userAgent(),
                    ],
                    'settled_at' => now(),
                ]);

                $installment->forceFill([
                    'paid_amount' => (float) $installment->amount,
                    'paid_at' => now(),
                    'status' => InstallmentStatus::Paid,
                    'metadata' => array_merge($installment->metadata ?? [], [
                        'payment_mode' => 'wallet',
                        'label' => 'Full ticket payment',
                        'wallet_reference' => $reference,
                    ]),
                ])->save();

                PaymentTransaction::query()->create([
                    'booking_id' => $booking->id,
                    'installment_id' => $installment->id,
                    'gateway' => 'wallet',
                    'provider_reference' => $reference,
                    'currency' => $booking->currency,
                    'amount' => $amountDue,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payload' => [
                        'source' => 'goshen_wallet',
                        'wallet_id' => $wallet->id,
                        'ledger_reference' => $reference,
                        'request_ip' => $request->ip(),
                        'request_user_agent' => $request->userAgent(),
                    ],
                ]);

                $booking->forceFill([
                    'paid_total' => $total,
                    'status' => BookingStatus::Paid,
                    'payment_expires_at' => null,
                    'metadata' => array_merge($booking->metadata ?? [], ['paid_from_wallet' => true]),
                ])->save();

                $ticketIssuer->issueForBooking($booking->refresh());
                $referrals->createPendingAwardForPaidBooking($booking->refresh());

                return $booking->fresh(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets.event', 'tickets.booking', 'tickets.attendee', 'tickets.ticketType']);
            });
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $notifications->notifyUser(
            $user,
            'Goshen Retreat payment completed',
            "Hello {$user->name}, your Goshen Retreat registration has been paid from your wallet. Your ticket is now ready in the app.",
            'events',
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen Retreat registration has been paid from your wallet. Your ticket is ready.',
            'booking' => $this->bookingPayload($paidBooking),
            'wallet' => $wallets->payload($wallets->walletFor($user)),
        ]);
    }

    public function verifyVoucher(Request $request, GoshenVoucherService $vouchers): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before verifying a voucher.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using Goshen vouchers.',
            ], 403);
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'voucher_code' => ['required', 'string', 'max:80'],
            'event_id' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $event = null;
        if (filled($validated['event_id'] ?? null)) {
            $event = $this->eventFromKey((string) $validated['event_id']);
            if (! $event || ! $this->isGoshenEvent($event)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected Goshen Retreat edition could not be found.',
                ], 404);
            }
        }

        $verification = $vouchers->verify(
            (string) $validated['voucher_code'],
            $event,
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            $validated['currency'] ?? null,
        );

        return response()->json([
            'status' => ($verification['valid'] ?? false) ? 'ok' : 'error',
            'message' => $verification['message'],
            'data' => $verification,
        ], ($verification['valid'] ?? false) ? 200 : 422);
    }

    public function payBookingWithVoucher(
        Request $request,
        string $booking,
        GoshenVoucherService $vouchers,
        GoshenRetreatNotificationService $notifications,
    ): JsonResponse {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $actor = $this->mobileUserFromRequest($request);
        if (! $actor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before paying with a voucher.',
            ], 401);
        }

        if (! $actor->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using Goshen vouchers.',
            ], 403);
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'voucher_code' => ['required', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $bookingModel = $this->bookingFromKey($booking);
        if (! $bookingModel) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration could not be found.',
            ], 404);
        }

        $canManageVouchers = $this->canManageGoshenVouchers($actor);
        if ((int) $bookingModel->customer_id !== (int) $actor->id && ! $canManageVouchers) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration could not be found on your account.',
            ], 404);
        }

        if (! $bookingModel->event || ! $this->isGoshenEvent($bookingModel->event)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This payment does not belong to a Goshen Retreat registration.',
            ], 404);
        }

        $beneficiary = MobileUser::query()->whereKey($bookingModel->customer_id)->first();
        if (! $beneficiary) {
            return response()->json([
                'status' => 'error',
                'message' => 'The registration member account could not be found.',
            ], 422);
        }

        $installment = $bookingModel->installments()->orderBy('sequence')->first();
        if (! $installment) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration does not have one complete payment record.',
            ], 422);
        }

        try {
            app(GoshenSingleFullPaymentService::class)->assertPayable($bookingModel, $installment);
            $vouchers->redeemForBooking(
                $bookingModel,
                $installment,
                (string) $validator->validated()['voucher_code'],
                $beneficiary,
                $actor,
                $canManageVouchers && (int) $actor->id !== (int) $beneficiary->id
                    ? 'control_hub'
                    : 'mobile_existing_booking',
                null,
                [
                    'request_ip' => $request->ip(),
                    'request_user_agent' => $request->userAgent(),
                ],
            );
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $paidBooking = $bookingModel->fresh(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets.event', 'tickets.booking', 'tickets.attendee', 'tickets.ticketType']);

        if ($paidBooking && (int) $actor->id !== (int) $beneficiary->id) {
            $notifications->notifyUser(
                $beneficiary,
                'Goshen Retreat voucher payment completed',
                "Hello {$beneficiary->name}, your Goshen Retreat registration has been paid with a voucher by {$actor->name}. Your ticket is now ready in the app.",
                'events',
            );
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen Retreat registration has been paid with a voucher. Your ticket is ready.',
            'booking' => $this->bookingPayload($paidBooking ?? $bookingModel),
        ]);
    }

    public function generateVouchers(Request $request, GoshenVoucherService $vouchers): JsonResponse
    {
        if ($response = $this->voucherManagerAccessError($request)) {
            return $response;
        }

        $actor = $this->mobileUserFromToken($request);
        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'event_id' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'purpose' => ['required', Rule::in(array_keys(GoshenVoucher::purposeOptions()))],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (($validated['purpose'] ?? null) === GoshenVoucher::PURPOSE_WALLET_FUNDING) {
            $validated['event_id'] = null;
        } elseif (filled($validated['event_id'] ?? null)) {
            $event = $this->eventFromKey((string) $validated['event_id']);
            if (! $event || ! $this->isGoshenEvent($event)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected Goshen Retreat edition could not be found.',
                ], 404);
            }
            $validated['event_id'] = $event->id;
        }

        try {
            $created = $vouchers->createBulk($validated, $actor);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => count($created).' voucher code(s) generated.',
            'data' => collect($created)
                ->map(fn (array $item): array => [
                    'code' => $item['code'],
                    'voucher' => $vouchers->voucherPayload($item['voucher']),
                ])
                ->values(),
        ], 201);
    }

    public function voucherUsages(Request $request, GoshenVoucherService $vouchers): JsonResponse
    {
        if ($response = $this->voucherManagerAccessError($request)) {
            return $response;
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'event_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $event = null;
        if (filled($validated['event_id'] ?? null)) {
            $event = $this->eventFromKey((string) $validated['event_id']);
            if (! $event || ! $this->isGoshenEvent($event)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected Goshen Retreat edition could not be found.',
                ], 404);
            }
        }

        $usages = GoshenVoucherUsage::query()
            ->with(['voucher', 'event', 'booking', 'mobileUser', 'redeemedByMobileUser'])
            ->when($event, fn ($query) => $query->where('event_id', $event->id))
            ->latest()
            ->limit((int) ($validated['limit'] ?? 100))
            ->get();

        return response()->json([
            'status' => 'ok',
            'message' => 'Voucher usage loaded.',
            'data' => $usages->map(fn (GoshenVoucherUsage $usage): array => $vouchers->usagePayload($usage))->values(),
        ]);
    }

    public function checkoutPayment(Request $request, string $booking, string $payment, PaymentGateway $gateway): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before paying for this registration.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before paying for Goshen Retreat.',
            ], 403);
        }

        $booking = $this->bookingFromKey($booking);
        $installment = $this->installmentFromKey($payment);

        if (! $booking || ! $installment) {
            return response()->json([
                'status' => 'error',
                'message' => 'This payment link is no longer available.',
            ], 404);
        }

        if ((int) $booking->customer_id !== (int) $user->id || (int) $installment->booking_id !== (int) $booking->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This payment does not belong to your Goshen Retreat registration.',
            ], 404);
        }

        if (! $booking->event || ! $this->isGoshenEvent($booking->event)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This payment does not belong to a Goshen Retreat registration.',
            ], 404);
        }

        $bookingStatus = $booking->status?->value ?? $booking->status;
        if (in_array($bookingStatus, [BookingStatus::Paid->value, BookingStatus::Cancelled->value, BookingStatus::Refunded->value], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration is not open for payment.',
            ], 422);
        }

        $status = strtolower((string) ($installment->status?->value ?? $installment->status));
        if (in_array($status, ['paid', 'succeeded', 'completed', 'cancelled', 'refunded'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This payment has already been completed.',
            ], 422);
        }

        try {
            $checkoutPayload = DB::transaction(function () use ($booking, $installment, $gateway): array {
                $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
                $lockedRecord = PaymentInstallment::query()
                    ->whereKey($installment->id)
                    ->where('booking_id', $lockedBooking->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $fullPayments = app(GoshenSingleFullPaymentService::class);
                $fullPayments->assertPayable($lockedBooking, $lockedRecord);

                if ($active = $fullPayments->activeExternalCheckout($lockedRecord)) {
                    return $fullPayments->checkoutPayload($active);
                }

                $checkout = $gateway->createCheckout($lockedRecord);
                $checkoutPayload = $checkout->payload;
                if ($checkout->checkoutUrl
                    && ! data_get($checkoutPayload, 'url')
                    && ! data_get($checkoutPayload, 'data.authorization_url')) {
                    $checkoutPayload['url'] = $checkout->checkoutUrl;
                }
                $transaction = PaymentTransaction::query()->create([
                    'booking_id' => $lockedBooking->id,
                    'installment_id' => $lockedRecord->id,
                    'gateway' => $checkout->gateway,
                    'provider_reference' => $checkout->reference,
                    'currency' => $lockedRecord->currency,
                    'amount' => $lockedRecord->amount,
                    'status' => 'pending',
                    'payload' => $checkoutPayload,
                ]);

                return $fullPayments->checkoutPayload($transaction);
            });

            return response()->json([
                'status' => 'ok',
                'message' => 'Checkout is ready. Please complete payment securely.',
                'checkout' => $checkoutPayload,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment checkout is not available right now. Please try again shortly.',
            ], 500);
        }
    }

    public function scannerStatus(Request $request): JsonResponse
    {
        $user = $this->mobileUserFromToken($request);
        $scannerSuspended = $user ? $this->scannerSuspended($user) : false;

        return $this->scannerJson([
            'status' => 'ok',
            'data' => [
                'enabled' => $this->enabled(),
                'scanner_enabled' => $this->flag('goshen_scanner_enabled', true),
                'allowed' => $user ? $this->canScanGoshen($user) && ! $scannerSuspended : false,
                'manager_allowed' => $user ? $this->canManageScanners($user) : false,
                'scanner_suspended' => $scannerSuspended,
                'scanner_suspension_reason' => $scannerSuspended ? $user?->scanner_suspension_reason : null,
                'roles' => $user ? $user->roles()->pluck('name')->values()->all() : [],
            ],
        ]);
    }

    public function scannerOperators(Request $request): JsonResponse
    {
        if ($response = $this->scannerManagerAccessError($request)) {
            return $response;
        }

        $operators = MobileUser::query()
            ->with('roles')
            ->where('is_verified', true)
            ->where('is_deleted', false)
            ->whereHas('roles', function ($query): void {
                $query
                    ->where('guard_name', 'mobile')
                    ->whereIn('name', $this->scannerRoleNames());
            })
            ->orderByRaw('scanner_suspended_at is null desc')
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->get()
            ->map(fn (MobileUser $operator): array => $this->scannerOperatorPayload($operator))
            ->values();

        return $this->scannerJson([
            'status' => 'ok',
            'data' => [
                'operators' => $operators,
            ],
        ]);
    }

    public function toggleScannerOperator(Request $request, MobileUser $mobileUser): JsonResponse
    {
        if ($response = $this->scannerManagerAccessError($request)) {
            return $response;
        }

        $actor = $this->mobileUserFromToken($request);

        if ((int) $actor?->id === (int) $mobileUser->id) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'You cannot suspend your own scanner access from the mobile app.',
            ], 422);
        }

        if ($mobileUser->hasAnyRole(['super_admin', 'Super Admin'])) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Super admin scanner access cannot be suspended from the mobile app.',
            ], 422);
        }

        if (! $this->canScanGoshen($mobileUser)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'This user is not configured as a Goshen Retreat scanner.',
            ], 422);
        }

        $data = $this->payload($request);
        $suspend = filter_var($data['suspend'] ?? ! $this->scannerSuspended($mobileUser), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $suspend = $suspend ?? ! $this->scannerSuspended($mobileUser);

        if ($suspend) {
            $mobileUser->forceFill([
                'scanner_suspended_at' => now(),
                'scanner_suspension_reason' => trim((string) ($data['reason'] ?? 'Suspended by event manager')),
                'scanner_suspended_by' => $actor?->id,
            ])->save();
        } else {
            $mobileUser->forceFill([
                'scanner_suspended_at' => null,
                'scanner_suspension_reason' => null,
                'scanner_suspended_by' => null,
            ])->save();
        }

        return $this->scannerJson([
            'status' => 'ok',
            'message' => $suspend ? 'Scanner activity has been suspended.' : 'Scanner activity has been resumed.',
            'data' => [
                'operator' => $this->scannerOperatorPayload($mobileUser->refresh()->load('roles')),
            ],
        ]);
    }

    public function ticketQrSvg(Request $request, string $ticket, QrPayloadService $qrPayload): Response
    {
        abort_unless($this->enabled(), 404, 'Goshen Retreat is not currently available.');

        $user = $this->mobileUserFromToken($request);
        if (! $user) {
            return response('Please sign in to view this ticket QR code.', 401)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Cache-Control', 'no-store, private');
        }

        $ticketModel = $this->ticketFromKey($ticket);
        abort_if(! $ticketModel, 404);
        abort_unless($this->ticketBelongsToPublishedGoshenEvent($ticketModel), 404);

        $isOwner = (int) $ticketModel->booking?->customer_id === (int) $user->id;
        abort_unless($isOwner || $this->canScanGoshen($user), 404);

        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'imageBase64' => false,
            'scale' => 6,
        ])))->render($qrPayload->encodedPayloadFor($ticketModel));

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private');
    }

    public function ticketDocument(Request $request, string $ticket, string $type, TicketDocumentService $documents): Response|JsonResponse|StreamedResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        if (! in_array($type, ['qr', 'pdf', 'ics'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This ticket document type is not available.',
            ], 404);
        }

        $user = $this->mobileUserFromToken($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to download your Goshen Retreat ticket document.',
            ], 401);
        }

        $ticketModel = $this->ticketFromKey($ticket);
        if (
            ! $ticketModel
            || ! $this->ticketBelongsToPublishedGoshenEvent($ticketModel)
            || (int) $ticketModel->booking?->customer_id !== (int) $user->id
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'This ticket document is not linked to your account.',
            ], 404);
        }

        try {
            $document = $type === 'pdf'
                ? $this->generateTicketDocument($documents, $ticketModel, $type)
                : ($ticketModel->documents()->where('type', $type)->first()
                    ?: $this->generateTicketDocument($documents, $ticketModel, $type));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'This ticket document could not be prepared right now.',
            ], 503);
        }

        $response = Storage::disk($document->disk)->download(
            $document->path,
            $this->ticketDocumentFilename($ticketModel, $document),
        );

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function scannerLookup(Request $request, CheckInService $checkIns, QrPayloadService $qrPayload): JsonResponse
    {
        if ($response = $this->scannerAccessError($request)) {
            return $response;
        }

        $data = $this->payload($request);
        $lookupMode = strtolower(trim((string) ($data['lookup_mode'] ?? 'ticket')));
        $lookupTerm = trim((string) ($data['query'] ?? $data['identifier'] ?? $data['qr_payload'] ?? $data['scan'] ?? ''));

        if (in_array($lookupMode, ['name', 'phone'], true)) {
            if (mb_strlen($lookupTerm) < 2) {
                return $this->scannerJson([
                    'status' => 'error',
                    'message' => 'Enter at least two characters or digits to search attendee records.',
                ], 422);
            }

            $tickets = $this->scannerSearchTickets($lookupMode, $lookupTerm)
                ->limit(10)
                ->get();

            return $this->scannerJson([
                'status' => 'ok',
                'data' => [
                    'matches' => $tickets
                        ->map(fn (Ticket $ticket): array => $this->scannerTicketPayload($ticket))
                        ->values(),
                    'count' => $tickets->count(),
                ],
            ]);
        }

        $identifier = $this->ticketIdentifierFromScan($data, $qrPayload);

        if (! $identifier) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Please scan a valid Goshen Retreat ticket or enter a ticket number.',
            ], 422);
        }

        try {
            $ticket = $this->resolveScannerTicket($identifier, $checkIns)
                ->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns']);
        } catch (ModelNotFoundException) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $this->scannerTicketNotFoundMessage(),
            ], 404);
        } catch (RuntimeException $exception) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $this->scannerRuntimeMessage($exception),
            ], $this->scannerRuntimeStatus($exception));
        }

        if (! $this->ticketBelongsToPublishedGoshenEvent($ticket)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $this->scannerTicketNotFoundMessage(),
            ], 404);
        }

        return $this->scannerJson([
            'status' => 'ok',
            'data' => $this->scannerTicketPayload($ticket),
        ]);
    }

    public function scannerCheckIn(Request $request, CheckInService $checkIns, QrPayloadService $qrPayload): JsonResponse
    {
        $accessError = $this->scannerAccessError($request);
        if ($accessError) {
            return $accessError;
        }

        $user = $this->mobileUserFromToken($request);
        $data = $this->payload($request);
        $identifier = $this->ticketIdentifierFromScan($data, $qrPayload);
        $dayNumber = max(1, (int) ($data['day_number'] ?? 1));

        if (! $identifier) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Please scan a valid Goshen Retreat ticket before checking in.',
            ], 422);
        }

        try {
            $ticket = $this->resolveScannerTicket($identifier, $checkIns)
                ->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns']);
        } catch (ModelNotFoundException) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $this->scannerTicketNotFoundMessage(),
            ], 404);
        } catch (RuntimeException $exception) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $this->scannerRuntimeMessage($exception),
            ], $this->scannerRuntimeStatus($exception));
        }

        if (! $this->ticketBelongsToPublishedGoshenEvent($ticket)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $this->scannerTicketNotFoundMessage(),
            ], 404);
        }

        $status = $ticket->status?->value ?? (string) $ticket->status;
        if (in_array($status, [TicketStatus::Cancelled->value, TicketStatus::Unpaid->value], true)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'This ticket is not eligible for check-in.',
                'data' => $this->scannerTicketPayload($ticket),
            ], 422);
        }

        $alreadyCheckedIn = $ticket->checkIns()
            ->where('day_number', $dayNumber)
            ->where('status', TicketStatus::CheckedIn->value)
            ->latest('checked_in_at')
            ->first();

        if ($alreadyCheckedIn) {
            return $this->scannerJson([
                'status' => 'ok',
                'message' => 'This ticket was already checked in for this day.',
                'duplicate' => true,
                'data' => $this->scannerTicketPayload($ticket->refresh()->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns'])),
            ]);
        }

        $checkIns->checkIn(
            ticket: $ticket,
            status: TicketStatus::CheckedIn,
            actorId: $user?->id,
            dayNumber: $dayNumber,
            source: 'flutter_scanner',
            deviceId: $data['device_id'] ?? null,
            metadata: [
                'app' => 'goshen_flutter',
                'scan_mode' => $data['scan_mode'] ?? 'online',
            ],
        );

        return $this->scannerJson([
            'status' => 'ok',
            'message' => 'Ticket checked in successfully.',
            'duplicate' => false,
            'data' => $this->scannerTicketPayload($ticket->refresh()->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns'])),
        ]);
    }

    public function legacyScannerCheckIn(
        Request $request,
        CheckInService $checkIns,
        QrPayloadService $qrPayload,
        string $ticket,
        ?int $day = null,
    ): JsonResponse {
        $payload = array_replace($this->payload($request), [
            'identifier' => $ticket,
        ]);

        if ($day !== null) {
            $payload['day_number'] = $day;
        }

        $request->merge(['data' => $payload]);

        return $this->scannerCheckIn($request, $checkIns, $qrPayload);
    }

    public function scannerSync(Request $request, CheckInService $checkIns, QrPayloadService $qrPayload): JsonResponse
    {
        $accessError = $this->scannerAccessError($request);
        if ($accessError) {
            return $accessError;
        }

        $user = $this->mobileUserFromToken($request);
        $data = $this->payload($request);
        $items = $data['items'] ?? [];

        if (! is_array($items) || count($items) < 1) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'No offline check-ins were provided for sync.',
            ], 422);
        }

        if (count($items) > 250) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Please sync 250 offline check-ins or fewer at a time.',
            ], 422);
        }

        $results = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $results[] = $this->scannerSyncResult($index, null, 'rejected', 'Invalid offline check-in item.');

                continue;
            }

            $localId = is_string($item['local_id'] ?? null) ? trim($item['local_id']) : null;
            $identifier = $this->ticketIdentifierFromScan($item, $qrPayload);
            $dayNumber = max(1, (int) ($item['day_number'] ?? 1));
            $deviceId = is_string($item['device_id'] ?? null)
                ? trim((string) $item['device_id'])
                : (is_string($data['device_id'] ?? null) ? trim((string) $data['device_id']) : null);

            if (! $identifier) {
                $results[] = $this->scannerSyncResult($index, $localId, 'rejected', 'Missing ticket identifier.');

                continue;
            }

            try {
                $ticket = $checkIns->findTicket($identifier)
                    ->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns']);
            } catch (ModelNotFoundException) {
                $results[] = $this->scannerSyncResult($index, $localId, 'rejected', 'Ticket was not found.');

                continue;
            }

            if (! $this->ticketBelongsToPublishedGoshenEvent($ticket)) {
                $results[] = $this->scannerSyncResult($index, $localId, 'rejected', 'Ticket was not found.');

                continue;
            }

            $status = $ticket->status?->value ?? (string) $ticket->status;
            if (in_array($status, [TicketStatus::Cancelled->value, TicketStatus::Unpaid->value], true)) {
                $results[] = $this->scannerSyncResult(
                    $index,
                    $localId,
                    'rejected',
                    'Ticket is not eligible for check-in.',
                    $ticket,
                );

                continue;
            }

            $existingOfflineCheckIn = $localId
                ? $ticket->checkIns()
                    ->where('day_number', $dayNumber)
                    ->where('source', 'flutter_offline_sync')
                    ->where('metadata->offline_key', $localId)
                    ->first()
                : null;

            $alreadyCheckedIn = $ticket->checkIns()
                ->where('day_number', $dayNumber)
                ->where('status', TicketStatus::CheckedIn->value)
                ->latest('checked_in_at')
                ->first();

            if ($existingOfflineCheckIn || $alreadyCheckedIn) {
                $results[] = $this->scannerSyncResult(
                    $index,
                    $localId,
                    'synced',
                    $existingOfflineCheckIn
                        ? 'This offline check-in was already synced.'
                        : 'This ticket was already checked in for this day.',
                    $ticket->refresh()->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns']),
                    duplicate: true,
                );

                continue;
            }

            $checkIns->checkIn(
                ticket: $ticket,
                status: TicketStatus::CheckedIn,
                actorId: $user?->id,
                dayNumber: $dayNumber,
                source: 'flutter_offline_sync',
                deviceId: $deviceId,
                metadata: [
                    'app' => 'goshen_flutter',
                    'offline_key' => $localId,
                    'offline_checked_in_at' => is_string($item['checked_in_at'] ?? null)
                        ? $item['checked_in_at']
                        : null,
                    'synced_at' => now()->toIso8601String(),
                ],
            );

            $results[] = $this->scannerSyncResult(
                $index,
                $localId,
                'synced',
                'Offline check-in synced successfully.',
                $ticket->refresh()->load(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns']),
            );
        }

        return $this->scannerJson([
            'status' => 'ok',
            'data' => [
                'synced' => collect($results)->where('status', 'synced')->count(),
                'rejected' => collect($results)->where('status', 'rejected')->count(),
                'results' => $results,
            ],
        ]);
    }

    public function scannerStats(Request $request, string $event): JsonResponse
    {
        if ($response = $this->scannerAccessError($request)) {
            return $response;
        }

        $user = $this->mobileUserFromToken($request);
        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($event->status === 'published', 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $tickets = Ticket::query()
            ->with(['attendee', 'checkIns'])
            ->where('event_id', $event->id)
            ->get();

        $eligibleTickets = $tickets
            ->reject(fn (Ticket $ticket): bool => in_array(
                $ticket->status?->value ?? (string) $ticket->status,
                [TicketStatus::Cancelled->value, TicketStatus::Unpaid->value],
                true,
            ))
            ->values();

        $checkedIn = $eligibleTickets->filter(function (Ticket $ticket): bool {
            return $ticket->checkIns->contains(function ($checkIn): bool {
                return ($checkIn->status?->value ?? (string) $checkIn->status) === TicketStatus::CheckedIn->value;
            });
        });

        $genderRows = collect();
        $ageGroupRows = collect();
        if ($user && $this->canViewScannerDemographics($user)) {
            $genderRows = $eligibleTickets
                ->groupBy(fn (Ticket $ticket): string => $this->attendeeSnapshotCode($ticket, 'gender'))
                ->map(fn ($items, string $code): array => [
                    'code' => $code,
                    'label' => $this->genderLabel($code),
                    'registered' => $items->count(),
                    'checked_in' => $items->filter(function (Ticket $ticket): bool {
                        return $ticket->checkIns->contains(fn ($checkIn): bool => ($checkIn->status?->value ?? (string) $checkIn->status) === TicketStatus::CheckedIn->value);
                    })->count(),
                ])
                ->values();

            $ageGroupRows = $eligibleTickets
                ->groupBy(fn (Ticket $ticket): string => $this->attendeeSnapshotCode($ticket, 'age_group'))
                ->map(fn ($items, string $code): array => [
                    'code' => $code,
                    'label' => $this->ageGroupLabel($code),
                    'registered' => $items->count(),
                    'checked_in' => $items->filter(function (Ticket $ticket): bool {
                        return $ticket->checkIns->contains(fn ($checkIn): bool => ($checkIn->status?->value ?? (string) $checkIn->status) === TicketStatus::CheckedIn->value);
                    })->count(),
                ])
                ->values();
        }

        return $this->scannerJson([
            'status' => 'ok',
            'data' => [
                'event' => [
                    'public_id' => $event->public_id,
                    'name' => $event->name,
                ],
                'registered' => $eligibleTickets->count(),
                'checked_in' => $checkedIn->count(),
                'not_yet_checked_in' => max(0, $eligibleTickets->count() - $checkedIn->count()),
                'gender_breakdown' => $genderRows,
                'age_group_breakdown' => $ageGroupRows,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function scannerManifest(Request $request, string $event): JsonResponse
    {
        if ($response = $this->scannerAccessError($request)) {
            return $response;
        }

        $event = $this->eventFromKey($event);
        abort_unless($event, 404);
        abort_unless($event->status === 'published', 404);
        abort_unless($this->isGoshenEvent($event), 404);

        $tickets = Ticket::query()
            ->with(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns'])
            ->where('event_id', $event->id)
            ->latest('issued_at')
            ->get()
            ->map(fn (Ticket $ticket): array => $this->scannerTicketPayload($ticket))
            ->values();

        $generatedAt = now();
        $ttlSeconds = 24 * 60 * 60;

        return $this->scannerJson([
            'status' => 'ok',
            'data' => [
                'event' => $this->scannerEventPayload($event->load('schedules')),
                'generated_at' => $generatedAt->toIso8601String(),
                'expires_at' => $generatedAt->copy()->addSeconds($ttlSeconds)->toIso8601String(),
                'ttl_seconds' => $ttlSeconds,
                'manifest_version' => 1,
                'tickets' => $tickets,
            ],
        ]);
    }

    private function retreatSetupResponse(Event $event, string $message): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => $message,
            'data' => [
                'event' => $this->eventPayload(
                    $event->fresh(['schedules', 'ticketTypes', 'paymentPlans', 'attendeeFields']),
                    true,
                ),
            ],
        ]);
    }

    private function eventPayload(Event $event, bool $forManagement = false): array
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $featureImagePath = $this->featureImagePath($settings);
        $registrationFields = $this->registrationFieldPayload($event);
        $ticketTypes = $event->ticketTypes;
        if (! $forManagement) {
            $ticketTypes = $ticketTypes->where('is_active', true);
        }

        return [
            'id' => $event->id,
            'public_id' => $event->public_id,
            'name' => $event->name,
            'slug' => $event->slug,
            'description' => $event->description,
            'timezone' => $event->timezone,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'support_email' => $event->support_email,
            'inquiry_phone' => $this->inquiryPhone($settings),
            'feature_image_path' => $featureImagePath,
            'feature_image_url' => MediaUrl::resolve($featureImagePath),
            'past_videos' => $this->pastVideoPayload($settings),
            'start_date' => $this->dateString($event->start_date),
            'end_date' => $this->dateString($event->end_date),
            'sales_start_at' => $this->isoTimestamp($event->sales_start_at),
            'sales_end_at' => $this->isoTimestamp($event->sales_end_at),
            'registration' => $this->registrationPayload($event),
            'registration_open' => $this->registrationIsOpen($event),
            'registration_closed_reason' => $this->registrationClosedReason($event),
            'registration_form' => [
                'attendee_fields' => $registrationFields,
                'privacy_consent' => [
                    'key' => 'uk_privacy_consent',
                    'label' => 'Privacy consent',
                    'required' => true,
                    'text' => 'I agree that MFM Triumphant Church may process my registration, attendee, payment, ticket, and travel-support information for Goshen Retreat administration in line with UK data protection requirements.',
                ],
            ],
            'attendee_fields' => $registrationFields,
            'pay_in_full_discount' => $this->discountPayload($event),
            'schedules' => $this->schedulePayload($event),
            'ticket_types' => $ticketTypes
                ->map(fn ($ticketType): array => [
                    'id' => $ticketType->id,
                    'public_id' => $ticketType->public_id,
                    'name' => $ticketType->name,
                    'sku' => $ticketType->sku,
                    'currency' => $ticketType->currency,
                    'price' => (float) $ticketType->price,
                    'capacity' => $ticketType->capacity,
                    'min_per_booking' => $ticketType->min_per_booking,
                    'max_per_booking' => $ticketType->max_per_booking,
                    'is_active' => (bool) $ticketType->is_active,
                ])
                ->values(),
            'payment_plans' => [],
        ];
    }

    private function registrationFieldPayload(Event $event): array
    {
        $fields = app(GoshenRegistrationFieldService::class);
        $event->loadMissing('attendeeFields');

        if ($event->attendeeFields->isEmpty()) {
            $fields->ensureDefaultsForEvent($event);
            $event->unsetRelation('attendeeFields');
            $event->load('attendeeFields');
        }

        return $fields->payloadFor($event);
    }

    private function featureImagePath(array $settings): ?string
    {
        $featureBanner = is_array($settings['feature_banner'] ?? null) ? $settings['feature_banner'] : [];

        return $this->storedMediaPath(
            $featureBanner['image_path']
                ?? $settings['feature_image_path']
                ?? $settings['feature_banner_image_path']
                ?? $settings['banner_image_path']
                ?? null
        );
    }

    private function storedMediaPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['path'] ?? $value['image_path'] ?? reset($value);
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function inquiryPhone(array $settings): ?string
    {
        $value = $settings['inquiry_phone'] ?? null;
        $value = is_array($value) ? ($value['phone'] ?? reset($value)) : $value;
        $value = preg_replace('/^tel:/i', '', trim((string) $value));
        $hasLeadingPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if ($digits === '') {
            return null;
        }

        return ($hasLeadingPlus ? '+' : '').$digits;
    }

    private function pastVideoPayload(array $settings): array
    {
        $videos = $settings['past_videos'] ?? [];

        if (! is_array($videos)) {
            return [];
        }

        $videos = collect($videos)->values();
        $hasExplicitOrder = $videos->contains(fn (mixed $video): bool => is_array($video)
            && is_numeric($video['sort_order'] ?? null)
            && (int) $video['sort_order'] > 0);

        return $videos
            ->filter(fn (mixed $video): bool => is_array($video))
            ->map(function (array $video, int $index) use ($hasExplicitOrder): ?array {
                $youtubeVideoId = $this->youtubeVideoId(
                    $video['youtube_url']
                        ?? $video['youtube_video_id']
                        ?? $video['url']
                        ?? $video['video_url']
                        ?? null
                );

                if (! $youtubeVideoId) {
                    return null;
                }

                $title = trim((string) ($video['title'] ?? ''));
                $description = trim((string) ($video['description'] ?? ''));
                $explicitSortOrder = $video['sort_order'] ?? null;
                $sortOrder = $hasExplicitOrder && is_numeric($explicitSortOrder) && (int) $explicitSortOrder > 0
                    ? (int) $explicitSortOrder
                    : $index + 1;

                return [
                    'youtube_video_id' => $youtubeVideoId,
                    'youtube_url' => "https://www.youtube.com/watch?v={$youtubeVideoId}",
                    'thumbnail_url' => "https://img.youtube.com/vi/{$youtubeVideoId}/hqdefault.jpg",
                    'title' => $title !== '' ? $title : 'Goshen Retreat video',
                    'description' => $description !== '' ? $description : null,
                    'sort_order' => $sortOrder,
                ];
            })
            ->filter()
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function youtubeVideoId(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) {
            return $value;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $isYouTubeHost = in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)
            || str_ends_with($host, '.youtube.com')
            || str_ends_with($host, '.youtube-nocookie.com');

        if (! $isYouTubeHost) {
            return null;
        }

        $candidate = null;

        if ($host === 'youtu.be') {
            $candidate = strtok(trim((string) ($parts['path'] ?? ''), '/'), '/');
        } else {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = $query['v'] ?? null;

            if (blank($candidate)) {
                $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));
                if (in_array($segments[0] ?? '', ['embed', 'shorts', 'live', 'v'], true)) {
                    $candidate = $segments[1] ?? null;
                }
            }
        }

        $candidate = is_array($candidate) ? reset($candidate) : $candidate;
        $candidate = trim((string) $candidate);

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1 ? $candidate : null;
    }

    private function registrationPayload(Event $event): array
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $override = strtolower(trim((string) ($registration['override'] ?? 'auto')));
        if (! in_array($override, ['auto', 'open', 'closed'], true)) {
            $override = 'auto';
        }

        $open = $this->registrationIsOpen($event);

        return [
            'open' => $open,
            'override' => $override,
            'status' => $open ? 'open' : 'closed',
            'message' => $open ? 'Registration is open.' : $this->registrationClosedMessage($event),
            'closed_reason' => $this->registrationClosedReason($event),
            'closed_at' => $registration['closed_at'] ?? null,
            'reopened_at' => $registration['reopened_at'] ?? null,
            'sales_start_at' => $this->isoTimestamp($event->sales_start_at),
            'sales_end_at' => $this->isoTimestamp($event->sales_end_at),
        ];
    }

    private function schedulePayload(Event $event): array
    {
        $event->loadMissing('schedules');

        return $event->schedules
            ->sortBy(['day_number', 'starts_at'])
            ->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'day_number' => $schedule->day_number,
                'title' => data_get($schedule->metadata, 'title'),
                'starts_at' => $this->isoTimestamp($schedule->starts_at),
                'ends_at' => $this->isoTimestamp($schedule->ends_at),
                'capacity' => $schedule->capacity,
            ])
            ->values()
            ->all();
    }

    private function registrationIsOpen(Event $event): bool
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $override = strtolower(trim((string) ($registration['override'] ?? 'auto')));

        if ($override === 'closed') {
            return false;
        }

        if ($override === 'open') {
            return true;
        }

        $now = now();

        if ($event->sales_start_at && $event->sales_start_at->gt($now)) {
            return false;
        }

        if ($event->sales_end_at && $event->sales_end_at->lt($now)) {
            return false;
        }

        return true;
    }

    private function registrationClosedReason(Event $event): ?string
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $override = strtolower(trim((string) ($registration['override'] ?? 'auto')));

        if ($override === 'closed') {
            return trim((string) ($registration['close_reason'] ?? 'Registration has been closed by the event manager.')) ?: 'Registration has been closed by the event manager.';
        }

        if ($override === 'open') {
            return null;
        }

        $now = now();
        if ($event->sales_start_at && $event->sales_start_at->gt($now)) {
            return 'Registration has not opened yet.';
        }

        if ($event->sales_end_at && $event->sales_end_at->lt($now)) {
            return 'Registration has closed.';
        }

        return null;
    }

    private function registrationClosedMessage(Event $event): string
    {
        return $this->registrationClosedReason($event)
            ?: 'Registration is not open for this retreat edition.';
    }

    private function discountPayload(Event $event): array
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $discount = is_array($settings['pay_in_full_discount'] ?? null) ? $settings['pay_in_full_discount'] : [];
        $type = $discount['type'] ?? 'percentage';
        $type = in_array($type, ['percentage', 'fixed'], true) ? $type : 'percentage';

        return [
            'enabled' => filter_var($discount['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'active' => $this->discountIsActive($event, $discount),
            'label' => trim((string) ($discount['label'] ?? 'Pay in full discount')),
            'type' => $type,
            'value' => (float) ($discount['value'] ?? 0),
            'starts_at' => $this->isoTimestamp($discount['starts_at'] ?? null),
            'ends_at' => $this->isoTimestamp($discount['ends_at'] ?? null),
        ];
    }

    private function payInFullDiscount(Event $event, float $subtotal, bool $requested): array
    {
        $payload = $this->discountPayload($event);
        $amount = 0.0;

        if ($requested && $subtotal > 0 && $payload['enabled'] && $payload['active'] && $payload['value'] > 0) {
            $amount = $payload['type'] === 'fixed'
                ? (float) $payload['value']
                : $subtotal * min(100, max(0, (float) $payload['value'])) / 100;
            $amount = round(min($subtotal, max(0, $amount)), 2);
        }

        return [
            ...$payload,
            'requested' => $requested,
            'applied' => $amount > 0,
            'amount' => $amount,
        ];
    }

    private function discountIsActive(Event $event, array $discount): bool
    {
        if (! filter_var($discount['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $timezone = trim((string) ($event->timezone ?: config('app.timezone', 'UTC'))) ?: 'UTC';
        $now = Carbon::now($timezone);
        $startsAt = $discount['starts_at'] ?? null;
        $endsAt = $discount['ends_at'] ?? null;

        if ($startsAt && Carbon::parse((string) $startsAt, $timezone)->gt($now)) {
            return false;
        }

        if ($endsAt && Carbon::parse((string) $endsAt, $timezone)->lt($now)) {
            return false;
        }

        return true;
    }

    private function goshenEventsQuery()
    {
        return Event::query()->where(function ($query): void {
            $this->applyGoshenEventScope($query);
        });
    }

    private function applyGoshenEventScope($query): void
    {
        $query
            ->where('settings->module', 'goshen_retreat')
            ->orWhere('settings->module', 'goshen-retreat')
            ->orWhere('settings->app_module', 'goshen_retreat')
            ->orWhere('slug', 'like', 'goshen-retreat%')
            ->orWhere('slug', 'like', 'goshen-%')
            ->orWhere('name', 'like', '%Goshen Retreat%');
    }

    private function isGoshenEvent(Event $event): bool
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $module = strtolower(trim((string) ($settings['module'] ?? $settings['app_module'] ?? '')));

        if (in_array($module, ['goshen_retreat', 'goshen-retreat'], true)) {
            return true;
        }

        $slug = strtolower((string) $event->slug);
        if (str_starts_with($slug, 'goshen-retreat') || str_starts_with($slug, 'goshen-')) {
            return true;
        }

        return str_contains(strtolower((string) $event->name), 'goshen retreat');
    }

    private function eventFromKey(string $key): ?Event
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        return Event::query()
            ->where(function ($query) use ($key): void {
                $query->where('public_id', $key);

                if (ctype_digit($key)) {
                    $query->orWhere('id', (int) $key);
                }
            })
            ->first();
    }

    private function ticketBelongsToPublishedGoshenEvent(Ticket $ticket): bool
    {
        $ticket->loadMissing('event');

        return $ticket->event
            && $ticket->event->status === 'published'
            && $this->isGoshenEvent($ticket->event);
    }

    private function managementPaidTotal(Booking $booking): float
    {
        $computedPaidTotal = (float) $booking->installments->sum('paid_amount');

        return round(max((float) $booking->paid_total, $computedPaidTotal), 2);
    }

    private function managementBalanceAmount(Booking $booking): float
    {
        if (in_array($this->managementBookingStatus($booking), [
            BookingStatus::Cancelled->value,
            BookingStatus::Refunded->value,
        ], true)) {
            return 0;
        }

        return round(max(0, (float) $booking->total - $this->managementPaidTotal($booking)), 2);
    }

    private function managementBookingStatus(Booking $booking): string
    {
        $paidTotal = $this->managementPaidTotal($booking);
        $total = (float) $booking->total;
        $rawStatus = $booking->status?->value ?? (string) $booking->status;
        $terminalStatuses = [
            BookingStatus::Cancelled->value,
            BookingStatus::Refunded->value,
        ];

        if (! in_array($rawStatus, $terminalStatuses, true) && $total > 0 && $paidTotal + 0.01 >= $total) {
            return BookingStatus::Paid->value;
        }

        return $rawStatus ?: BookingStatus::Pending->value;
    }

    private function bookingStatusLabel(string $status): string
    {
        return match ($status) {
            BookingStatus::Paid->value => 'Paid',
            BookingStatus::DepositPaid->value => 'Deposit paid',
            BookingStatus::PartiallyPaid->value => 'Partially paid',
            BookingStatus::Cancelled->value => 'Cancelled',
            BookingStatus::Refunded->value => 'Refunded',
            default => 'Pending',
        };
    }

    private function paymentTransactionIsPaid(PaymentTransaction $transaction): bool
    {
        return in_array(strtolower((string) $transaction->status), ['paid', 'succeeded', 'completed'], true);
    }

    private function managementPaymentMode(Booking $booking, $transactions): string
    {
        $paidTransactions = collect($transactions)
            ->filter(fn (PaymentTransaction $transaction): bool => $this->paymentTransactionIsPaid($transaction));

        if ($paidTransactions->contains(fn (PaymentTransaction $transaction): bool => strtolower((string) $transaction->gateway) === 'wallet')) {
            return 'wallet';
        }

        $metadata = is_array($booking->metadata) ? $booking->metadata : [];
        if ((float) $booking->total <= 0) {
            return 'free';
        }

        if (strtolower((string) ($metadata['payment_mode'] ?? '')) === 'wallet') {
            return 'wallet';
        }

        return 'online';
    }

    private function paymentModeLabel(string $mode): string
    {
        return match ($mode) {
            'wallet' => 'Wallet',
            'free' => 'Free registration',
            default => 'Card/online',
        };
    }

    private function privacyConsentCode(Booking $booking): string
    {
        $metadata = is_array($booking->metadata) ? $booking->metadata : [];
        $rawConsent = $metadata['uk_privacy_consent'] ?? $metadata['privacy_consent'] ?? null;

        if ($rawConsent === null || $rawConsent === '') {
            return 'not_recorded';
        }

        return filter_var($rawConsent, FILTER_VALIDATE_BOOLEAN) ? 'accepted' : 'declined';
    }

    private function privacyConsentLabel(string $code): string
    {
        return match ($code) {
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            default => 'Not recorded',
        };
    }

    private function attendeeCustomCode(Attendee $attendee, string $field, string $fallback): string
    {
        $customFields = is_array($attendee->custom_fields) ? $attendee->custom_fields : [];
        $value = strtolower(trim((string) ($customFields[$field] ?? $fallback)));

        return $value !== '' ? $value : $fallback;
    }

    private function attendeeTextCode(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function optionalTextLabel(string $code): string
    {
        return $code === 'not_provided' ? 'Not provided' : $code;
    }

    private function breakdownRows($items, callable $keyResolver, callable $labelResolver, ?callable $amountResolver = null): array
    {
        $items = collect($items)->values();
        $total = max(1, $items->count());

        return $items
            ->groupBy(fn ($item): string => (string) $keyResolver($item))
            ->map(function ($group, string $key) use ($labelResolver, $amountResolver, $total): array {
                $row = [
                    'key' => $key,
                    'label' => (string) $labelResolver($key),
                    'count' => $group->count(),
                    'percentage' => round(($group->count() / $total) * 100, 1),
                ];

                if ($amountResolver !== null) {
                    $row['amount'] = round((float) $group->sum(fn ($item): float => (float) $amountResolver($item)), 2);
                }

                return $row;
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function bookingPayload(Booking $booking): array
    {
        $metadata = is_array($booking->metadata) ? $booking->metadata : [];
        $computedPaidTotal = (float) $booking->installments->sum('paid_amount');
        $paidTotal = max((float) $booking->paid_total, $computedPaidTotal);
        $total = (float) $booking->total;
        $rawStatus = $booking->status?->value ?? $booking->status;
        $terminalStatuses = [
            BookingStatus::Cancelled->value,
            BookingStatus::Refunded->value,
        ];
        $status = in_array($rawStatus, $terminalStatuses, true)
            ? $rawStatus
            : ($paidTotal + 0.01 >= $total && $total > 0
                ? BookingStatus::Paid->value
                : $rawStatus);

        return [
            'id' => $booking->id,
            'public_id' => $booking->public_id,
            'event' => [
                'id' => $booking->event?->id,
                'public_id' => $booking->event?->public_id,
                'name' => $booking->event?->name,
                'schedules' => $booking->event ? $this->schedulePayload($booking->event) : [],
            ],
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'currency' => $booking->currency,
            'subtotal' => (float) $booking->subtotal,
            'total' => $total,
            'paid_total' => $paidTotal,
            'ticket_subtotal' => (float) ($metadata['ticket_subtotal'] ?? $booking->subtotal),
            'selected_option_fee_total' => (float) ($metadata['selected_option_fee_total'] ?? 0),
            'selected_option_fees' => is_array($metadata['selected_option_fees'] ?? null) ? $metadata['selected_option_fees'] : [],
            'status' => $status,
            'payment_mode' => $metadata['payment_mode'] ?? null,
            'voucher_code_suffix' => $metadata['voucher_code_suffix'] ?? null,
            'payment_expires_at' => $this->isoTimestamp($booking->payment_expires_at),
            'payment_reminder_sent_at' => $this->isoTimestamp($booking->payment_reminder_sent_at),
            'cancelled_at' => $this->isoTimestamp($booking->cancelled_at),
            'cancellation_reason' => $booking->cancellation_reason,
            'counts_in_summary' => $status === BookingStatus::Paid->value,
            'can_cancel' => $status === BookingStatus::Pending->value && $paidTotal <= 0,
            'can_pay' => ! in_array($status, [
                BookingStatus::Paid->value,
                BookingStatus::Cancelled->value,
                BookingStatus::Refunded->value,
            ], true),
            'lines' => $booking->lines
                ->map(fn (BookingLine $line): array => [
                    'ticket_type' => $line->ticketType?->name,
                    'quantity' => $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                ])
                ->values(),
            'attendees' => $booking->attendees
                ->map(function (Attendee $attendee): array {
                    $customFields = is_array($attendee->custom_fields) ? $attendee->custom_fields : [];

                    return [
                        'public_id' => $attendee->public_id,
                        'name' => trim(($attendee->first_name ?? '').' '.($attendee->last_name ?? '')),
                        'email' => $attendee->email,
                        'phone' => $attendee->phone,
                        'company' => $attendee->company,
                        'designation' => $attendee->designation,
                        'gender' => $customFields['gender'] ?? 'not_specified',
                        'age_group' => $customFields['age_group'] ?? 'not_specified',
                        'free_church_bus_interest' => $customFields['free_church_bus_interest'] ?? 'no_thanks',
                        'volunteer_department' => $customFields['volunteer_department'] ?? 'no_chance_at_the_moment',
                    ];
                })
                ->values(),
            'installments' => $booking->installments
                ->map(fn ($installment): array => [
                    'public_id' => $installment->public_id,
                    'label' => $this->installmentLabel($installment, $booking),
                    'sequence' => $installment->sequence,
                    'currency' => $installment->currency,
                    'amount' => (float) $installment->amount,
                    'paid_amount' => (float) $installment->paid_amount,
                    'due_on' => $this->dateString($installment->due_on),
                    'paid_at' => $this->isoTimestamp($installment->paid_at),
                    'status' => $installment->status?->value ?? $installment->status,
                ])
                ->values(),
            'tickets' => $booking->tickets
                ->map(fn (Ticket $ticket): array => $this->ticketPayload($ticket))
                ->values(),
            'created_at' => $this->isoTimestamp($booking->created_at),
        ];
    }

    private function installmentLabel($installment, Booking $booking): string
    {
        $metadata = is_array($installment->metadata ?? null) ? $installment->metadata : [];
        $label = trim((string) ($metadata['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        if ((int) $installment->sequence === 1 && (float) $installment->amount + 0.01 >= (float) $booking->total) {
            return 'Full payment';
        }

        return 'Installment '.(int) $installment->sequence;
    }

    private function paymentTransactionPayload(PaymentTransaction $transaction): array
    {
        $transaction->loadMissing(['booking.event', 'installment']);

        return [
            'id' => $transaction->id,
            'public_id' => $transaction->public_id,
            'event' => [
                'public_id' => $transaction->booking?->event?->public_id,
                'name' => $transaction->booking?->event?->name,
            ],
            'booking' => [
                'public_id' => $transaction->booking?->public_id,
                'status' => $transaction->booking?->status?->value ?? $transaction->booking?->status,
            ],
            'installment' => [
                'public_id' => $transaction->installment?->public_id,
                'sequence' => $transaction->installment?->sequence,
                'status' => $transaction->installment?->status?->value ?? $transaction->installment?->status,
            ],
            'gateway' => $transaction->gateway,
            'reference' => $transaction->provider_reference,
            'currency' => $transaction->currency,
            'amount' => (float) $transaction->amount,
            'status' => $transaction->status,
            'paid_at' => $this->isoTimestamp($transaction->paid_at),
            'created_at' => $this->isoTimestamp($transaction->created_at),
            'updated_at' => $this->isoTimestamp($transaction->updated_at),
        ];
    }

    private function isoTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function bookingFromKey(string $key): ?Booking
    {
        return Booking::query()
            ->when(
                ctype_digit($key),
                fn ($query) => $query->where('id', (int) $key)->orWhere('public_id', $key),
                fn ($query) => $query->where('public_id', $key),
            )
            ->first();
    }

    private function installmentFromKey(string $key): ?PaymentInstallment
    {
        return PaymentInstallment::query()
            ->when(
                ctype_digit($key),
                fn ($query) => $query->where('id', (int) $key)->orWhere('public_id', $key),
                fn ($query) => $query->where('public_id', $key),
            )
            ->first();
    }

    private function ticketFromKey(string $key): ?Ticket
    {
        return Ticket::query()
            ->with(['event', 'booking'])
            ->when(
                ctype_digit($key),
                fn ($query) => $query->where('id', (int) $key)->orWhere('public_id', $key),
                fn ($query) => $query->where('public_id', $key),
            )
            ->first();
    }

    private function ticketPayload(Ticket $ticket): array
    {
        $ticket->loadMissing('booking.installments', 'checkIns');

        $payload = null;
        $encoded = null;
        $lastCheckIn = $ticket->checkIns
            ->where('status', TicketStatus::CheckedIn)
            ->sortByDesc('checked_in_at')
            ->first();

        try {
            $qr = app(QrPayloadService::class);
            $payload = $qr->payloadFor($ticket);
            $encoded = $qr->encodedPayloadFor($ticket);
        } catch (Throwable) {
            $payload = null;
            $encoded = null;
        }

        $paidAmount = $this->ticketPaidAmount($ticket);
        $currency = strtoupper((string) ($ticket->booking?->currency ?: $ticket->ticketType?->currency ?: 'GBP'));

        return [
            'public_id' => $ticket->public_id,
            'ticket_number' => $ticket->formatted_number ?: $ticket->ticket_number,
            'status' => $ticket->status?->value ?? $ticket->status,
            'issued_at' => $this->isoTimestamp($ticket->issued_at),
            'last_checked_in_at' => $this->isoTimestamp($lastCheckIn?->checked_in_at),
            'event_name' => $ticket->event?->name,
            'ticket_type' => $ticket->ticketType?->name,
            'attendee_name' => trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? '')),
            'currency' => $currency,
            'amount_paid' => $paidAmount,
            'paid_amount' => $paidAmount,
            'amount_paid_label' => trim($currency.' '.number_format($paidAmount, 2)),
            'qr_payload' => $payload,
            'qr_encoded' => $encoded,
            'document_urls' => $ticket->public_id ? [
                'pdf' => '/api/goshen-retreat/tickets/'.rawurlencode($ticket->public_id).'/documents/pdf',
                'ics' => '/api/goshen-retreat/tickets/'.rawurlencode($ticket->public_id).'/documents/ics',
                'qr' => '/api/goshen-retreat/tickets/'.rawurlencode($ticket->public_id).'/qr.svg',
            ] : [],
        ];
    }

    private function ticketPaidAmount(Ticket $ticket): float
    {
        $metadata = is_array($ticket->metadata) ? $ticket->metadata : [];
        $metadataAmount = $metadata['amount_paid'] ?? $metadata['historical_paid_amount'] ?? null;
        if (is_numeric($metadataAmount) && (float) $metadataAmount > 0) {
            return round((float) $metadataAmount, 2);
        }

        $booking = $ticket->booking;
        if (! $booking) {
            return 0.0;
        }

        $computedPaidTotal = $booking->relationLoaded('installments')
            ? (float) $booking->installments->sum('paid_amount')
            : (float) $booking->installments()->sum('paid_amount');
        $paidTotal = max((float) $booking->paid_total, $computedPaidTotal);

        return round($paidTotal, 2);
    }

    private function generateTicketDocument(TicketDocumentService $documents, Ticket $ticket, string $type): TicketDocument
    {
        return match ($type) {
            'qr' => $documents->generateQr($ticket),
            'pdf' => $documents->generatePdf($ticket),
            'ics' => $documents->generateIcs($ticket),
        };
    }

    private function ticketDocumentFilename(Ticket $ticket, TicketDocument $document): string
    {
        $extension = match ($document->type) {
            'qr' => 'png',
            'pdf' => 'pdf',
            'ics' => 'ics',
            default => 'bin',
        };

        $number = $ticket->formatted_number ?: $ticket->ticket_number ?: $ticket->public_id;

        return 'goshen-retreat-ticket-'.$number.'.'.$extension;
    }

    private function accommodationAllocationPayload(GoshenAccommodationAllocation $allocation): array
    {
        $allocation->loadMissing(['event', 'attendee', 'ticket']);

        return [
            'id' => $allocation->id,
            'status' => $allocation->status,
            'event' => [
                'public_id' => $allocation->event?->public_id,
                'name' => $allocation->event?->name,
            ],
            'attendee' => [
                'public_id' => $allocation->attendee?->public_id,
                'name' => trim(($allocation->attendee?->first_name ?? '').' '.($allocation->attendee?->last_name ?? '')),
            ],
            'ticket_number' => $allocation->ticket?->formatted_number ?: $allocation->ticket?->ticket_number,
            'building' => $allocation->building,
            'room' => $allocation->room,
            'bed' => $allocation->bed,
            'check_in_note' => $allocation->check_in_note,
            'attendee_visible_details' => $this->normalizedAccommodationDetails($allocation->attendee_visible_details),
            'assigned_at' => $this->isoTimestamp($allocation->assigned_at),
            'updated_at' => $this->isoTimestamp($allocation->updated_at),
        ];
    }

    private function accommodationManagementAllocationPayload(GoshenAccommodationAllocation $allocation): array
    {
        $allocation->loadMissing(['event', 'attendee.booking', 'attendee.ticketType', 'ticket.ticketType']);

        $payload = $this->accommodationAllocationPayload($allocation);
        $attendee = $allocation->attendee;
        $ticket = $allocation->ticket;
        $booking = $attendee?->booking;

        $payload['attendee_id'] = $attendee?->id;
        $payload['attendee_email'] = $attendee?->email;
        $payload['attendee_phone'] = $attendee?->phone;
        $payload['attendee_company'] = $attendee?->company;
        $payload['attendee_designation'] = $attendee?->designation;
        $payload['booking_public_id'] = $booking?->public_id;
        $payload['booking_status'] = $booking ? $this->managementBookingStatus($booking) : null;
        $payload['ticket_id'] = $ticket?->id;
        $payload['ticket_public_id'] = $ticket?->public_id;
        $payload['ticket_type'] = $ticket?->ticketType?->name ?: $attendee?->ticketType?->name;
        $payload['ticket_status'] = $ticket?->status?->value ?? $ticket?->status;

        return $payload;
    }

    private function accommodationEligibleAttendeePayload(
        Attendee $attendee,
        ?GoshenAccommodationAllocation $allocation = null,
    ): array {
        $attendee->loadMissing(['booking.installments', 'ticket', 'ticketType']);

        $booking = $attendee->booking;
        $ticket = $attendee->ticket;
        $bookingStatus = $booking ? $this->managementBookingStatus($booking) : null;

        return [
            'id' => $attendee->id,
            'public_id' => $attendee->public_id,
            'name' => trim(($attendee->first_name ?? '').' '.($attendee->last_name ?? '')),
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'company' => $attendee->company,
            'designation' => $attendee->designation,
            'ticket_type' => $attendee->ticketType?->name,
            'ticket_id' => $ticket?->id,
            'ticket_public_id' => $ticket?->public_id,
            'ticket_number' => $ticket?->formatted_number ?: $ticket?->ticket_number,
            'ticket_status' => $ticket?->status?->value ?? $ticket?->status,
            'booking_public_id' => $booking?->public_id,
            'booking_status' => $bookingStatus,
            'booking_status_label' => $bookingStatus ? $this->bookingStatusLabel($bookingStatus) : null,
            'current_allocation' => $allocation ? $this->accommodationManagementAllocationPayload($allocation) : null,
        ];
    }

    private function accommodationStatusLabel(string $code): string
    {
        return match ($code) {
            'changed' => 'Changed',
            'removed' => 'Removed',
            default => 'Assigned',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function accommodationVisibleDetails(array $data): array
    {
        $labels = [
            'building' => 'Building',
            'room' => 'Room',
            'bed' => 'Bed',
            'check_in_note' => 'Check-in note',
        ];

        $details = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                $details[$label] = $value;
            }
        }

        return $details;
    }

    /**
     * Keep the mobile contract stable: attendee_visible_details must be an object
     * of displayable key/value pairs, even when older admin records were saved
     * as a JSON list.
     */
    private function normalizedAccommodationDetails(mixed $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        $normalized = [];

        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $label = trim((string) ($value['label'] ?? $value['key'] ?? $value['name'] ?? $key));
                $detail = $value['value'] ?? $value['text'] ?? $value['detail'] ?? null;
            } else {
                $label = is_string($key) ? trim($key) : 'Detail '.((int) $key + 1);
                $detail = $value;
            }

            if ($label === '' || $detail === null || trim((string) $detail) === '') {
                continue;
            }

            $normalized[$label] = $detail;
        }

        return $normalized;
    }

    private function scannerTicketPayload(Ticket $ticket): array
    {
        $ticket->loadMissing(['event.schedules', 'booking.installments', 'attendee', 'ticketType', 'checkIns']);

        $multidayStatus = is_array($ticket->multiday_status) ? $ticket->multiday_status : [];
        $paidAmount = $this->ticketPaidAmount($ticket);
        $currency = strtoupper((string) ($ticket->booking?->currency ?: $ticket->ticketType?->currency ?: 'GBP'));

        return [
            'public_id' => $ticket->public_id,
            'ticket_number' => $ticket->formatted_number ?: $ticket->ticket_number,
            'status' => $ticket->status?->value ?? $ticket->status,
            'multiday_status' => $multidayStatus,
            'issued_at' => $this->isoTimestamp($ticket->issued_at),
            'event' => [
                'public_id' => $ticket->event?->public_id,
                'name' => $ticket->event?->name,
                'timezone' => $ticket->event?->timezone,
                'days' => $ticket->event?->schedules
                    ? $ticket->event->schedules
                        ->sortBy(['day_number', 'starts_at'])
                        ->map(fn ($schedule): array => [
                            'day_number' => $schedule->day_number,
                            'title' => data_get($schedule->metadata, 'title') ?: 'Day '.$schedule->day_number,
                            'starts_at' => $this->isoTimestamp($schedule->starts_at),
                        ])
                        ->values()
                    : [],
            ],
            'ticket_type' => $ticket->ticketType?->name,
            'attendee_name' => trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? '')),
            'booking_status' => $ticket->booking?->status?->value ?? $ticket->booking?->status,
            'currency' => $currency,
            'amount_paid' => $paidAmount,
            'paid_amount' => $paidAmount,
            'amount_paid_label' => trim($currency.' '.number_format($paidAmount, 2)),
            'checked_in_days' => $ticket->checkIns
                ->where('status', TicketStatus::CheckedIn)
                ->map(fn ($checkIn): array => [
                    'day_number' => $checkIn->day_number,
                    'checked_in_at' => $this->isoTimestamp($checkIn->checked_in_at),
                    'source' => $checkIn->source,
                ])
                ->values(),
        ];
    }

    private function scannerEventPayload(Event $event): array
    {
        return [
            'public_id' => $event->public_id,
            'name' => $event->name,
            'timezone' => $event->timezone,
            'days' => $event->schedules
                ? $event->schedules
                    ->sortBy(['day_number', 'starts_at'])
                    ->map(fn ($schedule): array => [
                        'day_number' => $schedule->day_number,
                        'title' => data_get($schedule->metadata, 'title') ?: 'Day '.$schedule->day_number,
                        'starts_at' => $this->isoTimestamp($schedule->starts_at),
                    ])
                    ->values()
                : [],
        ];
    }

    private function scannerSyncResult(
        int $index,
        ?string $localId,
        string $status,
        string $message,
        ?Ticket $ticket = null,
        bool $duplicate = false,
    ): array {
        return [
            'index' => $index,
            'local_id' => $localId,
            'status' => $status,
            'message' => $message,
            'duplicate' => $duplicate,
            'ticket' => $ticket ? $this->scannerTicketPayload($ticket) : null,
        ];
    }

    private function scannerSearchTickets(string $mode, string $term)
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term) ?: $term;
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $digitsLike = '%'.str_replace(['%', '_'], ['\%', '\_'], $digits).'%';
        $nameParts = collect(preg_split('/\s+/', $term) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        return Ticket::query()
            ->with(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns'])
            ->whereHas('event', function ($query): void {
                $query->where('status', 'published')
                    ->where(function ($eventQuery): void {
                        $this->applyGoshenEventScope($eventQuery);
                    });
            })
            ->when($mode === 'phone', function ($query) use ($digitsLike, $like) {
                $query->whereHas('attendee', function ($attendeeQuery) use ($digitsLike, $like) {
                    $attendeeQuery
                        ->where('phone', 'like', $digitsLike)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when($mode === 'name', function ($query) use ($like, $nameParts) {
                $query->whereHas('attendee', function ($attendeeQuery) use ($like, $nameParts) {
                    $attendeeQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere(function ($fullNameQuery) use ($nameParts): void {
                            foreach ($nameParts as $part) {
                                $partLike = '%'.str_replace(['%', '_'], ['\%', '\_'], $part).'%';

                                $fullNameQuery->where(function ($nameQuery) use ($partLike): void {
                                    $nameQuery
                                        ->where('first_name', 'like', $partLike)
                                        ->orWhere('last_name', 'like', $partLike);
                                });
                            }
                        });
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id');
    }

    private function scannerJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Cache-Control', 'no-store, private');
    }

    private function scannerTicketNotFoundMessage(): string
    {
        return 'We could not find a Goshen Retreat ticket with that number. Please check the last four digits, enter the full ticket number, or scan the QR code again.';
    }

    private function scannerRuntimeMessage(RuntimeException $exception): string
    {
        if ($this->isScannerModelLookupLeak($exception)) {
            return $this->scannerTicketNotFoundMessage();
        }

        return $exception->getMessage();
    }

    private function scannerRuntimeStatus(RuntimeException $exception): int
    {
        return $this->isScannerModelLookupLeak($exception) ? 404 : 422;
    }

    private function isScannerModelLookupLeak(RuntimeException $exception): bool
    {
        return str_contains($exception->getMessage(), 'No query results for model')
            || str_contains($exception->getMessage(), Ticket::class);
    }

    private function attendeeSnapshotCode(Ticket $ticket, string $field): string
    {
        $customFields = is_array($ticket->attendee?->custom_fields) ? $ticket->attendee->custom_fields : [];
        $value = strtolower(trim((string) ($customFields[$field] ?? 'not_specified')));

        return $value !== '' ? $value : 'not_specified';
    }

    private function genderLabel(string $code): string
    {
        return match ($code) {
            'male' => 'Male',
            'female' => 'Female',
            default => 'Not specified',
        };
    }

    private function normalizedGender(?string $value): string
    {
        return match (strtolower(trim((string) $value))) {
            'female', 'f' => 'female',
            default => 'male',
        };
    }

    private function ageGroupLabel(string $code): string
    {
        return match ($code) {
            'child' => 'Child',
            'teen' => 'Teen',
            'young_adult' => 'Young adult',
            'adult' => 'Adult',
            'senior' => 'Senior',
            default => 'Not specified',
        };
    }

    private function freeChurchBusInterestLabel(string $code): string
    {
        return match ($code) {
            'yes' => 'Yes',
            default => 'No thanks',
        };
    }

    private function volunteerDepartmentLabel(string $code): string
    {
        return match ($code) {
            'children_department' => 'Children department',
            'intercessory' => 'Intercessory',
            'media' => 'Media',
            'protocol' => 'Protocol',
            'sanctuary' => 'Sanctuary',
            default => 'No Chance at the moment',
        };
    }

    private function scannerAccessError(Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        if (! $this->flag('goshen_scanner_enabled', true)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Ticket scanning is not currently enabled.',
            ], 403);
        }

        $user = $this->mobileUserFromToken($request);
        if (! $user) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Please sign in before scanning Goshen Retreat tickets.',
            ], 401);
        }

        if (! $this->canScanGoshen($user)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Your account is not authorized to scan Goshen Retreat tickets.',
            ], 403);
        }

        if ($this->scannerSuspended($user)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $user->scanner_suspension_reason ?: 'Your scanner activity has been suspended by the event manager.',
            ], 403);
        }

        return null;
    }

    private function scannerManagerAccessError(Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromToken($request);
        if (! $user) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Please sign in before managing scanner activity.',
            ], 401);
        }

        if (! $this->canManageScanners($user)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage scanner activity.',
            ], 403);
        }

        if ($this->scannerSuspended($user)) {
            return $this->scannerJson([
                'status' => 'error',
                'message' => $user->scanner_suspension_reason ?: 'Your scanner activity has been suspended by the event manager.',
            ], 403);
        }

        return null;
    }

    private function registrationManagerAccessError(Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromToken($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing registration status.',
            ], 401);
        }

        if (! $this->canManageGoshenRegistration($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage Goshen registration status.',
            ], 403);
        }

        return null;
    }

    private function voucherManagerAccessError(Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen Retreat is not currently available.',
            ], 404);
        }

        $user = $this->mobileUserFromToken($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing Goshen vouchers.',
            ], 401);
        }

        if (! $this->canManageGoshenVouchers($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage Goshen vouchers.',
            ], 403);
        }

        return null;
    }

    private function canScanGoshen(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        return $user->hasAnyRole([
            'event_scanner',
            'Event Scanner',
            'event_manager',
            'Event Manager',
            'super_admin',
            'Super Admin',
        ]);
    }

    private function canViewScannerDemographics(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        return $user->hasAnyRole([
            'event_manager',
            'Event Manager',
            'super_admin',
            'Super Admin',
        ]);
    }

    private function canManageScanners(MobileUser $user): bool
    {
        return $this->canViewScannerDemographics($user);
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

    private function canManageFundraising(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

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

    private function scannerSuspended(MobileUser $user): bool
    {
        return $user->scanner_suspended_at !== null;
    }

    private function scannerRoleNames(): array
    {
        return [
            'event_scanner',
            'Event Scanner',
            'event_manager',
            'Event Manager',
            'super_admin',
            'Super Admin',
        ];
    }

    private function scannerOperatorPayload(MobileUser $user): array
    {
        $avatar = trim((string) $user->avatar);

        if ($avatar !== '' && ! str_starts_with($avatar, 'http://') && ! str_starts_with($avatar, 'https://')) {
            $avatar = Storage::disk('public')->url($avatar);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $avatar,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'last_seen_at' => $this->isoTimestamp($user->last_seen_at),
            'is_verified' => (bool) $user->is_verified,
            'is_blocked' => (bool) $user->is_blocked,
            'scanner_suspended' => $this->scannerSuspended($user),
            'scanner_suspension_reason' => $user->scanner_suspension_reason,
            'scanner_suspended_at' => $this->isoTimestamp($user->scanner_suspended_at),
        ];
    }

    private function resolveScannerTicket(string $identifier, CheckInService $checkIns): Ticket
    {
        try {
            return $checkIns->findTicket($identifier);
        } catch (ModelNotFoundException $exception) {
            $digits = preg_replace('/\D+/', '', $identifier) ?? '';

            if (strlen($digits) !== 4) {
                throw $exception;
            }

            $tickets = Ticket::query()
                ->with(['event.schedules', 'booking', 'attendee', 'ticketType', 'checkIns'])
                ->whereHas('event', function ($query): void {
                    $query->where(function ($nested): void {
                        $this->applyGoshenEventScope($nested);
                    })->where('status', 'published');
                })
                ->where(function ($query) use ($digits): void {
                    $query
                        ->where('ticket_number', 'like', '%'.$digits)
                        ->orWhere('formatted_number', 'like', '%'.$digits);
                })
                ->limit(2)
                ->get();

            if ($tickets->count() === 1) {
                return $tickets->first();
            }

            if ($tickets->count() > 1) {
                throw new RuntimeException('More than one ticket matches those last four digits. Please scan the QR code or enter the full ticket number.');
            }

            throw $exception;
        }
    }

    private function ticketIdentifierFromScan(array $data, QrPayloadService $qrPayload): ?string
    {
        $raw = trim((string) ($data['identifier'] ?? $data['qr_payload'] ?? $data['scan'] ?? ''));
        if ($raw === '') {
            return null;
        }

        $decoded = $this->decodeQrPayload($raw);
        if (is_array($decoded)) {
            try {
                if ($qrPayload->verify($decoded)) {
                    return isset($decoded['ticket']) ? (string) $decoded['ticket'] : null;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return $raw;
    }

    private function decodeQrPayload(string $raw): ?array
    {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }

        $decoded = base64_decode($raw, true);
        if (! is_string($decoded)) {
            return null;
        }

        $json = json_decode($decoded, true);

        return is_array($json) ? $json : null;
    }

    private function activeCheckoutPayloadFor(PaymentInstallment $installment): ?array
    {
        $transactions = $installment->transactions()
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        foreach ($transactions as $transaction) {
            $expiresAt = data_get($transaction->payload, 'expires_at');
            $checkoutUrl = data_get($transaction->payload, 'url');

            if ($expiresAt && now()->timestamp >= (int) $expiresAt) {
                $transaction->forceFill([
                    'status' => 'expired',
                    'payload' => array_filter(array_merge(
                        $transaction->payload ?: [],
                        ['expired_locally_at' => now()->toIso8601String()],
                    )),
                ])->save();

                continue;
            }

            if (is_string($checkoutUrl) && $checkoutUrl !== '') {
                return [
                    'gateway' => (string) $transaction->gateway,
                    'reference' => (string) $transaction->provider_reference,
                    'checkout_url' => $checkoutUrl,
                    'payload' => $transaction->payload ?: [],
                ];
            }
        }

        return null;
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $authenticated = $request->user('mobile');
        if ($authenticated instanceof MobileUser) {
            $authenticated->markApiSeen();

            return $authenticated;
        }

        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        $user?->markApiSeen();

        return $user;
    }

    private function mobileUserFromToken(Request $request): ?MobileUser
    {
        $authenticated = $request->user('mobile');
        if ($authenticated instanceof MobileUser) {
            $authenticated->markApiSeen();

            return $authenticated;
        }

        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->query('api_token') ?? $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        $user?->markApiSeen();

        return $user;
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

    private function profileMissingFields(MobileUser $user): array
    {
        $required = [
            'title' => 'title',
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
            'gender' => 'gender',
            'marital_status' => 'marital status',
            'member_type' => 'church member or visitor status',
            'country_of_residence' => 'country of residence',
            'state_county_province' => 'state/county/province',
            'address' => 'address',
        ];

        return collect($required)
            ->filter(fn (string $label, string $field): bool => blank($user->{$field}))
            ->values()
            ->all();
    }

    private function managedMemberPayload(MobileUser $member): array
    {
        $missing = $this->profileMissingFields($member);

        return [
            'id' => $member->id,
            'triumphant_id' => $member->triumphant_id,
            'name' => $member->name,
            'title' => $member->title,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'gender' => $member->gender,
            'marital_status' => $member->marital_status,
            'member_type' => $member->member_type,
            'country_of_residence' => $member->country_of_residence,
            'state_county_province' => $member->state_county_province,
            'address' => $member->address,
            'profile_missing_fields' => $missing,
            'profile_needs_update' => $missing !== [],
        ];
    }

    private function validationError(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    private function nullableCarbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function nullableIsoTimestamp(mixed $value): ?string
    {
        return $this->nullableCarbon($value)?->toIso8601String();
    }

    private function scheduleFromEvent(Event $event, mixed $key): ?EventSchedule
    {
        if (blank($key)) {
            return null;
        }

        return $event->schedules()
            ->whereKey((int) $key)
            ->first();
    }

    private function ticketTypeFromEvent(Event $event, mixed $key): ?EventTicketType
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        return $event->ticketTypes()
            ->where(function ($query) use ($key): void {
                $query->where('public_id', $key);

                if (ctype_digit($key)) {
                    $query->orWhere('id', (int) $key);
                }
            })
            ->first();
    }

    private function registrationFieldFromEvent(Event $event, mixed $key): ?EventAttendeeField
    {
        if (blank($key)) {
            return null;
        }

        return $event->attendeeFields()
            ->whereKey((int) $key)
            ->first();
    }

    private function normalizedSetupFieldType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'single_select' => 'select',
            'image_option', 'image' => 'image_select',
            'color', 'colour', 'colour_select' => 'color_select',
            'textarea' => 'textarea',
            default => in_array(strtolower(trim($type)), ['text', 'select', 'image_select', 'color_select'], true)
                ? strtolower(trim($type))
                : 'text',
        };
    }

    private function normalizedSetupFieldOptions(array $options): array
    {
        return collect($options)
            ->filter(fn (mixed $option): bool => is_array($option))
            ->map(function (array $option, int $index): ?array {
                $label = trim((string) ($option['label'] ?? ''));
                $value = array_key_exists('value', $option)
                    ? trim((string) $option['value'])
                    : '';

                if ($label === '' && $value === '') {
                    return null;
                }

                if ($value === '' && strcasecmp($label, 'Please Select') !== 0) {
                    $value = Str::slug($label, '_');
                }

                $colorHex = trim((string) ($option['color_hex'] ?? $option['colour_hex'] ?? $option['color'] ?? ''));
                if ($colorHex !== '' && ! str_starts_with($colorHex, '#')) {
                    $colorHex = '#'.$colorHex;
                }

                $payload = [
                    'label' => $label !== '' ? $label : Str::of($value)->replace('_', ' ')->headline()->toString(),
                    'value' => $value,
                    'image_path' => trim((string) ($option['image_path'] ?? $option['image'] ?? '')),
                    'color_hex' => $colorHex,
                    'fee_amount' => round(max(0, (float) ($option['fee_amount'] ?? $option['price'] ?? $option['amount'] ?? 0)), 2),
                    'fee_label' => trim((string) ($option['fee_label'] ?? '')),
                    'sort_order' => (int) ($option['sort_order'] ?? $index + 1),
                ];

                return $payload;
            })
            ->filter()
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());

        return is_array($payload) ? $payload : [];
    }

    private function enabled(): bool
    {
        return $this->flag('goshen_retreat_enabled', true);
    }

    private function flag(string $key, bool $default): bool
    {
        return filter_var(AppSetting::value($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }
}
