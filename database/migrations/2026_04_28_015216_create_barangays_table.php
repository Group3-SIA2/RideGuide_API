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
        Schema::create('barangays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('center_latitude', 8, 6);
            $table->decimal('center_longitude', 8, 6);
            $table->decimal('north_latitude', 8, 6);
            $table->decimal('south_latitude', 8, 6);
            $table->decimal('east_longitude', 8, 6);
            $table->decimal('west_longitude', 8, 6);
            $table->timestamps();

            $table->index('name');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
