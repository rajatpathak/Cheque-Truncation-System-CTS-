<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_instruments', function (Blueprint $table) {
            $table->id();
            $table->uuid('instrument_id')->unique();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();

            // Clearing metadata
            $table->string('clearing_type', 20)->default('CTS');     // CTS|NONCTS|SPECIAL|RETURN|GOVT
            $table->string('grid_code', 10)->nullable();
            $table->string('region_code', 10)->nullable();
            $table->string('zone_code', 10)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->string('san_number', 20)->nullable();
            $table->string('item_sequence_number', 20)->nullable();

            // MICR fields
            $table->string('micr_code', 35)->nullable();             // full 30-char band
            $table->string('cheque_number', 6)->nullable();
            $table->string('bank_sort_code', 9)->nullable();
            $table->string('account_number', 20)->nullable();

            // Data entry fields
            $table->string('account_name', 200)->nullable();
            $table->string('payee_name', 200)->nullable();
            $table->decimal('amount_figures', 18, 2)->nullable();
            $table->text('amount_words')->nullable();
            $table->date('instrument_date')->nullable();
            $table->date('presentment_date')->nullable();
            $table->string('drawer_bank_code', 20)->nullable();
            $table->string('drawer_branch_code', 20)->nullable();
            $table->text('endorsement_text')->nullable();

            // Image paths and hashes
            $table->text('image_path_grey')->nullable();
            $table->text('image_path_bw')->nullable();
            $table->text('image_path_uv')->nullable();
            $table->string('image_hash_grey', 64)->nullable();
            $table->string('image_hash_bw', 64)->nullable();
            $table->string('image_hash_uv', 64)->nullable();

            // IQA
            $table->string('iqa_status', 10)->default('PENDING');    // PASS|FAIL|PENDING
            $table->json('iqa_failure_reasons')->nullable();

            // OCR
            $table->json('ocr_data')->nullable();

            // Signature / PKI
            $table->string('signature_status', 10)->default('UNSIGNED'); // UNSIGNED|SIGNED|VERIFIED|INVALID
            $table->text('digital_signature')->nullable();

            // Fraud Detection
            $table->string('fraud_status', 20)->default('PENDING');   // CLEAR|SUSPICIOUS|BLOCKED|PENDING
            $table->json('fraud_flags')->nullable();
            $table->boolean('cts2010_compliant')->nullable();
            $table->string('uv_check_status', 10)->nullable();
            $table->text('qr_code_data')->nullable();
            $table->boolean('is_government_cheque')->default(false);

            // Positive Pay
            $table->string('positive_pay_status', 20)->nullable();    // VERIFIED|UNVERIFIED|FAILED

            // Account validation
            $table->boolean('account_validated')->default(false);

            // High value flags
            $table->boolean('is_high_value')->default(false);
            $table->boolean('high_value_alert_sent')->default(false);

            // Status and audit
            $table->string('status', 30)->default('SCANNED');
            $table->text('hold_reason')->nullable();
            $table->text('remarks')->nullable();

            // User references
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();

            // Timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Indices for performance
            $table->index(['batch_id', 'status']);
            $table->index(['session_id', 'status']);
            $table->index(['cheque_number', 'micr_code']);
            $table->index(['account_number', 'instrument_date']);
            $table->index(['branch_code', 'created_at']);
            $table->index(['fraud_status']);
            $table->index(['iqa_status']);
            $table->index(['clearing_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_instruments');
    }
};
