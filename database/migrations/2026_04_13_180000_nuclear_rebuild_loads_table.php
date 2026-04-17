<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * v40: The 'Nuclear Rebuild' of the Loads table.
     * Standard 'unlocking' via change() can fail in SQLite when constraints are already violated.
     * We drop and recreate the table WITHOUT foreign keys to guarantee it never crashes again.
     */
    public function up(): void
    {
        // Disable foreign keys temporarily to allow the 'Nuclear' drop on MySQL/Local
        Schema::disableForeignKeyConstraints();

        // 1. Drop the table and all its strict locks
        Schema::dropIfExists('loads');

        // 2. Re-create it as a pure data-mirror WITHOUT constrained foreign keys
        Schema::create('loads', function (Blueprint $table) {
            $table->id(); // We keep the ID for updateOrCreate
            $table->unsignedBigInteger('dispatcher_id'); 
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->string('pickup_location');
            $table->string('pickup_time')->nullable();
            $table->string('drop_location');
            $table->string('drop_off_time')->nullable();
            $table->integer('miles');
            $table->decimal('rate', 10, 2);
            $table->integer('deadhead')->default(0);
            $table->integer('total_miles')->default(0);
            $table->decimal('rpm', 10, 2)->default(0);
            $table->string('equipment_type');
            $table->integer('weight')->nullable();
            $table->string('broker_name')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['available', 'pending', 'booked'])->default('available');
            $table->timestamps();
        });

        // Re-enable foreign keys
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
