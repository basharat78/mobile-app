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
        if (Schema::hasTable('carriers') && !Schema::hasColumn('carriers', 'background_sync_enabled')) {
            Schema::table('carriers', function (Blueprint $table) {
                $table->boolean('background_sync_enabled')->default(false)->after('status');
            });
        }

        if (Schema::hasTable('loads') && !Schema::hasColumn('loads', 'is_notified')) {
            Schema::table('loads', function (Blueprint $table) {
                $table->boolean('is_notified')->default(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('carriers') && Schema::hasColumn('carriers', 'background_sync_enabled')) {
            Schema::table('carriers', function (Blueprint $table) {
                $table->dropColumn('background_sync_enabled');
            });
        }

        if (Schema::hasTable('loads') && Schema::hasColumn('loads', 'is_notified')) {
            Schema::table('loads', function (Blueprint $table) {
                $table->dropColumn('is_notified');
            });
        }
    }
};
