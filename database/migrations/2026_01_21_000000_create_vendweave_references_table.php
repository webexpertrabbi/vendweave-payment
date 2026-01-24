<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendweave_references')) {
            Schema::create('vendweave_references', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference')->unique();
                $table->string('order_id');
                $table->string('store_id')->nullable();
                $table->string('status')->index();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->unsignedInteger('replay_count')->default(0);
                $table->timestamps();

                $table->index(['order_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendweave_references');
    }
};
