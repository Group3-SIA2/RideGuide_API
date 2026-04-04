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
            $table->uuid('origin_terminal_id')->nullable();
            $table->uuid('destination_terminal_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('fare_rate_id');
            $table->index('origin_terminal_id');
            $table->index('destination_terminal_id');
            $table->index(['organization_id', 'fare_rate_id']);
            $table->index('deleted_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('fare_rate_id')->references('id')->on('fare_rate')->onDelete('cascade');
            $table->foreign('origin_terminal_id')->references('id')->on('terminals')->onDelete('set null');
            $table->foreign('destination_terminal_id')->references('id')->on('terminals')->onDelete('set null');
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
