<?php

namespace App\Console\Commands;

use App\Models\MobileUser;
use App\Services\GoshenWalletService;
use App\Services\TriumphantIdService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportWpMobileUsers extends Command
{
    protected $signature = 'goshen:import-wp-users
        {path : CSV export path}
        {--apply : Write changes to the database. Omit for dry-run mode.}';

    protected $description = 'Dry-run or import WordPress users into linked Goshen mobile member accounts.';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $warnings = [];

    public function handle(TriumphantIdService $triumphantIds, GoshenWalletService $wallets): int
    {
        $path = (string) $this->argument('path');
        $apply = (bool) $this->option('apply');

        try {
            $rows = $this->readRows($path);
            $members = $this->membersByEmail($rows);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'csv_rows' => count($rows),
            'unique_emails' => count($members),
            'csv_duplicate_rows_merged' => count($rows) - count($members),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'wallets_ready' => 0,
            'triumphant_ids_ready' => 0,
        ];

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->error('Import could not connect to the member database: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($members as $member) {
            $existing = $this->findMobileUser($member['email']);
            $action = $existing ? 'update' : 'create';

            if (! $apply) {
                $summary[$existing ? 'updated' : 'created']++;

                continue;
            }

            try {
                DB::transaction(function () use ($member, $existing, $action, $triumphantIds, $wallets, &$summary): void {
                    if ($action === 'create') {
                        $user = MobileUser::query()->create($this->createAttributes($member));
                        $summary['created']++;
                    } else {
                        $user = MobileUser::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                        $changes = $this->updateAttributes($member, $user);

                        if ($changes === []) {
                            $summary['unchanged']++;
                        } else {
                            $user->forceFill($changes)->save();
                            $summary['updated']++;
                        }
                    }

                    $user = $triumphantIds->assignFor($user);
                    $wallets->walletFor($user);

                    if (filled($user->triumphant_id)) {
                        $summary['triumphant_ids_ready']++;
                    }

                    if ($user->wallet()->exists()) {
                        $summary['wallets_ready']++;
                    }
                });
            } catch (\Throwable $exception) {
                $summary['skipped']++;
                $this->warnings[] = [
                    'email' => $member['email'],
                    'line' => $member['line'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $this->line($apply ? 'Import complete.' : 'Dry run complete. No database changes were made.');
        $this->table(['Metric', 'Count'], collect($summary)->map(fn ($count, $metric): array => [$metric, $count])->all());

        if ($this->warnings !== []) {
            $this->warn('Warnings / skipped rows:');
            $this->table(['Line', 'Email', 'Message'], collect($this->warnings)
                ->map(fn (array $warning): array => [
                    $warning['line'] ?? '-',
                    $warning['email'] ?? '-',
                    $warning['message'] ?? '-',
                ])
                ->all());
        }

        if (! $apply) {
            $this->comment('Run again with --apply to import these users.');
        }

        return $summary['skipped'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function readRows(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("CSV file could not be read: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("CSV file could not be opened: {$path}");
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new RuntimeException('CSV file is empty.');
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $required = ['Username', 'Email', 'Phone Number', 'Gender', 'Age-group', 'First Name', 'Last Name'];
        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            fclose($handle);
            throw new RuntimeException('CSV file is missing required column(s): '.implode(', ', $missing));
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($values === [null] || $values === false) {
                continue;
            }

            $row = array_combine($headers, array_pad($values, count($headers), ''));
            if (! is_array($row)) {
                $this->warnings[] = [
                    'line' => $line,
                    'email' => '-',
                    'message' => 'Row could not be matched to the CSV header.',
                ];

                continue;
            }

            $row['_line'] = $line;
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array<string, string|int>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function membersByEmail(array $rows): array
    {
        $members = [];

        foreach ($rows as $row) {
            $email = $this->normalizeEmail((string) ($row['Email'] ?? ''));
            $line = (int) ($row['_line'] ?? 0);

            if ($email === null) {
                $this->warnings[] = [
                    'line' => $line,
                    'email' => (string) ($row['Email'] ?? ''),
                    'message' => 'Invalid email; row skipped.',
                ];

                continue;
            }

            $member = [
                'line' => $line,
                'username' => $this->clean((string) ($row['Username'] ?? '')),
                'email' => $email,
                'phone' => $this->clean((string) ($row['Phone Number'] ?? '')),
                'phone_normalized' => $this->normalizePhone((string) ($row['Phone Number'] ?? '')),
                'gender' => $this->normalizeGender((string) ($row['Gender'] ?? '')),
                'first_name' => $this->clean((string) ($row['First Name'] ?? '')),
                'last_name' => $this->clean((string) ($row['Last Name'] ?? '')),
            ];
            $member['name'] = $this->nameFor($member);

            if (! isset($members[$email])) {
                $members[$email] = $member;

                continue;
            }

            foreach (['username', 'phone', 'phone_normalized', 'gender', 'first_name', 'last_name', 'name'] as $field) {
                if (blank($members[$email][$field] ?? null) && filled($member[$field] ?? null)) {
                    $members[$email][$field] = $member[$field];
                }
            }
        }

        return $members;
    }

    private function findMobileUser(string $email): ?MobileUser
    {
        return MobileUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('is_deleted')
            ->oldest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    private function createAttributes(array $member): array
    {
        return [
            'name' => $member['name'],
            'first_name' => $member['first_name'] ?: null,
            'last_name' => $member['last_name'] ?: null,
            'email' => $member['email'],
            'phone' => $member['phone'] ?: null,
            'phone_normalized' => $member['phone_normalized'] ?: null,
            'gender' => $member['gender'] ?: null,
            'member_type' => 'church_member',
            'password' => null,
            'login_type' => 'wp_import_forgot_password',
            'is_verified' => true,
            'email_verified_at' => now(),
            'is_blocked' => false,
            'is_deleted' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    private function updateAttributes(array $member, MobileUser $user): array
    {
        $attributes = [
            'name' => $member['name'],
            'first_name' => $member['first_name'] ?: null,
            'last_name' => $member['last_name'] ?: null,
            'email' => $member['email'],
            'member_type' => 'church_member',
            'is_deleted' => false,
        ];

        if (filled($member['phone']) && blank($user->phone)) {
            $attributes['phone'] = $member['phone'];
        }

        if (filled($member['phone_normalized']) && blank($user->phone_normalized)) {
            $attributes['phone_normalized'] = $member['phone_normalized'];
        }

        if (filled($member['gender']) && blank($user->gender)) {
            $attributes['gender'] = $member['gender'];
        }

        if (blank($user->login_type)) {
            $attributes['login_type'] = 'wp_import_forgot_password';
        }

        if (! $user->is_verified || ! $user->email_verified_at) {
            $attributes['is_verified'] = true;
            $attributes['email_verified_at'] = now();
        }

        return collect($attributes)
            ->reject(fn ($value, string $key): bool => $this->sameValue($user->{$key} ?? null, $value))
            ->all();
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = strtolower($this->clean($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return strlen($digits) >= 7 ? $digits : null;
    }

    private function normalizeGender(string $gender): ?string
    {
        $gender = strtolower($this->clean($gender));

        return match ($gender) {
            'male' => 'Male',
            'female' => 'Female',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function nameFor(array $member): string
    {
        $name = trim(implode(' ', array_filter([
            $member['first_name'] ?? null,
            $member['last_name'] ?? null,
        ], fn ($value): bool => filled($value))));

        return $name !== '' ? $name : ($member['username'] ?: $member['email']);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }

    private function sameValue(mixed $current, mixed $incoming): bool
    {
        if ($current instanceof CarbonInterface) {
            return $incoming instanceof CarbonInterface
                ? $current->equalTo($incoming)
                : false;
        }

        return (string) ($current ?? '') === (string) ($incoming ?? '');
    }
}
