<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Live GPS sharing for any authenticated role (commuter, organization, etc.).
     * Drivers continue using driver_locations via the existing driver API.
     */
    public function up(): void
    {
        Schema::create('user_live_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('heading')->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('updated_at');

            $table->index('updated_at');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_live_locations');
    }
};
