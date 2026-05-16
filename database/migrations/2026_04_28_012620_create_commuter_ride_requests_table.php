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
        Schema::create('commuter_ride_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commuter_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('route_id')->nullable()->constrained('routes')->onDelete('cascade');
            $table->foreignUuid('terminal_id')->nullable()->constrained('terminals')->onDelete('cascade');
            $table->string('destination')->notNull();
            $table->decimal('pickup_latitude',  10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
            $table->enum('status', ['active', 'accepted', 'rejected', 'completed', 'cancelled'])->default('active');
            $table->timestamp('expires_at')->notNull();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient filtering
            $table->index(['commuter_id', 'status']);
            $table->index('expires_at');
            $table->index(['status', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commuter_ride_requests');
    }
};
