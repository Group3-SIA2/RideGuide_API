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
        Schema::create('drv_assign_term', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id')->notNull();
            $table->uuid('terminal_id')->notNull();
            $table->timestamps();
            $table->softDeletes();

            $table->index('driver_id');
            $table->index('terminal_id');
            $table->index(['driver_id', 'terminal_id']);
            $table->index('deleted_at');

            $table->foreign('driver_id')->references('id')->on('driver')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drv_assign_term');
    }
};
