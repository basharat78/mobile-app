<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * v43: Nuclear Rebuild of the Load Requests table.
     * Since the 'loads' table was rebuilt in v40, the references in 'load_requests' 
     * are now pointing to ghost indices. We drop and recreate it WITHOUT constraints
     * to unlock the marketplace.
     */
    public function up(): void
    {
        Schema::dropIfExists('load_requests');

        Schema::create('load_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('load_id'); // Pure ID mapping
            $table->unsignedBigInteger('carrier_id'); // Pure ID mapping
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
