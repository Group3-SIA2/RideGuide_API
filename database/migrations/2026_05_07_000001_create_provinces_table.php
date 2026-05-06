<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('code', 10)->nullable()->unique();
            $table->string('region')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
