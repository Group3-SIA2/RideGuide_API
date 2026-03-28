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
        Schema::create('vehicle', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id')->notNull();
            $table->uuid('vehicle_type_id')->notNull();
            $table->uuid('plate_number_id')->notNull();
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('driver_id');
            $table->index('vehicle_type_id');
            $table->index('plate_number_id');
            $table->index(['status', 'verification_status']);
            $table->index('deleted_at');

            $table->foreign('driver_id')->references('id')->on('driver')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
            $table->foreign('plate_number_id')->references('id')->on('plate_number')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle');
    }
};
