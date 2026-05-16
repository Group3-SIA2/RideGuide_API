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
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('heading')->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('updated_at');

            $table->index('updated_at');
            
            // Spatial index would require explicit syntax - adding regular indexes for now
            $table->index(['latitude', 'longitude']);

            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_locations');
    }
};
