<?php

namespace VendWeave\Gateway\Console;

use Illuminate\Console\Command;
use VendWeave\Gateway\Services\LedgerExporter;

class ExportLedgerCommand extends Command
{
    protected $signature = 'vendweave:export-ledger {--format=csv} {--date=} {--store_slug=} {--gateway=} {--settlement_id=}';
    protected $description = 'Export ledger entries in CSV/JSON/Accounting formats';

    public function handle(): int
    {
        if (!LedgerExporter::isAvailable()) {
            $this->info('Ledger export tables not available. Skipping.');
            return self::SUCCESS;
        }

        $filters = [
            'date' => $this->option('date'),
            'store_slug' => $this->option('store_slug'),
            'gateway' => $this->option('gateway'),
            'settlement_id' => $this->option('settlement_id'),
        ];

        $format = strtolower((string) $this->option('format'));

        $output = match ($format) {
            'json' => LedgerExporter::exportJSON($filters),
            'accounting' => LedgerExporter::exportAccountingFormat($filters),
            'excel' => LedgerExporter::exportExcel($filters),
            default => LedgerExporter::exportCSV($filters),
        };

        if ($output === null) {
            $this->info('No records to export.');
            return self::SUCCESS;
        }

        $this->line($output);
        return self::SUCCESS;
    }
}
