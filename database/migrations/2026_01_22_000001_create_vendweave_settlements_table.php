<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendweave_settlements')) {
            Schema::create('vendweave_settlements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('settlement_id')->unique();
                $table->string('store_slug')->nullable();
                $table->string('gateway')->nullable();
                $table->date('date');
                $table->decimal('total_expected', 12, 2)->default(0);
                $table->decimal('total_paid', 12, 2)->default(0);
                $table->unsignedInteger('record_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendweave_settlements');
    }
};
