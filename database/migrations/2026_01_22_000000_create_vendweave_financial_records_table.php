<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendweave_financial_records')) {
            Schema::create('vendweave_financial_records', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference')->unique();
                $table->string('order_id');
                $table->string('store_slug')->nullable();
                $table->decimal('amount_expected', 12, 2)->default(0);
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->string('status')->index();
                $table->string('gateway')->nullable();
                $table->string('trx_id')->nullable();
                $table->string('settlement_id')->nullable()->index();
                $table->boolean('ledger_exported')->default(false);
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendweave_financial_records');
    }
};
