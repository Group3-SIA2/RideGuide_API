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
        Schema::create('driver_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_assign_id')->notNull();
            $table->string('day_of_week')->notNull();
            $table->time('start_time')->notNull();
            $table->time('end_time')->notNull();
            $table->timestamps();

            $table->foreign('driver_assign_id')->references('id')->on('drv_assign_term')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_schedules');
    }
};
