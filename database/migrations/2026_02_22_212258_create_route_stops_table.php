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
        Schema::create('route_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('route_id')->notNull();
            $table->string('stop_name')->notNull();
            $table->decimal('latitude', 10, 8)->notNull();
            $table->decimal('longitude', 11, 8)->notNull();
            $table->integer('stop_order')->notNull();

            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
