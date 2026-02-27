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
        Schema::create('driver_route', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id')->notNull();
            $table->uuid('route_id')->notNull();
            $table->enum('status', ['active', 'inactive'])->notNull()->default('active');

            $table->foreign('driver_id')->references('id')->on('driver')->onDelete('cascade');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_route');
    }
};
