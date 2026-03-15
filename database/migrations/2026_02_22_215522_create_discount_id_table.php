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
        Schema::create('discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ID_number')->notNull()->unique();
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->uuid('ID_image_id')->notNull();
            $table->uuid('classification_type_id')->nullable();

            $table->foreign('ID_image_id')->references('id')->on('discount_img')->onDelete('cascade');
            $table->foreign('classification_type_id')->references('id')->on('commuter_classification_types')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
