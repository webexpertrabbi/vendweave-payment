<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SettlementEngine
{
    public const TABLE = 'vendweave_settlements';

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

    public static function groupByDate(string $date): array
    {
        return self::groupBy('date', $date);
    }

    public static function groupByStore(string $storeSlug): array
    {
        return self::groupBy('store_slug', $storeSlug);
    }

    public static function groupByGateway(string $gateway): array
    {
        return self::groupBy('gateway', $gateway);
    }

    public static function generateSettlement(array $filters = []): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $query = DB::table(FinancialRecordManager::TABLE)
                ->whereNull('settlement_id')
                ->whereIn('status', [
                    FinancialRecordManager::STATUS_CONFIRMED,
                    FinancialRecordManager::STATUS_PARTIAL,
                    FinancialRecordManager::STATUS_OVERPAID,
                ]);

            if (!empty($filters['store_slug'])) {
                $query->where('store_slug', $filters['store_slug']);
            }

            if (!empty($filters['gateway'])) {
                $query->where('gateway', $filters['gateway']);
            }

            if (!empty($filters['date'])) {
                $query->whereDate('created_at', $filters['date']);
            }

            $records = $query->get();
            if ($records->isEmpty()) {
                return null;
            }

            $settlementId = $filters['settlement_id'] ?? self::generateSettlementId($filters['date'] ?? null);

            $useNormalization = Schema::hasColumn(FinancialRecordManager::TABLE, 'normalized_amount')
                && Schema::hasColumn(FinancialRecordManager::TABLE, 'exchange_rate');

            $totalExpected = $useNormalization
                ? $records->sum(fn ($record) => (float) $record->amount_expected * (float) ($record->exchange_rate ?? 1))
                : $records->sum('amount_expected');

            $totalPaid = $useNormalization
                ? $records->sum(fn ($record) => (float) ($record->normalized_amount ?? $record->amount_paid))
                : $records->sum('amount_paid');

            DB::table(self::TABLE)->insert([
                'settlement_id' => $settlementId,
                'store_slug' => $filters['store_slug'] ?? null,
                'gateway' => $filters['gateway'] ?? null,
                'date' => $filters['date'] ?? now()->toDateString(),
                'total_expected' => $totalExpected,
                'total_paid' => $totalPaid,
                'record_count' => $records->count(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table(FinancialRecordManager::TABLE)
                ->whereIn('id', $records->pluck('id')->toArray())
                ->update([
                    'settlement_id' => $settlementId,
                    'updated_at' => now(),
                ]);

            self::log('info', '[VendWeave] Settlement generated', [
                'reference' => null,
                'financial_status' => null,
                'amount_expected' => $totalExpected,
                'amount_paid' => $totalPaid,
                'settlement_id' => $settlementId,
                'ledger_exported' => false,
            ]);

            return (array) DB::table(self::TABLE)->where('settlement_id', $settlementId)->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function groupBy(string $field, string $value): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        try {
            return DB::table(FinancialRecordManager::TABLE)
                ->where($field, $value)
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function generateSettlementId(?string $date = null): string
    {
        $suffix = strtoupper(Str::random(6));
        $datePart = $date ? str_replace('-', '', $date) : now()->format('Ymd');
        return "SET-{$datePart}-{$suffix}";
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
