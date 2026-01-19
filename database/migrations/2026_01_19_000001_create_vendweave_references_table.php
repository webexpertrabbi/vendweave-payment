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
        Schema::create('vendweave_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('order_id');
            $table->string('reference', 6);
            $table->enum('status', ['reserved', 'matched', 'expired', 'replayed', 'cancelled'])->default('reserved');
            $table->timestamp('expires_at');
            $table->timestamp('matched_at')->nullable();
            $table->integer('replay_count')->default(0);
            $table->timestamps();

            // Indexes for fast lookup
            $table->index(['store_id', 'reference']);
            $table->index(['reference', 'status']);
            $table->index('expires_at');
            
            // Unique constraint: one reference per store at a time
            $table->unique(['store_id', 'reference', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendweave_references');
    }
};
