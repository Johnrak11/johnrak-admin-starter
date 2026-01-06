<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Portfolio\AttachmentController;
use App\Http\Controllers\Portfolio\CertificationController;
use App\Http\Controllers\Portfolio\EducationController;
use App\Http\Controllers\Portfolio\ExperienceController;
use App\Http\Controllers\Portfolio\LinkedInImportController;
use App\Http\Controllers\Portfolio\ProfileController;
use App\Http\Controllers\Portfolio\ProjectController;
use App\Http\Controllers\Portfolio\SkillController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\ClientPortfolioSyncController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CryptoController;

Route::prefix('auth')->group(function () {
    // Route::options('/login', function() { return response()->noContent(); }); // Handle preflight explicitly if middleware fails
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/login/2fa', [AuthController::class, 'login2fa'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::put('/password', [AuthController::class, 'updatePassword'])->middleware('auth:sanctum'); // Password Reset
});

Route::middleware(['auth:sanctum', 'ensure.owner', 'audit.log'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Crypto Intel
    Route::get('/crypto/trades', [CryptoController::class, 'listTrades']);
    Route::post('/crypto/trades', [CryptoController::class, 'saveTrade']);
    Route::post('/crypto/trades/{trade}/check', [CryptoController::class, 'checkTrade']);
    Route::delete('/crypto/trades/{trade}', [CryptoController::class, 'deleteTrade']);
    Route::get('/crypto/{coin}', [CryptoController::class, 'getMarketData']);

    // Telegram Test
    Route::get('/admin/test-telegram', function () {
        $ok = \App\Services\TelegramService::sendMessage("🚀 <b>Johnrak System is Online!</b>\nTest notification from Admin Dashboard.");
        return response()->json(['sent' => $ok]);
    });
});

Route::middleware(['auth:sanctum', 'ensure.owner', 'audit.log'])->prefix('portfolio')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::apiResource('experiences', ExperienceController::class)->except(['show']);
    Route::apiResource('educations', EducationController::class)->except(['show']);
    Route::apiResource('skills', SkillController::class)->except(['show']);
    Route::apiResource('certifications', CertificationController::class)->except(['show']);
    Route::apiResource('projects', ProjectController::class)->except(['show']);

    Route::post('/attachments', [AttachmentController::class, 'store']);
    Route::get('/attachments', [AttachmentController::class, 'index']);
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    Route::post('/linkedin/import', [LinkedInImportController::class, 'importJson']);
});

Route::middleware(['auth:sanctum', 'ensure.owner', 'audit.log'])->prefix('security')->group(function () {
    Route::post('/2fa/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:login');
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:login');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:login');
    Route::get('/2fa/status', [TwoFactorController::class, 'status']);
    Route::post('/2fa/regenerate-recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:login');
    // Route::post('/portfolio-sync/token', [ClientPortfolioSyncController::class, 'issueToken'])->middleware('throttle:login'); // Removed manual flow
    Route::get('/backup/config', [BackupController::class, 'getConfig']);
    Route::post('/backup/config', [BackupController::class, 'saveConfig']);
    Route::post('/backup/run', [BackupController::class, 'run'])->middleware('throttle:login');
});

Route::middleware(['auth:sanctum', 'ensure.owner', 'audit.log'])->prefix('ai')->group(function () {
    Route::get('/config', [AiController::class, 'config']);
    Route::post('/config', [AiController::class, 'updateConfig']);
    Route::post('/reindex', [AiController::class, 'reindex'])->middleware('throttle:login');
    Route::get('/search', [AiController::class, 'search'])->middleware('throttle:ai-search');
    Route::post('/chat', [AiController::class, 'chat'])->middleware('throttle:ai-search');
    Route::post('/case-study', [AiController::class, 'generateCaseStudy'])->middleware('throttle:ai-search');
    Route::post('/bio', [AiController::class, 'generateBio'])->middleware('throttle:ai-search');
});

Route::prefix('client')->group(function () {
    Route::post('/portfolio/sync', [ClientPortfolioSyncController::class, 'sync'])->middleware('throttle:portfolio-sync');
    Route::post('/ai/chat', [AiController::class, 'publicChat'])->middleware('throttle:ai-search');
});

Route::prefix('payment')->group(function () {
    // Public webhook (no auth, but requires secret key)
    Route::post('/webhook', [\App\Http\Controllers\PaymentController::class, 'webhook'])->middleware('throttle:payment-webhook');
    
    // Protected routes (owner only)
    Route::middleware(['auth:sanctum', 'ensure.owner', 'audit.log'])->group(function () {
        Route::get('/config', [\App\Http\Controllers\PaymentController::class, 'getConfig']);
        Route::post('/config', [\App\Http\Controllers\PaymentController::class, 'saveConfig']);
        Route::get('/webhook-secret', [\App\Http\Controllers\PaymentController::class, 'getWebhookSecret']);
        Route::get('/merchant-info', [\App\Http\Controllers\PaymentController::class, 'getMerchantInfo']);
        Route::post('/merchant-info', [\App\Http\Controllers\PaymentController::class, 'saveMerchantInfo']);
        Route::post('/test', [\App\Http\Controllers\PaymentController::class, 'testPayment']);
        Route::get('/transactions', [\App\Http\Controllers\PaymentController::class, 'listTransactions']);
        Route::get('/transactions/{transaction}', [\App\Http\Controllers\PaymentController::class, 'getTransaction']);
        Route::post('/tokens', [\App\Http\Controllers\PaymentController::class, 'generateToken']);
        Route::get('/tokens', [\App\Http\Controllers\PaymentController::class, 'listTokens']);
        Route::delete('/tokens/{token}', [\App\Http\Controllers\PaymentController::class, 'revokeToken']);
    });
});
