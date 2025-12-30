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
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/login/2fa', [AuthController::class, 'login2fa'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
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
    Route::post('/portfolio-sync/token', [ClientPortfolioSyncController::class, 'issueToken'])->middleware('throttle:login');
});

Route::prefix('client')->group(function () {
    Route::post('/portfolio/sync', [ClientPortfolioSyncController::class, 'sync'])->middleware('throttle:portfolio-sync');
});
