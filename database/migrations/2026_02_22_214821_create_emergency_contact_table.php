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
        Schema::create('emergency_contact', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contact_name')->notNull();
            $table->string('contact_phone_number')->notNull();
            $table->string('contact_relationship')->notNull();

            $table->timestamps();
            $table->softDeletes();

            $table->index('contact_phone_number');
            $table->index('deleted_at');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_contact');
    }
};
