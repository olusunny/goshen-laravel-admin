<?php

namespace App\Http\Controllers\Api;

use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\MobileUser;
use App\Services\DynamicFormService;
use App\Services\StripePaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class DynamicFormController extends Controller
{
    public function __construct(private readonly DynamicFormService $forms) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        $forms = DynamicForm::query()
            ->with('fields')
            ->open()
            ->where(function ($query) use ($user): void {
                $query->where('visibility', DynamicForm::VISIBILITY_PUBLIC);

                if ($user) {
                    $query->orWhere('visibility', DynamicForm::VISIBILITY_AUTHENTICATED);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (DynamicForm $form): array => $this->forms->formPayload($form, $user, includeResponses: true))
            ->values();

        return response()->json([
            'status' => 'ok',
            'message' => 'Forms loaded.',
            'data' => $forms,
        ]);
    }

    public function show(Request $request, string $form): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        $form = $this->formFromKey($form);

        if (! $form || ! $form->isOpen()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be found.',
            ], 404);
        }

        if ($form->requiresLogin() && ! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before opening this form.',
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $this->forms->formPayload($form, $user, includeResponses: true),
        ]);
    }

    public function submit(Request $request, string $form): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        $form = $this->formFromKey($form);

        if (! $form) {
            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be found.',
            ], 404);
        }

        try {
            $result = $this->forms->submit($form, $request, $user, 'flutter_or_web_app');
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?: 'Please check the form and try again.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], in_array($exception->getMessage(), [
                'Please sign in before submitting this form.',
                'Please sign in before paying this form from your wallet.',
            ], true) ? 401 : 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be submitted right now. Please try again shortly.',
            ], 500);
        }

        /** @var DynamicFormSubmission $submission */
        $submission = $result['submission'];
        $checkout = $result['checkout'] ?? null;
        $message = $submission->payment_status === DynamicFormSubmission::PAYMENT_PENDING
            ? 'Secure checkout is ready.'
            : ($form->thank_you_message ?: 'Thank you. Your form has been submitted.');

        return response()->json([
            'status' => 'ok',
            'message' => $message,
            'mode' => $result['mode'],
            'submission' => $this->forms->submissionPayload($submission),
            'checkout' => $checkout,
        ], 201);
    }

    public function webhook(Request $request, StripePaymentSettings $settings): JsonResponse
    {
        $settings->applyToConfig();
        $secret = $settings->givingWebhookSecret();

        if ($secret === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Dynamic form Stripe webhook is not configured.',
            ], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
                300,
            );
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            report($exception);

            return response()->json(['status' => 'error', 'message' => 'Invalid Stripe webhook.'], 400);
        }

        $this->forms->settleStripeWebhook($event->toArray());

        return response()->json(['status' => 'ok']);
    }

    private function formFromKey(string $key): ?DynamicForm
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        return DynamicForm::query()
            ->with('fields')
            ->when(
                ctype_digit($key),
                fn ($query) => $query->whereKey((int) $key)->orWhere('slug', $key),
                fn ($query) => $query->where('slug', $key),
            )
            ->first();
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $request->bearerToken() ?: ($data['api_token'] ?? $request->input('api_token'));

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()->where('api_token_hash', hash('sha256', $token))->first();
        $user?->markApiSeen();

        return $user;
    }

    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }
}
