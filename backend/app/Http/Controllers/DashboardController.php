<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DashboardWidget;
use App\Models\Backup;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        // 1. Fetch Active Widgets
        $widgets = DashboardWidget::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        // 2. System Health Logic
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsagePct = 0;
        if ($diskTotal > 0) {
            $diskUsagePct = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
        }

        // RAM Usage
        $ramUsagePct = 0;
        try {
            if (file_exists('/proc/meminfo')) {
                // Linux / Docker (Reads kernel info directly, no shell_exec needed)
                $memInfo = file_get_contents('/proc/meminfo');
                $values = [];
                foreach (explode("\n", $memInfo) as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                        $values[$matches[1]] = (int)$matches[2];
                    }
                }

                if (isset($values['MemTotal']) && isset($values['MemAvailable'])) {
                    // Accurate for modern Linux kernels (MemAvailable estimates usable memory)
                    $used = $values['MemTotal'] - $values['MemAvailable'];
                    $ramUsagePct = round(($used / $values['MemTotal']) * 100, 1);
                } elseif (isset($values['MemTotal']) && isset($values['MemFree'])) {
                    // Fallback for older kernels
                    $used = $values['MemTotal'] - $values['MemFree'];
                    $ramUsagePct = round(($used / $values['MemTotal']) * 100, 1);
                }
            } else {
                // Fallback: memory_get_usage() as a % of a hardcoded limit (e.g. 512MB)
                // This is just to show *something* active if we can't read OS stats
                $currentScriptMem = memory_get_usage(true);
                $limit = 512 * 1024 * 1024; // Assume 512MB container limit if unknown
                $ramUsagePct = round(($currentScriptMem / $limit) * 100, 2);
            }
        } catch (\Throwable $e) {
            // Keep 0 on error
        }

        // 3. Backup Status
        $lastBackup = Backup::where('user_id', $request->user()->id)
            ->where('is_successful', true)
            ->latest()
            ->first();
        
        $backupStatus = [
            'ran_today' => false,
            'last_run' => null,
            'disk' => 'N/A'
        ];

        if ($lastBackup) {
            $backupStatus['last_run'] = $lastBackup->created_at->diffForHumans();
            $backupStatus['disk'] = $lastBackup->disk;
            if ($lastBackup->created_at->isToday()) {
                $backupStatus['ran_today'] = true;
            }
        }

        return response()->json([
            'widgets' => $widgets,
            'system_health' => [
                'disk_usage_pct' => $diskUsagePct,
                'ram_usage_pct' => $ramUsagePct,
            ],
            'backup_status' => $backupStatus,
        ]);
    }
}
