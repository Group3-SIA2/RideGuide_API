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
        Schema::create('hq_address', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('barangay')->notNull();
            $table->string('street')->notNull();
            $table->string('subdivision')->nullable();
            $table->string('floor_unit_room')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['barangay', 'street']);
            $table->index('deleted_at');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hq_address');
    }
};
