<?php

namespace App\Services;

use App\Models\BackupConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;

class DatabaseBackupService
{
    public function runForUser(int $userId): ?string
    {
        $cfg = BackupConfig::where('user_id', $userId)->first();
        if (!$cfg || !$cfg->enabled || !$cfg->s3_bucket || !$cfg->s3_access_key || !$cfg->s3_secret) {
            return null;
        }

        $filename = sprintf('database-%s.sql', now()->format('Ymd_His'));
        $tmp = storage_path('app/tmp/' . $filename);
        @mkdir(dirname($tmp), 0775, true);

        $this->dumpToFile($tmp);

        $key = trim($cfg->s3_path_prefix ?: 'backups', '/') . '/' . $filename;
        $this->uploadToS3($cfg, $tmp, $key);

        @unlink($tmp);
        return $key;
    }

    private function dumpToFile(string $path): void
    {
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $cmd = sprintf('mysqldump -h %s -P %s -u %s -p%s %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($user), escapeshellarg($pass), escapeshellarg($db));
        $proc = proc_open($cmd, [1 => ['file', $path, 'w']], $pipes);
        if (!is_resource($proc)) throw new \RuntimeException('Failed to start mysqldump');
        $code = proc_close($proc);
        if ($code !== 0) throw new \RuntimeException('mysqldump failed with code ' . $code);
    }

    private function uploadToS3(BackupConfig $cfg, string $file, string $key): void
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => $cfg->s3_region ?: 'us-east-1',
            'credentials' => [
                'key' => $cfg->s3_access_key,
                'secret' => $cfg->s3_secret,
            ],
            'endpoint' => $cfg->s3_endpoint ?: null,
            'use_path_style_endpoint' => (bool) ($cfg->s3_endpoint),
        ]);

        $client->putObject([
            'Bucket' => $cfg->s3_bucket,
            'Key' => $key,
            'SourceFile' => $file,
            'ContentType' => 'application/sql',
        ]);
    }
}

