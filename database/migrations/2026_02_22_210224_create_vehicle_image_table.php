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
        Schema::create('vehicle_image', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('image_front')->notNull();
            $table->string('image_back')->nullable();
            $table->string('image_left')->nullable();
            $table->string('image_right')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_image');
    }
};
