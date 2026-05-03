# IOB CTS National Grid — PHP Laravel Scaffold
**Tender:** GEM/2026/B/7367951 | **Bank:** Indian Overseas Bank | **Model:** CAPEX 5-Year

---

## Project Structure

```
cts-laravel/
├── app/
│   ├── Console/Kernel.php                    # Scheduled jobs (EOD, reports, uptime)
│   ├── Events/FailoverInitiated.php          # DR failover event
│   ├── Exceptions/SignatureException.php     # PKI signing exception
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthController.php        # Login, MFA, SSO, password
│   │   │   │   ├── MakerCheckerController.php# Approval queue (approve/reject)
│   │   │   │   └── UserManagementController.php
│   │   │   ├── OutwardClearing/
│   │   │   │   ├── ScanController.php        # Capture, rescan, IQA, bulk-upload
│   │   │   │   ├── BatchController.php       # Batch lifecycle + CHI/DEM submit
│   │   │   │   └── MICRController.php        # MICR read, validate, correct
│   │   │   ├── InwardClearing/
│   │   │   │   ├── InwardController.php      # Session mgmt, CHI/DEM receive
│   │   │   │   ├── DataEntryController.php   # Queue, save, verify
│   │   │   │   └── OCRController.php         # Extract, bulk-extract
│   │   │   ├── FraudDetection/
│   │   │   │   ├── FraudDetectionController.php # Full pipeline + blacklist
│   │   │   │   └── PositivePayController.php    # NPCI CPPS (w.e.f. 01.01.2021)
│   │   │   ├── ReturnProcessing/
│   │   │   │   ├── ReturnController.php      # Inward/outward returns, represent
│   │   │   │   └── ReturnMemoController.php  # PDF memo generate + email
│   │   │   ├── DigitalSignature/
│   │   │   │   └── SignatureController.php   # Instrument/file/batch sign, verify
│   │   │   ├── Integration/
│   │   │   │   ├── CHIDEMController.php      # CHI/DEM submit, receive, rejections
│   │   │   │   ├── NPCIController.php        # NPCI grid, continuous clearing
│   │   │   │   └── FinacleController.php     # CBS validate, upload, sync masters
│   │   │   ├── Reporting/
│   │   │   │   ├── DashboardController.php   # Real-time ops dashboard
│   │   │   │   └── ReportController.php      # Daily/monthly/yearly/custom reports
│   │   │   ├── ImageStorage/
│   │   │   │   └── ImageController.php       # Retrieve, magnify, archive, purge
│   │   │   ├── Administration/
│   │   │   │   ├── AdminController.php       # Parameters, EOD, access review
│   │   │   │   ├── MasterController.php      # Branch/bank/grid masters
│   │   │   │   └── MigrationController.php   # Legacy data migration
│   │   │   └── BCP/
│   │   │       ├── BCPController.php         # Failover, switchback, health
│   │   │       └── DRController.php          # DR drills, schedule, report
│   │   └── Middleware/
│   │       ├── AuditLogger.php               # Immutable audit trail on all requests
│   │       ├── MakerCheckerMiddleware.php    # Intercepts writes → approval queue
│   │       └── CheckUserLimit.php            # Daily cheque processing limit
│   ├── Jobs/
│   │   ├── RunOCRExtraction.php              # Async OCR per instrument
│   │   ├── RunFraudDetection.php             # Async fraud pipeline
│   │   ├── RunEndOfDay.php                   # EOD: disable users, close sessions
│   │   ├── ProcessBulkUpload.php             # Bulk CSV/Excel cheque import
│   │   └── GenerateScheduledReports.php      # PDF/CSV report generation
│   ├── Listeners/NotifyOnFailover.php        # IT alert on failover event
│   ├── Models/
│   │   ├── User.php                          # + HasRoles, HasApiTokens
│   │   ├── Instrument.php                    # Core cheque record (50+ fields)
│   │   ├── Batch.php
│   │   ├── ClearingSession.php
│   │   ├── ReturnInstrument.php
│   │   ├── PendingApproval.php               # Maker-Checker queue
│   │   ├── AuditTrail.php                    # Immutable audit log
│   │   ├── FraudAlert.php
│   │   └── DRDrill.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php            # Service bindings
│   │   └── EventServiceProvider.php          # Event → Listener map
│   └── Services/
│       ├── IQAService.php                    # Image Quality Assessment (NPCI spec)
│       ├── MICRService.php                   # 30-char MICR band parse/validate
│       ├── OCRService.php                    # AI/ICR extraction service
│       ├── PKISignatureService.php           # SHA256withRSA, HSM, 3-level signing
│       ├── FraudDetectionService.php         # 8-check pipeline (CTS2010→Blacklist)
│       ├── PositivePayService.php            # NPCI CPPS integration
│       ├── CHIDEMService.php                 # CHI/DEM file build + mTLS submit
│       ├── ReplicationMonitorService.php     # DC/DR lag, failover, health
│       └── NotificationService.php           # SMS + email notifications
├── bootstrap/app.php                         # Laravel 11 app bootstrap
├── config/
│   ├── cts.php                               # All CTS-specific configuration
│   ├── database.php                          # Oracle DC + DR + Legacy connections
│   └── queue.php                             # Redis queues (high/default/low)
├── database/
│   ├── migrations/                           # 10 migrations (users → masters)
│   └── seeders/
│       ├── CTSRolesSeeder.php                # 13 roles + all permissions
│       ├── CTSParametersSeeder.php           # 30+ configurable parameters
│       └── CTSReturnReasonsSeeder.php        # 30 return reason codes
└── routes/
    ├── api.php                               # 80+ secured API routes, all 11 modules
    └── web.php                               # Healthcheck + system info
```

