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
        Schema::create('plate_number', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plate_number')->notNull();
            $table->timestamps();
            $table->softDeletes();

            $table->index('plate_number');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plate_number');
    }
};
