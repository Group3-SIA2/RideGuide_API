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
        Schema::create('org_fare_rate', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->notNull();
            $table->uuid('fare_rate_id')->notNull();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('fare_rate_id');
            $table->index(['organization_id', 'fare_rate_id']);
            $table->index('deleted_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('fare_rate_id')->references('id')->on('fare_rate')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_fare_rate');
    }
};
