<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\BackupConfig;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup {--user= : Owner user id}';
    protected $description = 'Backup database and upload to configured cloud storage (S3-compatible)';

    public function handle(DatabaseBackupService $svc): int
    {
        $userId = (int) ($this->option('user') ?: User::query()->where('role', 'owner')->value('id'));
        if (!$userId) {
            $this->warn('No owner user found');
            return self::FAILURE;
        }
        $cfg = BackupConfig::where('user_id', $userId)->first();
        if (!$cfg || !$cfg->enabled) {
            $this->info('Backup skipped (disabled)');
            return self::SUCCESS;
        }
        $key = $svc->runForUser($userId);
        if (!$key) {
            $this->info('Backup skipped (not configured/enabled)');
            return self::SUCCESS;
        }
        $this->info('Backup uploaded: ' . $key);
        return self::SUCCESS;
    }
}
