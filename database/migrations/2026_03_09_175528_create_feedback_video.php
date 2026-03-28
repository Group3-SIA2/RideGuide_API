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
        Schema::create('feedback_video', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('feedback_id')->notNull();
            $table->string('video')->notNull();
            $table->softDeletes();
            $table->timestamps();

            $table->index('feedback_id');
            $table->index('deleted_at');

            $table->foreign('feedback_id')->references('id')->on('feedback')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_video');
    }
};
