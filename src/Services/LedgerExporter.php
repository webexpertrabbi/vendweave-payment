<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LedgerExporter
{
    public const TABLE = 'vendweave_ledger_exports';

    public static function isAvailable(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            return Schema::hasTable(self::TABLE) && Schema::hasTable(FinancialRecordManager::TABLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function exportCSV(array $filters = []): ?string
    {
        $rows = self::getExportRows($filters);
        if ($rows === null) {
            return null;
        }

        $header = array_keys($rows[0] ?? []);
        $lines = [];
        $lines[] = implode(',', $header);

        foreach ($rows as $row) {
            $escaped = array_map(function ($value) {
                $value = (string) $value;
                $value = str_replace('"', '""', $value);
                return '"' . $value . '"';
            }, array_values($row));
            $lines[] = implode(',', $escaped);
        }

        $export = implode("\n", $lines);
        self::recordExport('csv', $filters, count($rows));

        return $export;
    }

    public static function exportExcel(array $filters = []): ?string
    {
        // Keep dependency-free: return CSV formatted content for Excel compatibility
        return self::exportCSV($filters);
    }

    public static function exportJSON(array $filters = []): ?string
    {
        $rows = self::getExportRows($filters);
        if ($rows === null) {
            return null;
        }

        self::recordExport('json', $filters, count($rows));
        return json_encode($rows, JSON_PRETTY_PRINT);
    }

    public static function exportAccountingFormat(array $filters = []): ?string
    {
        $rows = self::getExportRows($filters);
        if ($rows === null) {
            return null;
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode('|', [
                $row['reference'],
                $row['order_id'],
                $row['store_slug'],
                $row['amount_expected'],
                $row['amount_paid'],
                $row['currency'] ?? null,
                $row['base_currency'] ?? null,
                $row['exchange_rate'] ?? null,
                $row['normalized_amount'] ?? null,
                $row['status'],
                $row['gateway'],
                $row['trx_id'],
                $row['settlement_id'],
                $row['created_at'],
                $row['confirmed_at'],
            ]);
        }

        self::recordExport('accounting', $filters, count($rows));

        return implode("\n", $lines);
    }

    private static function getExportRows(array $filters = []): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $query = DB::table(FinancialRecordManager::TABLE);

            if (!empty($filters['settlement_id'])) {
                $query->where('settlement_id', $filters['settlement_id']);
            }

            if (!empty($filters['store_slug'])) {
                $query->where('store_slug', $filters['store_slug']);
            }

            if (!empty($filters['gateway'])) {
                $query->where('gateway', $filters['gateway']);
            }

            if (!empty($filters['date'])) {
                $query->whereDate('created_at', $filters['date']);
            }

            $columns = [
                'reference',
                'order_id',
                'store_slug',
                'amount_expected',
                'amount_paid',
            ];

            if (Schema::hasColumn(FinancialRecordManager::TABLE, 'currency')) {
                $columns[] = 'currency';
            }
            if (Schema::hasColumn(FinancialRecordManager::TABLE, 'base_currency')) {
                $columns[] = 'base_currency';
            }
            if (Schema::hasColumn(FinancialRecordManager::TABLE, 'exchange_rate')) {
                $columns[] = 'exchange_rate';
            }
            if (Schema::hasColumn(FinancialRecordManager::TABLE, 'normalized_amount')) {
                $columns[] = 'normalized_amount';
            }

            $columns = array_merge($columns, [
                'status',
                'gateway',
                'trx_id',
                'settlement_id',
                'created_at',
                'confirmed_at',
            ]);

            $records = $query->get($columns);

            return $records->map(fn ($row) => (array) $row)->toArray();
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function recordExport(string $format, array $filters, int $count): void
    {
        if (!self::isAvailable()) {
            return;
        }

        try {
            $recordsQuery = DB::table(FinancialRecordManager::TABLE);

            if (!empty($filters['settlement_id'])) {
                $recordsQuery->where('settlement_id', $filters['settlement_id']);
            }

            if (!empty($filters['store_slug'])) {
                $recordsQuery->where('store_slug', $filters['store_slug']);
            }

            if (!empty($filters['gateway'])) {
                $recordsQuery->where('gateway', $filters['gateway']);
            }

            if (!empty($filters['date'])) {
                $recordsQuery->whereDate('created_at', $filters['date']);
            }

            if (Schema::hasColumn(FinancialRecordManager::TABLE, 'ledger_exported')) {
                $recordsQuery->update([
                    'ledger_exported' => true,
                    'updated_at' => now(),
                ]);
            }

            DB::table(self::TABLE)->insert([
                'export_format' => $format,
                'filters' => json_encode($filters),
                'record_count' => $count,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::log('info', '[VendWeave] Ledger export generated', [
                'reference' => null,
                'financial_status' => null,
                'amount_expected' => null,
                'amount_paid' => null,
                'settlement_id' => $filters['settlement_id'] ?? null,
                'ledger_exported' => true,
            ]);
        } catch (Throwable $e) {
            // swallow
        }
    }

    private static function isEnabled(): bool
    {
        return (bool) config('vendweave.financial_reconciliation.enabled', true);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        if (!class_exists(self::class)) {
            return;
        }

        if (!config('vendweave.logging.enabled', true)) {
            return;
        }

        try {
            Log::channel(config('vendweave.logging.channel', 'stack'))
                ->log($level, $message, $context);
        } catch (Throwable $e) {
            // Swallow logging failures to keep package safe
        }
    }
}
