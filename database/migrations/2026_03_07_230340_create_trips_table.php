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
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id')->notNull();
            $table->uuid('trip_passenger_id')->notNull();
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('driver')->onDelete('cascade');
            $table->foreign('trip_passenger_id')->references('id')->on('trip_passengers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
