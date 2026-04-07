<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table): void {
            $table->uuid('passenger_start_id')->nullable()->change();
            $table->uuid('passenger_stop_id')->nullable()->change();
            $table->decimal('fare', 8, 2)->nullable()->change();

            $table->uuid('destination_terminal_id')->nullable()->after('passenger_stop_id');
            $table->decimal('destination_latitude', 10, 7)->nullable()->after('destination_terminal_id');
            $table->decimal('destination_longitude', 10, 7)->nullable()->after('destination_latitude');
            $table->string('status')->default('joined')->after('destination_longitude');
            $table->timestamp('joined_at')->nullable()->after('status');
            $table->timestamp('picked_up_at')->nullable()->after('joined_at');
            $table->timestamp('dropped_off_at')->nullable()->after('picked_up_at');

            $table->index('destination_terminal_id');
            $table->index('status');
            $table->index(['trip_id', 'status']);

            $table->foreign('destination_terminal_id')->references('id')->on('terminals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table): void {
            $table->dropForeign(['destination_terminal_id']);
            $table->dropIndex(['destination_terminal_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['trip_id', 'status']);

            $table->dropColumn([
                'destination_terminal_id',
                'destination_latitude',
                'destination_longitude',
                'status',
                'joined_at',
                'picked_up_at',
                'dropped_off_at',
            ]);

            $table->uuid('passenger_start_id')->nullable(false)->change();
            $table->uuid('passenger_stop_id')->nullable(false)->change();
            $table->decimal('fare', 8, 2)->nullable(false)->change();
        });
    }
};
