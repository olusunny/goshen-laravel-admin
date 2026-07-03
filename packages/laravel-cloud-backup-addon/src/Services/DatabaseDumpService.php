<?php

namespace ChurchTools\CloudBackup\Services;

use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

class DatabaseDumpService
{
    public function dump(string $connectionName, string $destination): void
    {
        $connection = config("database.connections.{$connectionName}");

        if (!is_array($connection)) {
            throw new \InvalidArgumentException("Database connection [{$connectionName}] is not configured.");
        }

        if (($connection['driver'] ?? null) !== 'mysql') {
            throw new \RuntimeException('Only MySQL database dumps are currently supported by this addon.');
        }

        $database = Arr::get($connection, 'database');
        $host = Arr::get($connection, 'host', '127.0.0.1');
        $port = (string) Arr::get($connection, 'port', '3306');
        $username = Arr::get($connection, 'username');
        $password = (string) Arr::get($connection, 'password', '');

        if (!$database || !$username) {
            throw new \RuntimeException('Database name and username are required for backup.');
        }

        $command = array_merge([
            config('cloud-backup.database.mysqldump_path', 'mysqldump'),
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
            "--result-file={$destination}",
        ], config('cloud-backup.database.extra_options', []), [$database]);

        $process = new Process($command, null, ['MYSQL_PWD' => $password]);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Database dump failed: '.$this->safeProcessError($process->getErrorOutput()));
        }
    }

    private function safeProcessError(string $error): string
    {
        $error = preg_replace('/password=\S+/i', 'password=[redacted]', $error) ?: $error;

        return trim($error) ?: 'mysqldump exited with a non-zero status.';
    }
}
