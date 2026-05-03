<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_clearing_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number', 30)->unique();
            $table->date('session_date');
            $table->string('session_type', 20);          // OUTWARD|INWARD|RETURN
            $table->string('clearing_type', 20)->nullable();
            $table->string('grid_code', 10)->nullable();
            $table->string('npci_grid_session_ref', 50)->nullable();
            $table->string('chi_session_ref', 50)->nullable();
            $table->string('dem_session_ref', 50)->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->unsignedInteger('total_batches')->default(0);
            $table->unsignedInteger('total_instruments')->default(0);
            $table->decimal('total_outward_amount', 18, 2)->default(0);
            $table->decimal('total_inward_amount', 18, 2)->default(0);
            $table->decimal('total_return_amount', 18, 2)->default(0);
            $table->text('submission_file_path')->nullable();
            $table->string('submission_file_hash', 64)->nullable();
            $table->boolean('submission_signed')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('settlement_at')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_continuous_clearing')->default(false);
            $table->boolean('eod_processed')->default(false);
            $table->timestamps();

            $table->index(['session_date', 'grid_code']);
            $table->index(['status', 'session_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_clearing_sessions');
    }
};
