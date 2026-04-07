<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->uuid('start_terminal_id')->nullable()->after('driver_id');
            $table->unsignedInteger('capacity')->default(1)->after('departure_time');
            $table->string('status')->default('scheduled')->after('capacity');
            $table->uuid('trip_start_waypoint_id')->nullable()->after('status');
            $table->uuid('trip_end_waypoint_id')->nullable()->after('trip_start_waypoint_id');

            $table->index('start_terminal_id');
            $table->index('status');
            $table->index('trip_start_waypoint_id');
            $table->index('trip_end_waypoint_id');

            $table->foreign('start_terminal_id')->references('id')->on('terminals')->nullOnDelete();
            $table->foreign('trip_start_waypoint_id')->references('id')->on('waypoint')->nullOnDelete();
            $table->foreign('trip_end_waypoint_id')->references('id')->on('waypoint')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropForeign(['start_terminal_id']);
            $table->dropForeign(['trip_start_waypoint_id']);
            $table->dropForeign(['trip_end_waypoint_id']);

            $table->dropIndex(['start_terminal_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['trip_start_waypoint_id']);
            $table->dropIndex(['trip_end_waypoint_id']);

            $table->dropColumn([
                'start_terminal_id',
                'capacity',
                'status',
                'trip_start_waypoint_id',
                'trip_end_waypoint_id',
            ]);
        });
    }
};
