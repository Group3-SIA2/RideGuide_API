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
        Schema::create('ride_requests', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Foreign keys
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('commuter_ride_request_id')->constrained('commuter_ride_requests')->onDelete('cascade');

            // Status column
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed'])->default('pending');

            // Response timestamp
            $table->timestamp('responded_at')->nullable();

            // Timestamps
            $table->timestamps();

            // Soft deletes for project pattern consistency
            $table->softDeletes();

            // Indexes for efficient filtering
            $table->index(['driver_id', 'status'], 'idx_driver_status');
            $table->index('commuter_ride_request_id', 'idx_commuter_request');
            $table->index('responded_at', 'idx_responded_at');
            $table->index(['status', 'deleted_at'], 'idx_status_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
