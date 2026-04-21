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
        if (Schema::hasTable('loads') && !Schema::hasColumn('loads', 'dispatcher_phone')) {
            Schema::table('loads', function (Blueprint $table) {
                $table->string('dispatcher_phone')->nullable()->after('dispatcher_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loads', function (Blueprint $table) {
            $table->dropColumn('dispatcher_phone');
        });
    }
};
