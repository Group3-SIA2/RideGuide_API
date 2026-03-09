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
        Schema::create('passenger_start', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('waypoint_id')->notNull();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('waypoint_id')->references('id')->on('waypoint')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passenger_start');
    }
};