---

## 11 Modules Covered

| # | Module | Key Files |
|---|--------|-----------|
| 1 | Auth & User Management | `AuthController`, `UserManagementController`, `MakerCheckerController` |
| 2 | Outward Clearing | `ScanController`, `BatchController`, `MICRController`, `IQAService` |
| 3 | Inward Clearing / OCR | `InwardController`, `DataEntryController`, `OCRController`, `OCRService` |
| 4 | Fraud Detection | `FraudDetectionController`, `PositivePayController`, `FraudDetectionService` |
| 5 | Return Processing | `ReturnController`, `ReturnMemoController` |
| 6 | Digital Signature / PKI | `SignatureController`, `PKISignatureService` |
| 7 | Integration Layer | `CHIDEMController`, `NPCIController`, `FinacleController`, `CHIDEMService` |
| 8 | Reporting & MIS | `DashboardController`, `ReportController`, `GenerateScheduledReports` |
| 9 | Image Storage | `ImageController` |
| 10 | Administration | `AdminController`, `MasterController`, `MigrationController` |
| 11 | BCP / DR | `BCPController`, `DRController`, `ReplicationMonitorService` |

---

## Getting Started

```bash
# 1. Install dependencies
composer install

# 2. Copy environment file and configure
cp .env.example .env
php artisan key:generate

# 3. Run migrations (Oracle or MySQL for dev)
php artisan migrate

# 4. Seed roles, permissions, parameters
php artisan db:seed

# 5. Start queue workers (3 priority levels)
php artisan queue:work --queue=cts-high,cts-default,cts-low

# 6. Start scheduler (add to crontab)
* * * * * cd /path/to/cts-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## Key Technical Decisions

- **Database:** Oracle RDBMS (yajra/laravel-oci8) — 4 connections (DC, DR, Legacy, CBS Mirror)
- **Auth:** Laravel Sanctum + LDAP/AD + MFA (SMS OTP) + SSO/SAML
- **Queue:** Redis with 3 priority levels (high: fraud/sign, default: OCR, low: reports)
- **PKI:** SHA256withRSA, HSM via PKCS#11, 3-level signing (instrument → file → central)
- **Maker-Checker:** Middleware-based — all write operations queued; checkers cannot self-approve
- **Fraud Pipeline:** CTS2010 → UV → QR → Duplicate → Tamper → Torn/Pasted → MICR Anomaly → Blacklist
- **MICR:** 30-char band (Cheque[6] + Sort[9] + Account[6] + TxnCode[2] + City[3])
- **Positive Pay:** NPCI CPPS (w.e.f. 01.01.2021 per RBI mandate)
- **IQA:** NPCI-spec checks: size, brightness, skew, piggyback, torn corner, streaks, partial
- **BCP/DR:** Auto-failover with RPO ≤ 5 min, RTO ≤ 30 min; quarterly DR drills
- **Audit:** Immutable trail on all actions; 10-year retention; WORM-compliant storage
- **Image:** 10-year WORM archive, SHA-256 hash at capture, X9.37 standard, 200 DPI CCITT4
