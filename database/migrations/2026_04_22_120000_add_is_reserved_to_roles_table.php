<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_reserved')->default(false)->after('description');
            $table->index('is_reserved');
        });

        DB::table('roles')
            ->whereIn('name', Role::RESERVED_NAMES)
            ->update(['is_reserved' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['is_reserved']);
            $table->dropColumn('is_reserved');
        });
    }
};
