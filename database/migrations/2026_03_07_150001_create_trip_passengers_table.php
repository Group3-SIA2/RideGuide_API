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
            $table->timestamps();

            $table->foreign('commuter_id')->references('id')->on('commuter')->onDelete('cascade');
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
