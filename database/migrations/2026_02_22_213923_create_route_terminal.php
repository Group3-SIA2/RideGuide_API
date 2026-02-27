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
        Schema::create('route_terminal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('route_id')->notNull();
            $table->uuid('terminal_id')->notNull();

            $table->integer('stop_order')->default(0)->notNull();
            $table->enum('terminal_role', ['origin', 'waypoint', 'destination'])->default('waypoint');

            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('cascade');

            $table->unique(['route_id', 'terminal_id']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_terminal');
    }
};
