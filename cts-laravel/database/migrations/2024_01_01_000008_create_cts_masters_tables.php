<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bank-Branch Master (sourced from RBI / NPCI)
        Schema::create('cts_bank_branch_master', function (Blueprint $table) {
            $table->id();
            $table->string('sort_code', 9)->unique();
            $table->string('bank_name', 200);
            $table->string('bank_code', 20);
            $table->string('ifsc_code', 11)->nullable();
            $table->string('branch_name', 200);
            $table->string('branch_code', 20)->nullable();
            $table->string('city_code', 3)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('grid_code', 10)->nullable();
            $table->string('micr_centre', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['bank_code', 'active']);
            $table->index(['grid_code', 'active']);
            $table->index('city_code');
        });

        // Branch Master (IOB branches)
        Schema::create('cts_branch_master', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 20)->unique();
            $table->string('branch_name', 200);
            $table->string('region_code', 10)->nullable();
            $table->string('zone_code', 10)->nullable();
            $table->string('grid_code', 10)->nullable();
            $table->string('sort_code', 9)->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->string('san_number', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('hub_branch', 20)->nullable();
            $table->boolean('is_hub')->default(false);
            $table->boolean('cts_enabled')->default(true);
            $table->boolean('scanner_available')->default(false);
            $table->string('scanner_model', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['grid_code', 'active']);
            $table->index(['region_code', 'zone_code']);
            $table->index('hub_branch');
        });

        // Return Reason Codes
        Schema::create('cts_return_reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description', 300);
            $table->string('category', 50)->nullable();  // DRAWER|PAYEE|TECHNICAL|FRAUD
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Scanner Device Master
        Schema::create('cts_scanner_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 50)->unique();
            $table->string('branch_code', 20);
            $table->string('model', 100);
            $table->string('make', 100);
            $table->string('serial_number', 100)->nullable();
            $table->string('driver_version', 50)->nullable();
            $table->date('amc_expiry')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_calibrated_at')->nullable();
            $table->timestamps();

            $table->index(['branch_code', 'active']);
        });

        // CTS Parameters
        Schema::create('cts_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('description', 500)->nullable();
            $table->string('module', 50)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // DR Drill Log
        Schema::create('cts_dr_drills', function (Blueprint $table) {
            $table->id();
            $table->string('drill_type', 20);
            $table->string('initiated_by', 100);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('dc_to_dr_switchover_time_seconds', 8, 2)->nullable();
            $table->decimal('dr_to_dc_switchover_time_seconds', 8, 2)->nullable();
            $table->decimal('rto_achieved_minutes', 8, 2)->nullable();
            $table->decimal('rpo_achieved_minutes', 8, 2)->nullable();
            $table->string('status', 20)->default('SCHEDULED');
            $table->string('outcome', 10)->nullable();           // PASS|FAIL|PARTIAL
            $table->json('participants')->nullable();
            $table->text('observations')->nullable();
            $table->text('report_path')->nullable();
            $table->decimal('sla_rto_target', 5, 2)->nullable();
            $table->decimal('sla_rpo_target', 5, 2)->nullable();
            $table->timestamps();
        });

        // EOD Log
        Schema::create('cts_eod_log', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedInteger('held_back_count')->default(0);
            $table->unsignedInteger('users_disabled')->default(0);
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Uptime monitoring log
        Schema::create('cts_uptime_log', function (Blueprint $table) {
            $table->id();
            $table->string('node', 10)->default('dc');   // dc|dr
            $table->string('status', 10);                // UP|DOWN
            $table->string('check_type', 20)->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['node', 'status', 'recorded_at']);
        });

        // Report Schedules
        Schema::create('cts_report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_name', 200);
            $table->string('report_type', 50);
            $table->string('frequency', 20);             // EOD|MONTHLY|YEARLY
            $table->text('email_recipients')->nullable();
            $table->string('output_format', 10)->default('PDF');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Patch log
        Schema::create('cts_patch_log', function (Blueprint $table) {
            $table->id();
            $table->string('patch_id', 50)->unique();
            $table->string('description', 500);
            $table->string('severity', 20)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->date('due_date')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_patch_log');
        Schema::dropIfExists('cts_report_schedules');
        Schema::dropIfExists('cts_uptime_log');
        Schema::dropIfExists('cts_eod_log');
        Schema::dropIfExists('cts_dr_drills');
        Schema::dropIfExists('cts_parameters');
        Schema::dropIfExists('cts_scanner_devices');
        Schema::dropIfExists('cts_return_reason_codes');
        Schema::dropIfExists('cts_branch_master');
        Schema::dropIfExists('cts_bank_branch_master');
    }
};
