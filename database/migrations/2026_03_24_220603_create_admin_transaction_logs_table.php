<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_transaction_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('actor_user_id')->nullable();
            $table->string('actor_email')->nullable();

            $table->string('module', 50);
            $table->string('transaction_type', 80);

            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 80)->nullable();

            $table->enum('status', ['success', 'failed']);
            $table->string('reason')->nullable();

            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Explicit short index names to avoid MySQL identifier length limits.
            $table->index('created_at', 'atl_created_idx');
            $table->index(['actor_user_id', 'created_at'], 'atl_actor_created_idx');
            $table->index(['module', 'transaction_type', 'created_at'], 'atl_mod_type_created_idx');
            $table->index(['reference_type', 'reference_id', 'created_at'], 'atl_ref_created_idx');
            $table->index(['status', 'created_at'], 'atl_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_transaction_logs');
    }
};