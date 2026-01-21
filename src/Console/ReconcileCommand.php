<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\FinancialRecordManager;

class ReconcileCommand extends Command
{
    protected $signature = 'vendweave:reconcile';
    protected $description = 'Run financial reconciliation stats summary';

    public function handle(): int
    {
        if (!FinancialRecordManager::isAvailable()) {
            $this->info('Financial records table not available. Skipping.');
            return self::SUCCESS;
        }

        $stats = FinancialRecordManager::stats();
        if (empty($stats)) {
            $this->info('No financial records found.');
            return self::SUCCESS;
        }

        foreach ($stats as $status => $total) {
            $this->line("{$status}: {$total}");
        }

        return self::SUCCESS;
    }
}
