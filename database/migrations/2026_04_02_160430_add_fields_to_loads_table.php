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
        Schema::table('loads', function (Blueprint $table) {
            $table->string('pickup_time')->nullable()->after('pickup_location');
            $table->string('drop_off_time')->nullable()->after('drop_location');
            $table->integer('deadhead')->nullable()->after('drop_off_time');
            $table->integer('total_miles')->nullable()->after('miles');
            $table->decimal('rpm', 10, 2)->nullable()->after('rate');
            $table->integer('weight')->nullable()->after('equipment_type');
            $table->string('broker_name')->nullable()->after('weight');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loads', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_time',
                'drop_off_time',
                'deadhead',
                'total_miles',
                'rpm',
                'weight',
                'broker_name'
            ]);
        });
    }
};
