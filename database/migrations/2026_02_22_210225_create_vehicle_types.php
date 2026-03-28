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
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vehicle_type')->notNull();
            $table->string('description')->nullable();
            $table->uuid('image_id')->notNull();

            $table->timestamps();
            $table->softDeletes();

            $table->index('image_id');
            $table->index('deleted_at');

            $table->foreign('image_id')->references('id')->on('vehicle_image')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
