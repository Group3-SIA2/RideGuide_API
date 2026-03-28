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
        Schema::create('passenger_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('waypoint_id')->notNull();
            $table->timestamps();
            $table->softDeletes();

            $table->index('waypoint_id');
            $table->index('deleted_at');

            $table->foreign('waypoint_id')->references('id')->on('waypoint')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passenger_stops');
    }
};
