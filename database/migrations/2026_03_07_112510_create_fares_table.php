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
        Schema::create('fare_rate', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('base_fare_4KM', 8, 2)->notNull();
            $table->decimal('per_km_rate', 8, 2)->notNull();
            $table->date('effective_date')->notNull();

            $table->timestamps();
            $table->softDeletes();

            $table->index('effective_date');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fare_rate');
    }
};
