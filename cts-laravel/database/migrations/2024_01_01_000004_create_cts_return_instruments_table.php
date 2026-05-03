<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_return_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_id', 50)->nullable();
            $table->unsignedBigInteger('original_instrument_id')->nullable();
            $table->string('return_type', 30);            // INWARD_RETURN|OUTWARD_RETURN|CHI_REJECTED
            $table->string('return_reason_code', 10)->nullable();
            $table->string('return_reason_description', 300)->nullable();
            $table->date('return_date')->nullable();
            $table->date('clearing_date')->nullable();
            $table->date('return_clearing_date')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->boolean('memo_generated')->default(false);
            $table->text('memo_path')->nullable();
            $table->boolean('memo_emailed')->default(false);
            $table->boolean('signed')->default(false);
            $table->boolean('submitted_to_chi')->default(false);
            $table->string('chi_reference', 50)->nullable();
            $table->unsignedInteger('representment_count')->default(0);
            $table->timestamp('last_representment_at')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->boolean('micr_corrected')->default(false);
            $table->boolean('iqa_override')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['return_date', 'return_type']);
            $table->index(['original_instrument_id']);
            $table->index(['status', 'return_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_return_instruments');
    }
};
