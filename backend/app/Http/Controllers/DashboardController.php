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

        // RAM Usage: Prefer reading /proc/meminfo on Linux/Docker (most reliable)
        $ramUsagePct = 0;
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows RAM Check
                $cmd = 'wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value';
                $output = shell_exec($cmd);
                if ($output) {
                    preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatches);
                    preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatches);

                    if (isset($totalMatches[1], $freeMatches[1]) && $totalMatches[1] > 0) {
                        $total = $totalMatches[1]; // in KB
                        $free = $freeMatches[1];   // in KB
                        $used = $total - $free;
                        $ramUsagePct = round(($used / $total) * 100, 1);
                    }
                }
            } elseif (@is_readable('/proc/meminfo')) {
                $memInfo = file_get_contents('/proc/meminfo');
                $total = 0;
                $available = 0;

                if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $memInfo, $matches)) {
                    $total = $matches[1];
                }
                if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $memInfo, $matches)) {
                    $available = $matches[1];
                }

                // Fallback if MemAvailable is missing (older kernels)
                if ($available == 0 && preg_match('/MemFree:\s+(\d+)\s+kB/', $memInfo, $freeMatches)) {
                    $available = $freeMatches[1]; // Crude approximation
                }

                if ($total > 0) {
                    $used = $total - $available;
                    $ramUsagePct = round(($used / $total) * 100, 1);
                }
            } else {
                // Fallback for macOS (vm_stat) or systems without /proc
                $vmStat = shell_exec('vm_stat');
                if ($vmStat) {
                    $pageSize = 4096;
                    preg_match('/Pages free:\s+(\d+)\./', $vmStat, $freeMatches);
                    preg_match('/Pages active:\s+(\d+)\./', $vmStat, $activeMatches);
                    preg_match('/Pages wired down:\s+(\d+)\./', $vmStat, $wiredMatches);

                    if (isset($freeMatches[1], $activeMatches[1], $wiredMatches[1])) {
                        $free = $freeMatches[1] * $pageSize;
                        $active = $activeMatches[1] * $pageSize;
                        $wired = $wiredMatches[1] * $pageSize;

                        $used = $active + $wired;
                        $total = $free + $used; // Simplified total

                        if ($total > 0) {
                            $ramUsagePct = round(($used / $total) * 100, 1);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $ramUsagePct = 0;
        }

        // CPU Load
        $cpuLoad = 0;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows CPU Check
            $cmd = 'wmic cpu get loadpercentage';
            $output = shell_exec($cmd);
            if (preg_match('/(\d+)/', $output, $matches)) {
                $cpuLoad = (float) $matches[1];
            }
        } else {
            $load = sys_getloadavg();
            if ($load && isset($load[0])) {
                $cpuLoad = $load[0];
            } else {
                // Fallback: Read /proc/loadavg
                if (@is_readable('/proc/loadavg')) {
                    $content = file_get_contents('/proc/loadavg');
                    $parts = explode(' ', $content);
                    if (isset($parts[0])) {
                        $cpuLoad = (float) $parts[0];
                    }
                }
            }
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
