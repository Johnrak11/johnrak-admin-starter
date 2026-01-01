<?php

namespace App\Http\Controllers;

use App\Models\BackupConfig;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class BackupController extends Controller
{
    public function getConfig(Request $request)
    {
        $cfg = BackupConfig::firstOrCreate(['user_id' => $request->user()->id], ['enabled' => false]);
        return response()->json([
            'enabled' => (bool) $cfg->enabled,
            'provider' => $cfg->provider,
            's3' => [
                'region' => $cfg->s3_region,
                'bucket' => $cfg->s3_bucket,
                'endpoint' => $cfg->s3_endpoint,
                'path_prefix' => $cfg->s3_path_prefix,
                'configured' => (bool) ($cfg->s3_access_key && $cfg->s3_secret && $cfg->s3_bucket),
            ],
        ]);
    }

    public function saveConfig(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider' => ['nullable', 'in:s3,r2'],
            's3_access_key' => ['nullable', 'string'],
            's3_secret' => ['nullable', 'string'],
            's3_region' => ['nullable', 'string'],
            's3_bucket' => ['nullable', 'string'],
            's3_endpoint' => ['nullable', 'string'],
            's3_path_prefix' => ['nullable', 'string'],
        ]);

        $cfg = BackupConfig::firstOrCreate(['user_id' => $request->user()->id]);
        foreach (['enabled', 'provider', 's3_region', 's3_bucket', 's3_endpoint', 's3_path_prefix'] as $k) {
            if (array_key_exists($k, $data)) $cfg->$k = $data[$k];
        }
        if (!empty($data['s3_access_key'])) $cfg->s3_access_key = $data['s3_access_key'];
        if (!empty($data['s3_secret'])) $cfg->s3_secret = $data['s3_secret'];
        $cfg->save();

        return response()->json(['ok' => true]);
    }

    public function run(Request $request, DatabaseBackupService $svc)
    {
        $res = $svc->runForUser($request->user()->id);
        if (!$res) return response()->json(['message' => 'Backup not configured'], 422);
        return response()->json($res);
    }
}
