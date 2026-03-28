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
        Schema::create('license_id', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('license_id')->notNull()->unique();
            $table->uuid('image_id')->notNull();
            $table->enum('verification_status', ['unverified', 'verified', 'rejected'])->notNull()->default('unverified');
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('image_id');
            $table->index('verification_status');
            $table->index('deleted_at');

            $table->foreign('image_id')->references('id')->on('license_image')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_id');
    }
};
