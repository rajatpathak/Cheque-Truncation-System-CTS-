<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 30)->unique();
            $table->string('batch_type', 20)->default('CTS');
            $table->string('branch_code', 20)->nullable();
            $table->string('grid_code', 10)->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->unsignedInteger('total_instruments')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedInteger('iqa_pass_count')->default(0);
            $table->unsignedInteger('iqa_fail_count')->default(0);
            $table->boolean('data_entry_complete')->default(false);
            $table->boolean('verification_complete')->default(false);
            $table->boolean('signed')->default(false);
            $table->boolean('submitted_to_chi')->default(false);
            $table->string('chi_reference', 50)->nullable();
            $table->timestamp('chi_submission_time')->nullable();
            $table->json('chi_response')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->string('scanner_device_id', 50)->nullable();
            $table->string('scan_mode', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->index(['branch_code', 'created_at']);
            $table->index('batch_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_batches');
    }
};
