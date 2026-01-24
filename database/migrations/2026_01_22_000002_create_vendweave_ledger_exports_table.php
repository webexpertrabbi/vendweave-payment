<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendweave_ledger_exports')) {
            Schema::create('vendweave_ledger_exports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('export_format');
                $table->text('filters')->nullable();
                $table->unsignedInteger('record_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendweave_ledger_exports');
    }
};
