<?php

namespace VendWeave\Gateway\Console\Commands;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\ReferenceGovernor;

/**
 * Expire overdue payment references.
 * 
 * This command should be scheduled to run every 5 minutes
 * to automatically expire references that have exceeded their TTL.
 * 
 * Usage:
 *   php artisan vendweave:expire-references
 * 
 * Scheduling (in app/Console/Kernel.php):
 *   $schedule->command('vendweave:expire-references')->everyFiveMinutes();
 */
class ExpireReferencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendweave:expire-references';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire overdue VendWeave payment references';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!ReferenceGovernor::isEnabled()) {
            $this->warn('VendWeave Reference Governance is not enabled (table missing).');
            return self::SUCCESS;
        }

        $this->info('Expiring overdue VendWeave references...');

        $count = ReferenceGovernor::expireOverdue();

        if ($count > 0) {
            $this->info("Expired {$count} reference(s).");
        } else {
            $this->info('No overdue references found.');
        }

        return self::SUCCESS;
    }
}
