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
            // 9,6 supports PH coordinates with 3 integer digits (e.g. 125.xxxxxx).
            $table->decimal('center_latitude', 9, 6);
            $table->decimal('center_longitude', 9, 6);
            $table->decimal('north_latitude', 9, 6);
            $table->decimal('south_latitude', 9, 6);
            $table->decimal('east_longitude', 9, 6);
            $table->decimal('west_longitude', 9, 6);
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
