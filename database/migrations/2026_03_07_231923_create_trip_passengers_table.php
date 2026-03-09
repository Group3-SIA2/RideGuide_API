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
        Schema::create('trip_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('commuter_id')->notNull();
            $table->uuid('trip_id')->notNull();
            $table->uuid('passenger_start_id')->notNull();
            $table->uuid('passenger_stop_id')->notNull();
            $table->decimal('fare', 8, 2)->notNull(); // I calculate ang trip and waypoint tapos store dri

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('commuter_id')->references('id')->on('commuter')->onDelete('cascade');
            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('passenger_start_id')->references('id')->on('passenger_start')->onDelete('cascade');
            $table->foreign('passenger_stop_id')->references('id')->on('passenger_stops')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_passengers');
    }
};
