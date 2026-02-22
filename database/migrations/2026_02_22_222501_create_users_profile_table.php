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
        Schema::create('users_profile', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->notNull()->unique();
            $table->string('first_name')->notNull();
            $table->string('last_name')->notNull();
            $table->string('middle_name')->nullable();
            $table->string('birthdate')->notNull();
            $table->string('gender')->notNull();
            $table->string('image')->nullable();
            $table->string('emergency_contact_name')->notNull();
            $table->string('emergency_contact_number')->notNull();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_profile');
    }
};
