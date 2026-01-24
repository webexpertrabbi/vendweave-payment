<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\ReferenceGovernor;

class ExpireReferencesCommand extends Command
{
    protected $signature = 'vendweave:expire-references';
    protected $description = 'Expire overdue VendWeave references';

    public function handle(): int
    {
        if (!ReferenceGovernor::isAvailable()) {
            $this->info('Reference governance table not available. Skipping.');
            return self::SUCCESS;
        }

        $count = ReferenceGovernor::expireOverdue();
        $this->info("Expired {$count} reference(s).");

        return self::SUCCESS;
    }
}
