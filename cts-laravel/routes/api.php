<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\UserManagementController;
use App\Http\Controllers\OutwardClearing\ScanController;
use App\Http\Controllers\OutwardClearing\BatchController;
use App\Http\Controllers\OutwardClearing\MICRController;
use App\Http\Controllers\InwardClearing\InwardController;
use App\Http\Controllers\InwardClearing\DataEntryController;
use App\Http\Controllers\InwardClearing\OCRController;
use App\Http\Controllers\FraudDetection\FraudDetectionController;
use App\Http\Controllers\FraudDetection\PositivePayController;
use App\Http\Controllers\ReturnProcessing\ReturnController;
use App\Http\Controllers\ReturnProcessing\ReturnMemoController;
use App\Http\Controllers\DigitalSignature\SignatureController;
use App\Http\Controllers\Integration\CHIDEMController;
use App\Http\Controllers\Integration\FinacleController;
use App\Http\Controllers\Integration\NPCIController;
use App\Http\Controllers\Reporting\ReportController;
use App\Http\Controllers\Reporting\DashboardController;
use App\Http\Controllers\ImageStorage\ImageController;
use App\Http\Controllers\Administration\AdminController;
use App\Http\Controllers\Administration\MasterController;
use App\Http\Controllers\Administration\MigrationController;
use App\Http\Controllers\BCP\BCPController;
use App\Http\Controllers\BCP\DRController;

/*
|--------------------------------------------------------------------------
| CTS API Routes
| All routes protected by Sanctum + Role-based middleware
|--------------------------------------------------------------------------
*/

