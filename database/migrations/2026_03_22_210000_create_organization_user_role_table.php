<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_user_role', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('invited_by_user_id')->nullable();
            $table->enum('status', ['active', 'pending', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['organization_id', 'user_id', 'role_id'], 'org_user_role_unique');
            $table->index(['organization_id', 'user_id', 'status'], 'org_user_status_idx');
            $table->index('invited_by_user_id', 'org_user_invited_by_idx');
            $table->index('deleted_at', 'org_user_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user_role');
    }
};