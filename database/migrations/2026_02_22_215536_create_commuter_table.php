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
        Schema::create('commuter', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->notNull()->unique();
            $table->enum('commuter_classification', ['regular', 'student', 'senior', 'PWD'])->notNull()->default('regular');
                
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commuter');
    }
};
