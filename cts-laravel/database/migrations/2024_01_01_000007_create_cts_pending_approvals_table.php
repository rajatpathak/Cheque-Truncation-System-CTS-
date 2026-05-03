<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_pending_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maker_id');
            $table->string('maker_name', 100);
            $table->string('branch_code', 20)->nullable();
            $table->string('module', 50);
            $table->string('action', 200);
            $table->text('payload');                        // encrypted JSON of request body
            $table->string('route', 200);
            $table->string('method', 10);
            $table->string('status', 20)->default('PENDING'); // PENDING|APPROVED|REJECTED|EXPIRED
            $table->unsignedTinyInteger('checker_level')->default(1);
            $table->unsignedBigInteger('checker1_id')->nullable();
            $table->timestamp('checker1_at')->nullable();
            $table->text('checker1_remarks')->nullable();
            $table->unsignedBigInteger('checker2_id')->nullable();
            $table->timestamp('checker2_at')->nullable();
            $table->text('checker2_remarks')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['maker_id', 'status']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_pending_approvals');
    }
};
