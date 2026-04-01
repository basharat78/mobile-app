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
        Schema::create('loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->onDelete('cascade');
            $table->string('pickup_location');
            $table->string('drop_location');
            $table->integer('miles');
            $table->decimal('rate', 10, 2);
            $table->string('equipment_type');
            $table->text('notes')->nullable();
            $table->enum('status', ['available', 'pending', 'booked'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loads');
    }
};
