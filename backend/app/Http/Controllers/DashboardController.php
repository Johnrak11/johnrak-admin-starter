<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DashboardWidget;
use App\Models\Backup;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Fetch Active Widgets
        $widgets = DashboardWidget::where('is_active', true)
            ->orderBy('position', 'asc')
            ->get();

        // 2. Health & Backup Logic
        $serverHealth = $this->checkServerHealth();
        $backupStatus = $this->checkBackupStatus($request->user()->id);

        // 3. Database Size (Extra touch for "Unified Dashboard")
        $dbSize = 'Unknown';
        try {
            // MySQL specific
            $res = DB::select('SELECT table_schema "name", ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) "size" FROM information_schema.TABLES GROUP BY table_schema');
            foreach ($res as $row) {
                if ($row->name === env('DB_DATABASE', 'laravel')) {
                    $dbSize = $row->size . 'MB';
                    break;
                }
            }
        } catch (\Exception $e) {
        }

        // 4. Return Structure
        return response()->json([
            'widgets' => $widgets,
            'data' => [
                'server' => $serverHealth,
                'backup' => $backupStatus,
                'database_size' => $dbSize,
            ],
        ]);
    }

    private function checkServerHealth()
    {
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsagePct = 0;
        if ($diskTotal > 0) {
            $diskUsagePct = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
        }

        // RAM Usage
        $ramUsagePct = 0;
        try {
            // Try standard 'free -m' (Linux)
            $freeOutput = shell_exec('free -m');
            if ($freeOutput) {
                // Parse: Mem: 7976 2345 ...
                // Matches: total, used
                if (preg_match('/Mem:\s+(\d+)\s+(\d+)/', $freeOutput, $matches)) {
                    $total = $matches[1];
                    $used = $matches[2];
                    if ($total > 0) {
                        $ramUsagePct = round(($used / $total) * 100, 1);
                    }
                }
            } else {
                // Fallback for macOS (vm_stat)
                $vmStat = shell_exec('vm_stat');
                if ($vmStat) {
                    // Extract page size (usually 4096 bytes)
                    $pageSize = 4096; // Default assumption

                    // Parse pages free, active, inactive, wired
                    preg_match('/Pages free:\s+(\d+)\./', $vmStat, $freeMatches);
                    preg_match('/Pages active:\s+(\d+)\./', $vmStat, $activeMatches);
                    preg_match('/Pages inactive:\s+(\d+)\./', $vmStat, $inactiveMatches);
                    preg_match('/Pages wired down:\s+(\d+)\./', $vmStat, $wiredMatches);

                    if (isset($freeMatches[1], $activeMatches[1], $wiredMatches[1])) {
                        $free = $freeMatches[1] * $pageSize;
                        $active = $activeMatches[1] * $pageSize;
                        $inactive = ($inactiveMatches[1] ?? 0) * $pageSize;
                        $wired = $wiredMatches[1] * $pageSize;

                        $used = $active + $wired; // Active + Wired is a decent proxy for "Used"
                        $total = $free + $active + $inactive + $wired;

                        if ($total > 0) {
                            $ramUsagePct = round(($used / $total) * 100, 1);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $ramUsagePct = 0;
        }

        // CPU Load (sys_getloadavg)
        $cpuLoad = 0;
        $load = sys_getloadavg();
        if ($load && isset($load[0])) {
            $cpuLoad = $load[0];
        }

        return [
            'disk_percent' => $diskUsagePct,
            'ram_percent' => $ramUsagePct,
            'cpu_load' => $cpuLoad,
        ];
    }

    private function checkBackupStatus($userId)
    {
        $lastBackup = Backup::where('user_id', $userId)
            ->where('is_successful', true)
            ->latest()
            ->first();

        $status = 'warning';
        $lastRun = 'Never';

        if ($lastBackup) {
            $lastRun = $lastBackup->created_at->diffForHumans();
            if ($lastBackup->created_at->gt(now()->subHours(24))) {
                $status = 'safe';
            }
        }

        return [
            'status' => $status,
            'last_run' => $lastRun,
        ];
    }
}
