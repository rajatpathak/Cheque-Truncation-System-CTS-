<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instrument_id');
            $table->string('alert_type', 100);
            $table->string('severity', 10)->default('MEDIUM');     // HIGH|MEDIUM|LOW
            $table->json('checks_failed')->nullable();
            $table->json('cts2010_violations')->nullable();
            $table->string('uv_status', 10)->nullable();
            $table->string('qr_status', 10)->nullable();
            $table->string('duplicate_of', 50)->nullable();
            $table->boolean('tamper_detected')->default(false);
            $table->boolean('photocopy_detected')->default(false);
            $table->boolean('torn_pasted_detected')->default(false);
            $table->boolean('micr_anomaly')->default(false);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('OPEN');         // OPEN|RESOLVED|ESCALATED
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('auto_blocked')->default(false);
            $table->timestamps();

            $table->index(['instrument_id', 'status']);
            $table->index(['severity', 'status']);
            $table->index('created_at');
        });

        Schema::create('cts_blacklisted_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 20);
            $table->string('account_name', 200)->nullable();
            $table->string('bank_sort_code', 9)->nullable();
            $table->string('reason', 300);
            $table->string('blacklisted_by', 100);
            $table->boolean('active')->default(true);
            $table->date('blacklisted_date');
            $table->date('review_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique('account_number');
            $table->index(['active', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_blacklisted_accounts');
        Schema::dropIfExists('cts_fraud_alerts');
    }
};
