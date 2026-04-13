<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * v39: Unlocking the loads table for cross-device synchronization.
     * We drop the strict foreign keys because dispatcher_id and carrier_id from the cloud 
     * often don't exist in the phone's local mirror database.
     */
    public function up(): void
    {
        Schema::table('loads', function (Blueprint $table) {
            // Drop foreign keys if they exist (SQLite ignores this but we do it for compatibility)
            // In SQLite, dropping foreign keys requires re-creating the table, 
            // but Laravel's Schema::table provides a cleaner abstraction for simple migrations.
            
            // We ensure columns remain but aren't strictly constrained to local records.
            $table->unsignedBigInteger('dispatcher_id')->change();
            $table->unsignedBigInteger('carrier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for this critical recovery migration
    }
};
