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
        Schema::create('routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('route_name')->notNull();
            $table->string('route_code')->unique()->notNull();
            $table->string('origin')->notNull();
            $table->string('destination')->notNull();
            $table->uuid('vehicle_id')->notNull();
            $table->string('status', 20)->notNull();
            $table->uuid('create_by')->notNull();

            $table->foreign('vehicle_id')->references('id')->on('vehicle_types')->onDelete('cascade');
            $table->foreign('create_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
