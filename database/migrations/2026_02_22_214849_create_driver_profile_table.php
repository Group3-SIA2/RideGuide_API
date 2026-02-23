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
        Schema::create('driver_profile', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->notNull()->unique();
            $table->string('license_number')->notNull()->unique();
            $table->string('franchise_number')->notNull()->unique();
            $table->enum('verification_status', ['unverified', 'verified', 'rejected'])->notNull()->default('unverified');
            $table->softDeletes();


            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profile');
    }
};
