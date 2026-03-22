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
        Schema::create('driver_organization_assignment_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id');
            $table->uuid('old_organization_id')->nullable();
            $table->uuid('new_organization_id')->nullable();
            $table->uuid('acted_by_user_id')->nullable();
            $table->enum('action', ['assign', 'reassign', 'unassign']);
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('driver')->cascadeOnDelete();
            $table->foreign('old_organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('new_organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('acted_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['driver_id', 'created_at'], 'doal_driver_created_idx');
            $table->index(['old_organization_id', 'new_organization_id'], 'doal_old_new_org_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_organization_assignment_logs');
    }
};
