<?php

namespace App\Services;

use App\Models\BackupConfig;
use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;

class DatabaseBackupService
{
    public function runForUser(int $userId): ?array
    {
        // Force refresh from DB to ensure we get latest saveConfig changes
        $cfg = BackupConfig::where('user_id', $userId)->first();

        if (!$cfg) {
            \Illuminate\Support\Facades\Log::warning("Backup failed: No config found for user $userId");
            return null;
        }

        // We allow running manual backups even if 'enabled' is false (automation off),
        // as long as the provider credentials are set.
        // But the previous check was strict on 'enabled'.
        // Let's relax it for manual runs or ensure the UI sets enabled=true.
        // For now, we will assume if the user clicked "Run Manual Backup", they want it to run.
        // However, the original logic required enabled=true.
        // Let's check if we have enough credentials.

        $hasS3 = ($cfg->s3_bucket && $cfg->s3_access_key && $cfg->s3_secret);

        if (!$hasS3 && $cfg->provider !== 'local') {
            // If provider is R2/S3 but no creds, we can't run.
            \Illuminate\Support\Facades\Log::warning("Backup failed: Missing S3/R2 credentials for user $userId");
            return null;
        }

        $filename = sprintf('database-%s.sql', now()->format('Ymd_His'));
        $tmp = storage_path('app/tmp/' . $filename);
        @mkdir(dirname($tmp), 0775, true);

        $this->dumpToFile($tmp);
        $size = filesize($tmp);

        $result = [];
        $hasS3 = ($cfg->s3_bucket && $cfg->s3_access_key && $cfg->s3_secret);

        $backupRecord = [
            'user_id' => $userId,
            'size_bytes' => $size,
            'is_successful' => true,
        ];

        if ($hasS3) {
            $key = trim($cfg->s3_path_prefix ?: 'backups', '/') . '/' . $filename;
            $this->uploadToS3($cfg, $tmp, $key);
            $result['uploaded_key'] = $key;

            $backupRecord['disk'] = 's3';
            $backupRecord['path'] = $key;

            @unlink($tmp);
        } else {
            $destRel = 'backups/' . $filename;
            @mkdir(storage_path('app/backups'), 0775, true);
            // move to local storage
            $destAbs = storage_path('app/' . $destRel);
            @rename($tmp, $destAbs);
            $result['local_path'] = $destRel;

            $backupRecord['disk'] = 'local';
            $backupRecord['path'] = $destRel;
        }

        // Log to database
        try {
            Backup::create($backupRecord);
        } catch (\Exception $e) {
            // If logging fails, don't fail the backup itself, just log error
            // Log::error("Failed to log backup: " . $e->getMessage());
        }

        return $result;
    }

    private function dumpToFile(string $path): void
    {
        // If mysqldump is available, use it (best for full backups)
        $hasMysqldump = false;
        try {
            // Check if mysqldump exists in path
            $check = shell_exec('which mysqldump');
            if (!empty($check)) {
                $hasMysqldump = true;
            }
        } catch (\Exception $e) {
        }

        if ($hasMysqldump) {
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $db = config('database.connections.mysql.database');
            $user = config('database.connections.mysql.username');
            $pass = config('database.connections.mysql.password');

            $cmd = sprintf('mysqldump -h %s -P %s -u %s -p%s %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($user), escapeshellarg($pass), escapeshellarg($db));
            $proc = proc_open($cmd, [1 => ['file', $path, 'w']], $pipes);
            if (is_resource($proc)) {
                $code = proc_close($proc);
                if ($code === 0) return; // Success
            }
        }

        // Fallback: Poor man's backup (JSON export via PHP)
        // This is not ideal for large DBs but works for local dev/testing without mysqldump
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $prop = "Tables_in_$dbName";

        $fp = fopen($path, 'w');
        fwrite($fp, "-- Fallback Backup (JSON format) - mysqldump not found\n");
        fwrite($fp, "-- Created at: " . now() . "\n\n");

        foreach ($tables as $table) {
            $tableName = $table->$prop;
            $rows = DB::table($tableName)->get();
            fwrite($fp, "-- Table: $tableName\n");
            fwrite($fp, json_encode(['table' => $tableName, 'rows' => $rows]) . "\n");
        }
        fclose($fp);
    }

    private function uploadToS3(BackupConfig $cfg, string $file, string $key): void
    {
        // For Cloudflare R2, the endpoint typically looks like:
        // https://<ACCOUNT_ID>.r2.cloudflarestorage.com
        // We ensure it's a valid URL.

        $client = new S3Client([
            'version' => 'latest',
            'region' => $cfg->s3_region ?: 'auto', // R2 uses 'auto' typically
            'credentials' => [
                'key' => $cfg->s3_access_key,
                'secret' => $cfg->s3_secret,
            ],
            'endpoint' => $cfg->s3_endpoint ?: null,
            'use_path_style_endpoint' => true, // Important for R2/MinIO
        ]);

        $client->putObject([
            'Bucket' => $cfg->s3_bucket,
            'Key' => $key,
            'SourceFile' => $file,
            'ContentType' => 'application/sql',
        ]);
    }
}
