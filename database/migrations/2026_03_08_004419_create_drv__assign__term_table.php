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
        Schema::create('drv__assign__term', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id')->notNull();
            $table->uuid('terminal_id')->notNull();
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('driver')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drv__assign__term');
    }
};
