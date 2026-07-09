<?php

namespace App\Http\Controllers\Api;

use App\Models\MobileUser;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ControlHubMobileUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->accessError($request, 'manage_mobile_users')) {
            return $response;
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'query' => ['nullable', 'string', 'max:120'],
            'include_deleted' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();
        $queryText = trim((string) ($validated['query'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 25);

        $users = MobileUser::query()
            ->with('churchGroup')
            ->with('roles')
            ->when(! (bool) ($validated['include_deleted'] ?? false), fn ($query) => $query->where('is_deleted', false))
            ->when($queryText !== '', function ($query) use ($queryText): void {
                $query->where(function ($search) use ($queryText): void {
                    $search
                        ->where('name', 'like', "%{$queryText}%")
                        ->orWhere('first_name', 'like', "%{$queryText}%")
                        ->orWhere('last_name', 'like', "%{$queryText}%")
                        ->orWhere('email', 'like', "%{$queryText}%")
                        ->orWhere('phone', 'like', "%{$queryText}%")
                        ->orWhere('triumphant_id', 'like', "%{$queryText}%");
                });
            })
            ->latest('last_seen_at')
            ->latest()
            ->paginate($perPage, ['*'], 'page', (int) ($validated['page'] ?? 1));

        return response()->json([
            'status' => 'ok',
            'data' => [
                'users' => collect($users->items())->map(fn (MobileUser $user): array => $this->userPayload($user))->values(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ],
                'options' => $this->profileOptions(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isReadOnlyListPayload($request)) {
            return $this->index($request);
        }

        if ($response = $this->accessError($request, 'create_mobile_users')) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();
        $user = MobileUser::query()->create(array_merge($this->profileAttributes($validated), [
            'email' => strtolower($validated['email']),
            'password' => filled($validated['password'] ?? null) ? Hash::make($validated['password']) : null,
            'login_type' => $validated['login_type'] ?? 'control_hub',
            'is_verified' => (bool) ($validated['is_verified'] ?? true),
            'email_verified_at' => (bool) ($validated['is_verified'] ?? true) ? now() : null,
            'is_blocked' => (bool) ($validated['is_blocked'] ?? false),
            'is_deleted' => false,
        ]));

        return response()->json([
            'status' => 'ok',
            'message' => 'Mobile user created.',
            'data' => ['user' => $this->userPayload($user->fresh(['churchGroup', 'roles']))],
        ], 201);
    }

    public function update(Request $request, MobileUser $mobileUser): JsonResponse
    {
        if ($response = $this->accessError($request, 'update_mobile_users')) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules($mobileUser));

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();
        $attributes = $this->profileAttributes($validated);
        $attributes['email'] = strtolower($validated['email']);
        $attributes['login_type'] = $validated['login_type'] ?? $mobileUser->login_type;
        $attributes['is_verified'] = (bool) ($validated['is_verified'] ?? $mobileUser->is_verified);
        $attributes['is_blocked'] = (bool) ($validated['is_blocked'] ?? $mobileUser->is_blocked);
        $attributes['is_deleted'] = (bool) ($validated['is_deleted'] ?? $mobileUser->is_deleted);

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        if ($attributes['is_verified'] && ! $mobileUser->email_verified_at) {
            $attributes['email_verified_at'] = now();
        }

        $mobileUser->forceFill($attributes)->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Mobile user updated.',
            'data' => ['user' => $this->userPayload($mobileUser->fresh(['churchGroup', 'roles']))],
        ]);
    }

    public function destroy(Request $request, MobileUser $mobileUser): JsonResponse
    {
        if ($response = $this->accessError($request, 'delete_mobile_users')) {
            return $response;
        }

        $mobileUser->tokens()->delete();
        $mobileUser->forceFill([
            'api_token_hash' => null,
            'is_deleted' => true,
            'is_blocked' => true,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Mobile user deleted.',
            'data' => ['user' => $this->userPayload($mobileUser->fresh(['churchGroup', 'roles']))],
        ]);
    }

    private function rules(?MobileUser $user = null): array
    {
        return [
            'title' => ['required_without_all:profile_title,salutation', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'profile_title' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'salutation' => ['nullable', 'string', Rule::in(array_keys(MobileUser::TITLE_OPTIONS))],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name' => ['required_without:first_name', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('mobile_users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', 'string', 'max:30'],
            'marital_status' => ['required', 'string', Rule::in(array_keys(MobileUser::MARITAL_STATUS_OPTIONS))],
            'group_id' => ['nullable', 'integer', 'exists:church_groups,id'],
            'member_type' => ['required', 'string', 'in:church_member,visitor'],
            'country_of_residence' => ['nullable', 'string', 'max:120'],
            'state_county_province' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'login_type' => ['nullable', 'string', 'max:80'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'is_verified' => ['nullable', 'boolean'],
            'is_blocked' => ['nullable', 'boolean'],
            'is_deleted' => ['nullable', 'boolean'],
        ];
    }

    private function profileAttributes(array $validated): array
    {
        $name = trim((string) ($validated['name'] ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                $validated['first_name'] ?? '',
                $validated['middle_name'] ?? '',
                $validated['last_name'] ?? '',
            ])));
        }

        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $middleName = trim((string) ($validated['middle_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        if ($firstName === '' && $name !== '') {
            $parts = preg_split('/\s+/', $name) ?: [];
            $firstName = (string) ($parts[0] ?? '');
            if ($lastName === '' && count($parts) > 1) {
                $lastName = implode(' ', array_slice($parts, 1));
            }
        }

        return [
            'name' => $name,
            'title' => $validated['title'] ?? $validated['profile_title'] ?? $validated['salutation'],
            'first_name' => $firstName !== '' ? $firstName : null,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'],
            'marital_status' => $validated['marital_status'],
            'group_id' => $validated['group_id'] ?? null,
            'member_type' => $validated['member_type'],
            'country_of_residence' => $validated['country_of_residence'] ?? null,
            'state_county_province' => $validated['state_county_province'] ?? null,
            'address' => $validated['address'] ?? null,
            'address_latitude' => $validated['address_latitude'] ?? null,
            'address_longitude' => $validated['address_longitude'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ];
    }

    private function userPayload(MobileUser $user): array
    {
        $user->loadMissing(['churchGroup', 'roles']);

        return [
            'id' => $user->id,
            'triumphant_id' => $user->triumphant_id,
            'name' => $user->name,
            'title' => $user->title,
            'profile_title' => $user->title,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => MediaUrl::resolve($user->avatar) ?: '',
            'gender' => $user->gender,
            'marital_status' => $user->marital_status,
            'group_id' => $user->group_id,
            'group_name' => $user->churchGroup?->name,
            'member_type' => $user->member_type,
            'country_of_residence' => $user->country_of_residence,
            'state_county_province' => $user->state_county_province,
            'address' => $user->address,
            'address_latitude' => $user->address_latitude,
            'address_longitude' => $user->address_longitude,
            'bio' => $user->bio,
            'roles' => $user->roles->pluck('name')->values(),
            'is_verified' => (bool) $user->is_verified,
            'is_blocked' => (bool) $user->is_blocked,
            'is_deleted' => (bool) $user->is_deleted,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function accessError(Request $request, string $permission): ?JsonResponse
    {
        $user = $this->mobileUserFromToken($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing mobile users.',
            ], 401);
        }

        if (! $this->canManageMobileUsers($user, $permission)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage mobile users.',
            ], 403);
        }

        return null;
    }

    private function isReadOnlyListPayload(Request $request): bool
    {
        $payload = $this->payload($request);

        if ($payload === []) {
            return false;
        }

        $readOnlyKeys = [
            'api_token',
            'email',
            'query',
            'include_deleted',
            'page',
            'per_page',
        ];

        return collect(array_keys($payload))
            ->every(fn (string $key): bool => in_array($key, $readOnlyKeys, true));
    }

    private function canManageMobileUsers(MobileUser $user, string $permission): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_mobile_users') || $user->can($permission)) {
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

    private function mobileUserFromToken(Request $request): ?MobileUser
    {
        $token = $this->payload($request)['api_token'] ?? $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        $user?->markApiSeen();

        return $user;
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    private function profileOptions(): array
    {
        return [
            'titles' => MobileUser::TITLE_OPTIONS,
            'marital_statuses' => MobileUser::MARITAL_STATUS_OPTIONS,
        ];
    }

    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());

        return is_array($payload) ? $payload : [];
    }
}
