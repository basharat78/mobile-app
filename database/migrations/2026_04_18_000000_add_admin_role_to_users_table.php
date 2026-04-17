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
        // For MySQL, we use a raw statement to ensure the ENUM is updated correctly
        // without needing doctrine/dbal or dealing with 'change()' limitations on ENUMs.
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('dispatcher', 'carrier', 'admin') DEFAULT 'carrier'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('dispatcher', 'carrier') DEFAULT 'carrier'");
    }
};
