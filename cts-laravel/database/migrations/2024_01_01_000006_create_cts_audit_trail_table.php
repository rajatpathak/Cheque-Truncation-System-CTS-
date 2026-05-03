<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 100)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('action', 200);
            $table->string('module', 50)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('risk_level', 10)->nullable();   // LOW|MEDIUM|HIGH
            $table->timestamp('timestamp')->useCurrent();

            $table->index(['user_id', 'timestamp']);
            $table->index(['module', 'timestamp']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('timestamp');
        });

        // Immutable WORM simulation — audit records cannot be updated or deleted
        // In Oracle: implement with VPD (Virtual Private Database) policy
        // In MySQL: revoke UPDATE/DELETE on this table from app user
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_audit_trail');
    }
};
