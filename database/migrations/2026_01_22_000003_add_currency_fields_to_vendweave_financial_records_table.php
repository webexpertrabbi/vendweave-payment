<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendweave_financial_records')) {
            return;
        }

        Schema::table('vendweave_financial_records', function (Blueprint $table) {
            if (!Schema::hasColumn('vendweave_financial_records', 'currency')) {
                $table->string('currency')->nullable();
            }
            if (!Schema::hasColumn('vendweave_financial_records', 'base_currency')) {
                $table->string('base_currency')->nullable();
            }
            if (!Schema::hasColumn('vendweave_financial_records', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->nullable();
            }
            if (!Schema::hasColumn('vendweave_financial_records', 'normalized_amount')) {
                $table->decimal('normalized_amount', 18, 8)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vendweave_financial_records')) {
            return;
        }

        Schema::table('vendweave_financial_records', function (Blueprint $table) {
            if (Schema::hasColumn('vendweave_financial_records', 'normalized_amount')) {
                $table->dropColumn('normalized_amount');
            }
            if (Schema::hasColumn('vendweave_financial_records', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
            if (Schema::hasColumn('vendweave_financial_records', 'base_currency')) {
                $table->dropColumn('base_currency');
            }
            if (Schema::hasColumn('vendweave_financial_records', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