/* ─────────────────────────────────────────────
   MODULE 1 — Authentication & User Management
───────────────────────────────────────────── */
Route::prefix('auth')->group(function () {
    Route::post('/login',               [AuthController::class, 'login']);
    Route::post('/logout',              [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/refresh',             [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::post('/mfa/verify',          [AuthController::class, 'verifyMFA']);
    Route::post('/mfa/setup',           [AuthController::class, 'setupMFA'])->middleware('auth:sanctum');
    Route::post('/password/change',     [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::post('/sso/callback',        [AuthController::class, 'ssoCallback']);
});

Route::middleware(['auth:sanctum', 'cts.audit'])->group(function () {

    /* ── User Management ── */
    Route::prefix('users')->middleware('role:admin|supervisor')->group(function () {
        Route::get('/',                 [UserManagementController::class, 'index']);
        Route::post('/',                [UserManagementController::class, 'store'])->middleware('maker-checker');
        Route::get('/{id}',             [UserManagementController::class, 'show']);
        Route::put('/{id}',             [UserManagementController::class, 'update'])->middleware('maker-checker');
        Route::delete('/{id}',          [UserManagementController::class, 'destroy'])->middleware('maker-checker');
        Route::post('/{id}/enable',     [UserManagementController::class, 'enable']);
        Route::post('/{id}/disable',    [UserManagementController::class, 'disable']);
        Route::post('/{id}/roles',      [UserManagementController::class, 'assignRoles'])->middleware('maker-checker');
        Route::post('/{id}/limits',     [UserManagementController::class, 'setLimits'])->middleware('maker-checker');
        Route::get('/access-review',    [UserManagementController::class, 'accessReview']);
    });

    /* ─────────────────────────────────────────────
       MODULE 2 — Outward Clearing
    ───────────────────────────────────────────── */
    Route::prefix('outward')->middleware('role:branch_operator|hub_operator|supervisor')->group(function () {

        /* Batch Management */
        Route::get('/batches',                      [BatchController::class, 'index']);
        Route::post('/batches',                     [BatchController::class, 'create']);
        Route::get('/batches/{id}',                 [BatchController::class, 'show']);
        Route::put('/batches/{id}',                 [BatchController::class, 'update']);
        Route::post('/batches/{id}/close',          [BatchController::class, 'close']);
        Route::post('/batches/{id}/submit',         [BatchController::class, 'submit'])->middleware('maker-checker');
        Route::post('/batches/{id}/type',           [BatchController::class, 'setType']);

        /* Scanner Interface */
        Route::post('/scan/capture',                [ScanController::class, 'capture']);
        Route::post('/scan/rescan/{instrument_id}', [ScanController::class, 'rescan']);
        Route::get('/scan/iqa/{instrument_id}',     [ScanController::class, 'iqaStatus']);
        Route::post('/scan/bulk-upload',            [ScanController::class, 'bulkUpload']);
        Route::get('/scan/devices',                 [ScanController::class, 'listDevices']);

        /* MICR */
        Route::post('/micr/read',                   [MICRController::class, 'read']);
        Route::post('/micr/validate',               [MICRController::class, 'validate']);
        Route::put('/micr/{instrument_id}/correct', [MICRController::class, 'correct'])->middleware('maker-checker');

        /* Instruments */
        Route::get('/instruments',                  [ScanController::class, 'instruments']);
        Route::get('/instruments/{id}',             [ScanController::class, 'instrument']);
        Route::get('/instruments/{id}/image',       [ImageController::class, 'serveInstrumentImage']);
        Route::get('/instruments/{id}/snippet',     [ImageController::class, 'serveSnippet']);
        Route::post('/instruments/{id}/hold',       [ScanController::class, 'hold']);
        Route::post('/instruments/{id}/release',    [ScanController::class, 'release']);
        Route::post('/instruments/{id}/remark',     [ScanController::class, 'addRemark']);
    });

    /* ─────────────────────────────────────────────
       MODULE 3 — Inward Clearing & AI/OCR
    ───────────────────────────────────────────── */
    Route::prefix('inward')->middleware('role:inward_operator|supervisor|data_entry')->group(function () {

        Route::get('/sessions',                         [InwardController::class, 'sessions']);
        Route::post('/sessions',                        [InwardController::class, 'createSession']);
        Route::get('/sessions/{id}/instruments',        [InwardController::class, 'sessionInstruments']);
        Route::post('/sessions/{id}/submit',            [InwardController::class, 'submitSession'])->middleware('maker-checker');

        /* OCR / AI Engine */
        Route::post('/ocr/extract/{instrument_id}',    [OCRController::class, 'extract']);
        Route::get('/ocr/status/{instrument_id}',      [OCRController::class, 'status']);
        Route::post('/ocr/bulk-extract',               [OCRController::class, 'bulkExtract']);

        /* Data Entry */
        Route::get('/data-entry/queue',                [DataEntryController::class, 'queue']);
        Route::post('/data-entry/{instrument_id}',     [DataEntryController::class, 'save']);
        Route::put('/data-entry/{instrument_id}',      [DataEntryController::class, 'update']);
        Route::post('/data-entry/{instrument_id}/verify', [DataEntryController::class, 'verify'])->middleware('maker-checker');

        /* Account Validation */
        Route::post('/validate/account',              [FinacleController::class, 'validateAccount']);
        Route::post('/validate/bulk',                 [FinacleController::class, 'bulkValidate']);
    });

    /* ─────────────────────────────────────────────
       MODULE 4 — Fraud Detection & Security
    ───────────────────────────────────────────── */
    Route::prefix('fraud')->middleware('role:supervisor|fraud_officer')->group(function () {
        Route::post('/scan/{instrument_id}',           [FraudDetectionController::class, 'scanInstrument']);
        Route::get('/alerts',                          [FraudDetectionController::class, 'alerts']);
        Route::get('/alerts/{id}',                     [FraudDetectionController::class, 'alert']);
        Route::post('/alerts/{id}/resolve',            [FraudDetectionController::class, 'resolve']);
        Route::get('/blacklist',                       [FraudDetectionController::class, 'blacklist']);
        Route::post('/blacklist',                      [FraudDetectionController::class, 'addToBlacklist'])->middleware('maker-checker');
        Route::delete('/blacklist/{account_no}',       [FraudDetectionController::class, 'removeFromBlacklist'])->middleware('maker-checker');
        Route::get('/suspicious',                      [FraudDetectionController::class, 'suspiciousInstruments']);
        Route::get('/cts2010/report',                  [FraudDetectionController::class, 'cts2010ComplianceReport']);

        /* Positive Pay */
        Route::post('/positive-pay/check',             [PositivePayController::class, 'check']);
        Route::get('/positive-pay/status/{ref}',       [PositivePayController::class, 'status']);
        Route::post('/positive-pay/register',          [PositivePayController::class, 'register']);
    });

    /* ─────────────────────────────────────────────
       MODULE 5 — Return Processing
    ───────────────────────────────────────────── */
    Route::prefix('returns')->middleware('role:return_officer|supervisor')->group(function () {
        Route::get('/',                                [ReturnController::class, 'index']);
        Route::post('/inward',                         [ReturnController::class, 'processInwardReturn']);
        Route::post('/outward',                        [ReturnController::class, 'processOutwardReturn']);
        Route::post('/{id}/represent',                 [ReturnController::class, 'represent'])->middleware('maker-checker');
        Route::post('/{id}/sign-submit',               [ReturnController::class, 'signAndSubmit'])->middleware('maker-checker');
        Route::get('/frequent-accounts',              [ReturnController::class, 'frequentReturnAccounts']);

        /* Return Memos */
        Route::get('/memo/{id}',                       [ReturnMemoController::class, 'generate']);
        Route::post('/memo/bulk',                      [ReturnMemoController::class, 'bulkGenerate']);
        Route::post('/memo/{id}/email',                [ReturnMemoController::class, 'email']);
    });

    /* ─────────────────────────────────────────────
       MODULE 6 — Digital Signature & PKI
    ───────────────────────────────────────────── */
    Route::prefix('signatures')->middleware('role:signing_officer|supervisor')->group(function () {
        Route::post('/instrument/{id}',                [SignatureController::class, 'signInstrument']);
        Route::post('/file/{clearing_file_id}',        [SignatureController::class, 'signFile']);
        Route::post('/batch/{batch_id}',               [SignatureController::class, 'signBatch']);
        Route::get('/verify/{instrument_id}',          [SignatureController::class, 'verify']);
        Route::get('/certificates',                    [SignatureController::class, 'certificates']);
        Route::post('/certificates/renew/{id}',        [SignatureController::class, 'renewCertificate']);
    });

    /* ─────────────────────────────────────────────
       MODULE 7 — Integration Layer
    ───────────────────────────────────────────── */
    Route::prefix('integration')->middleware('role:supervisor|integration_officer')->group(function () {

        /* CHI / DEM */
        Route::post('/chi-dem/submit/{session_id}',    [CHIDEMController::class, 'submit']);
        Route::get('/chi-dem/status/{ref}',            [CHIDEMController::class, 'status']);
        Route::post('/chi-dem/receive',                [CHIDEMController::class, 'receive']);
        Route::get('/chi-dem/rejections',              [CHIDEMController::class, 'rejections']);

        /* NPCI */
        Route::post('/npci/submit',                    [NPCIController::class, 'submit']);
        Route::get('/npci/status/{batch_ref}',         [NPCIController::class, 'status']);
        Route::post('/npci/continuous-clearing',       [NPCIController::class, 'continuousClearing']);

        /* Finacle CBS */
        Route::post('/cbs/upload-clearing',            [FinacleController::class, 'uploadClearingFile']);
        Route::post('/cbs/sync-masters',               [FinacleController::class, 'syncMasters']);
        Route::post('/cbs/signature-master',           [FinacleController::class, 'fetchSignatureMaster']);
    });

    /* ─────────────────────────────────────────────
       MODULE 8 — Reporting & MIS
    ───────────────────────────────────────────── */
    Route::prefix('reports')->group(function () {
        Route::get('/dashboard',                       [DashboardController::class, 'index']);
        Route::get('/dashboard/grid-summary',          [DashboardController::class, 'gridSummary']);
        Route::get('/dashboard/branch-summary',        [DashboardController::class, 'branchSummary']);
        Route::get('/dashboard/processing-stages',     [DashboardController::class, 'processingStages']);

        Route::get('/clearing/daily',                  [ReportController::class, 'daily']);
        Route::get('/clearing/monthly',                [ReportController::class, 'monthly']);
        Route::get('/clearing/yearly',                 [ReportController::class, 'yearly']);
        Route::get('/clearing/session/{id}',           [ReportController::class, 'sessionReport']);
        Route::post('/clearing/custom',                [ReportController::class, 'custom']);

        Route::get('/iqa-failures',                    [ReportController::class, 'iqaFailures']);
        Route::get('/exceptions',                      [ReportController::class, 'exceptions']);
        Route::get('/audit-trail',                     [ReportController::class, 'auditTrail'])->middleware('role:admin|auditor');
        Route::get('/return-analysis',                 [ReportController::class, 'returnAnalysis']);
        Route::post('/schedule',                       [ReportController::class, 'scheduleReport'])->middleware('maker-checker');
        Route::get('/archived',                        [ReportController::class, 'archivedReports']);
    });

    /* ─────────────────────────────────────────────
       MODULE 9 — Image Storage & Archival
    ───────────────────────────────────────────── */
    Route::prefix('images')->group(function () {
        Route::get('/{instrument_id}',                 [ImageController::class, 'retrieve']);
        Route::get('/{instrument_id}/uv',              [ImageController::class, 'retrieveUV']);
        Route::post('/{instrument_id}/archive',        [ImageController::class, 'archive'])->middleware('role:admin');
        Route::get('/archived/{instrument_id}',        [ImageController::class, 'retrieveArchived']);
        Route::post('/email/{instrument_id}',          [ImageController::class, 'emailImage']);
        Route::get('/{instrument_id}/magnify',         [ImageController::class, 'magnify']);
        Route::get('/{instrument_id}/qr-barcode',      [ImageController::class, 'extractQRBarcode']);
        Route::delete('/{instrument_id}/purge',        [ImageController::class, 'purge'])->middleware('role:admin|maker-checker');
    });

    /* ─────────────────────────────────────────────
       MODULE 10 — System Administration
    ───────────────────────────────────────────── */
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::apiResource('masters/branches',         MasterController::class . '@branch');
        Route::apiResource('masters/grids',            MasterController::class . '@grid');
        Route::apiResource('masters/clearing-houses',  MasterController::class . '@clearingHouse');
        Route::apiResource('masters/banks',            MasterController::class . '@bank');

        Route::get('/parameters',                      [AdminController::class, 'parameters']);
        Route::put('/parameters/{key}',                [AdminController::class, 'updateParameter'])->middleware('maker-checker');
        Route::post('/eod/run',                        [AdminController::class, 'runEOD']);
        Route::post('/eod/disable-all-users',          [AdminController::class, 'disableAllUsers']);
        Route::post('/eod/enable-users',               [AdminController::class, 'enableUsers']);
        Route::get('/access-review',                   [AdminController::class, 'monthlyAccessReview']);
        Route::get('/patch-compliance',                [AdminController::class, 'patchCompliance']);
        Route::post('/patches/{id}/apply',             [AdminController::class, 'applyPatch'])->middleware('maker-checker');

        /* Data Migration */
        Route::post('/migration/start',                [MigrationController::class, 'start']);
        Route::get('/migration/status',                [MigrationController::class, 'status']);
        Route::get('/migration/progress',              [MigrationController::class, 'progress']);
        Route::post('/migration/validate',             [MigrationController::class, 'validate']);
        Route::post('/migration/rollback',             [MigrationController::class, 'rollback']);
    });

    /* ─────────────────────────────────────────────
       MODULE 11 — BCP / Disaster Recovery
    ───────────────────────────────────────────── */
    Route::prefix('bcp')->middleware('role:admin|bcp_officer')->group(function () {
        Route::get('/status',                          [BCPController::class, 'status']);
        Route::get('/replication-status',              [BCPController::class, 'replicationStatus']);
        Route::post('/failover/initiate',              [BCPController::class, 'initiateFailover'])->middleware('maker-checker');
        Route::post('/failover/switchback',            [BCPController::class, 'switchback'])->middleware('maker-checker');
        Route::get('/health',                          [BCPController::class, 'healthCheck']);

        /* DR Drills */
        Route::get('/drills',                          [DRController::class, 'index']);
        Route::post('/drills',                         [DRController::class, 'schedule']);
        Route::post('/drills/{id}/start',              [DRController::class, 'start']);
        Route::post('/drills/{id}/complete',           [DRController::class, 'complete']);
        Route::get('/drills/{id}/report',              [DRController::class, 'report']);
    });

    /* ── Maker-Checker approval queue ── */
    Route::prefix('approvals')->middleware('role:checker|supervisor')->group(function () {
        Route::get('/',                                [\App\Http\Controllers\Auth\MakerCheckerController::class, 'index']);
        Route::post('/{id}/approve',                   [\App\Http\Controllers\Auth\MakerCheckerController::class, 'approve']);
        Route::post('/{id}/reject',                    [\App\Http\Controllers\Auth\MakerCheckerController::class, 'reject']);
    });
});
