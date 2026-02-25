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
        Schema::create('fares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vehicle_id')->notNull();
            $table->uuid('commuter_id')->notNull();
            $table->decimal('base_fare_4KM', 8, 2)->notNull();
            $table->decimal('per_km_rate', 8, 2)->notNull();
            $table->decimal('discounts', 8, 2)->default(0);

            $table->foreign('vehicle_id')->references('id')->on('vehicle_types')->onDelete('cascade');
            $table->foreign('commuter_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fares');
    }
};
