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
        Schema::create('pasenger_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id')->notNull();
            $table->uuid('waypoint_id')->notNull();
            $table->decimal('fare', 8, 2)->notNull(); // I calculate ang trip and waypoint tapos store dri
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('waypoint_id')->references('id')->on('waypoint')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pasenger_stops');
    }
};
