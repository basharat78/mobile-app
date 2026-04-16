<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Carriers (Account Status)
        if (Schema::hasTable('carriers') && !Schema::hasColumn('carriers', 'is_notified')) {
            Schema::table('carriers', function (Blueprint $table) {
                $table->boolean('is_notified')->default(false)->after('status');
            });
            // Backfill existing to true so they don't trigger alerts
            DB::table('carriers')->update(['is_notified' => true]);
        }

        // 2. Load Requests (Bid Status)
        if (Schema::hasTable('load_requests') && !Schema::hasColumn('load_requests', 'is_notified')) {
            Schema::table('load_requests', function (Blueprint $table) {
                $table->boolean('is_notified')->default(false)->after('status');
            });
            // Backfill existing to true
            DB::table('load_requests')->update(['is_notified' => true]);
        }

        // 3. Carrier Documents (Document Status)
        if (Schema::hasTable('carrier_documents') && !Schema::hasColumn('carrier_documents', 'is_notified')) {
            Schema::table('carrier_documents', function (Blueprint $table) {
                $table->boolean('is_notified')->default(false)->after('status');
            });
            // Backfill existing to true
            DB::table('carrier_documents')->update(['is_notified' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropColumn('is_notified');
        });
        Schema::table('load_requests', function (Blueprint $table) {
            $table->dropColumn('is_notified');
        });
        Schema::table('carrier_documents', function (Blueprint $table) {
            $table->dropColumn('is_notified');
        });
    }
};
