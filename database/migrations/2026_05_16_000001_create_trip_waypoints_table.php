<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_waypoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->uuid('waypoint_id');
            $table->unsignedInteger('sequence')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['trip_id', 'sequence']);
            $table->index('waypoint_id');
            $table->index('deleted_at');

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('waypoint_id')->references('id')->on('waypoint')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_waypoints');
    }
};
