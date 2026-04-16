<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Force-reset all notification flags to false to ensure any missed updates trigger in v70
        if (Schema::hasTable('loads')) {
            DB::table('loads')->update(['is_notified' => false]);
        }

        if (Schema::hasTable('load_requests')) {
            DB::table('load_requests')->update(['is_notified' => false]);
        }

        if (Schema::hasTable('carriers')) {
            DB::table('carriers')->update(['is_notified' => false]);
        }

        if (Schema::hasTable('carrier_documents')) {
            DB::table('carrier_documents')->update(['is_notified' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
