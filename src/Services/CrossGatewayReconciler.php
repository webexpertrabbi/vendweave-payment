<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CrossGatewayReconciler
{
    public static function isAvailable(): bool
    {
        if (!config('vendweave.financial_reconciliation.enabled', true)) {
            return false;
        }

        try {
            return Schema::hasTable(FinancialRecordManager::TABLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function reconcileOrder(string $orderId): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $records = DB::table(FinancialRecordManager::TABLE)
                ->where('order_id', $orderId)
                ->get();

            if ($records->isEmpty()) {
                return null;
            }

            $useNormalized = Schema::hasColumn(FinancialRecordManager::TABLE, 'normalized_amount');
            $baseCurrency = $records->first()->base_currency ?? config('vendweave.base_currency', 'USD');

            $totalsByGateway = [];
            foreach ($records as $record) {
                $gateway = $record->gateway ?? 'unknown';
                $amount = $useNormalized
                    ? (float) ($record->normalized_amount ?? $record->amount_paid)
                    : (float) $record->amount_paid;

                $totalsByGateway[$gateway] = ($totalsByGateway[$gateway] ?? 0) + $amount;
            }

            $total = array_sum($totalsByGateway);

            return [
                'order_id' => $orderId,
                'base_currency' => $baseCurrency,
                'total_paid' => $total,
                'by_gateway' => $totalsByGateway,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }
}
