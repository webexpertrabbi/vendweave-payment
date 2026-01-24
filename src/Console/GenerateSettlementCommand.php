<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\SettlementEngine;

class GenerateSettlementCommand extends Command
{
    protected $signature = 'vendweave:generate-settlement {--date=} {--store_slug=} {--gateway=}';
    protected $description = 'Generate a settlement for financial records';

    public function handle(): int
    {
        if (!SettlementEngine::isAvailable()) {
            $this->info('Settlement tables not available. Skipping.');
            return self::SUCCESS;
        }

        $settlement = SettlementEngine::generateSettlement([
            'date' => $this->option('date'),
            'store_slug' => $this->option('store_slug'),
            'gateway' => $this->option('gateway'),
        ]);

        if (!$settlement) {
            $this->info('No eligible records found.');
            return self::SUCCESS;
        }

        $this->info('Settlement generated: ' . ($settlement['settlement_id'] ?? 'unknown'));

        return self::SUCCESS;
    }
}
