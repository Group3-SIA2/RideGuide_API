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
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->uuid('organization_type_id')->nullable();
            $table->uuid('owner_user_id')->nullable();
            $table->uuid('hq_address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at'], 'orgs_status_deleted_idx');
            $table->index(['organization_type_id', 'status', 'deleted_at'], 'orgs_type_status_deleted_idx');
            $table->index(['owner_user_id', 'deleted_at'], 'orgs_owner_deleted_idx');

            $table->foreign('organization_type_id')->references('id')->on('organization_types')->nullOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('hq_address')->references('id')->on('hq_address')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
