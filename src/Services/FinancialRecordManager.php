<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Throwable;

class FinancialRecordManager
{
    public const TABLE = 'vendweave_financial_records';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_OVERPAID = 'OVERPAID';
    public const STATUS_REFUNDED = 'REFUNDED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public static function isAvailable(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function createFromReference(
        string $reference,
        string $orderId,
        ?string $storeSlug,
        float $amountExpected,
        float $amountPaid,
        string $gateway,
        ?string $trxId = null,
        array $context = []
    ): ?array {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $record = DB::table(self::TABLE)->where('reference', $reference)->first();
            $status = self::determineStatus($amountExpected, $amountPaid, $context);

            $currency = $context['currency'] ?? config('vendweave.base_currency', 'USD');
            $baseCurrency = $context['base_currency'] ?? config('vendweave.base_currency', 'USD');
            $normalized = CurrencyNormalizer::normalize($amountPaid, $currency, $baseCurrency);

            $exchangeRate = $normalized['exchange_rate'] ?? null;
            $normalizedAmount = $normalized['normalized_amount'] ?? null;
            $currencyColumns = self::currencyColumnsAvailable();

            $currencyPayload = [];
            if ($currencyColumns['currency']) {
                $currencyPayload['currency'] = $currency;
            }
            if ($currencyColumns['base_currency']) {
                $currencyPayload['base_currency'] = $baseCurrency;
            }
            if ($currencyColumns['exchange_rate']) {
                $currencyPayload['exchange_rate'] = $exchangeRate;
            }
            if ($currencyColumns['normalized_amount']) {
                $currencyPayload['normalized_amount'] = $normalizedAmount;
            }

            if ($record) {
                DB::table(self::TABLE)->where('reference', $reference)->update(array_merge([
                    'amount_expected' => $amountExpected,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'gateway' => $gateway,
                    'trx_id' => $trxId,
                    'confirmed_at' => $status === self::STATUS_CONFIRMED ? now() : $record->confirmed_at,
                    'updated_at' => now(),
                ], $currencyPayload));
            } else {
                DB::table(self::TABLE)->insert(array_merge([
                    'reference' => $reference,
                    'order_id' => $orderId,
                    'store_slug' => $storeSlug,
                    'amount_expected' => $amountExpected,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'gateway' => $gateway,
                    'trx_id' => $trxId,
                    'settlement_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'confirmed_at' => $status === self::STATUS_CONFIRMED ? now() : null,
                ], $currencyPayload));
            }

            $updated = DB::table(self::TABLE)->where('reference', $reference)->first();

            self::log('info', '[VendWeave] Financial record updated', [
                'reference' => $reference,
                'financial_status' => $status,
                'amount_expected' => $amountExpected,
                'amount_paid' => $amountPaid,
                'settlement_id' => $updated->settlement_id ?? null,
                'ledger_exported' => (bool) ($updated->ledger_exported ?? false),
            ]);

            return (array) $updated;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function markConfirmed(string $reference): ?array
    {
        return self::markStatus($reference, self::STATUS_CONFIRMED);
    }

    public static function markPartial(string $reference): ?array
    {
        return self::markStatus($reference, self::STATUS_PARTIAL);
    }

    public static function markOverpaid(string $reference): ?array
    {
        return self::markStatus($reference, self::STATUS_OVERPAID);
    }

    public static function markRefunded(string $reference): ?array
    {
        return self::markStatus($reference, self::STATUS_REFUNDED);
    }

    public static function cancel(string $reference): ?array
    {
        return self::markStatus($reference, self::STATUS_CANCELLED);
    }

    public static function stats(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get();

            return $rows->pluck('total', 'status')->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function markStatus(string $reference, string $status): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            DB::table(self::TABLE)->where('reference', $reference)->update([
                'status' => $status,
                'confirmed_at' => $status === self::STATUS_CONFIRMED ? now() : null,
                'updated_at' => now(),
            ]);

            $updated = DB::table(self::TABLE)->where('reference', $reference)->first();

            self::log('info', '[VendWeave] Financial status updated', [
                'reference' => $reference,
                'financial_status' => $status,
                'amount_expected' => $updated->amount_expected ?? null,
                'amount_paid' => $updated->amount_paid ?? null,
                'settlement_id' => $updated->settlement_id ?? null,
                'ledger_exported' => (bool) ($updated->ledger_exported ?? false),
            ]);

            return (array) $updated;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function determineStatus(float $expected, float $paid, array $context = []): string
    {
        $isRefund = (bool) ($context['is_refund'] ?? $context['refund'] ?? false);
        $status = $context['status'] ?? null;

        if ($isRefund || $status === 'refunded') {
            return self::STATUS_REFUNDED;
        }

        $expectedRounded = round($expected, 2);
        $paidRounded = round($paid, 2);

        if ($paidRounded < 0) {
            return self::STATUS_REFUNDED;
        }

        if ($paidRounded === $expectedRounded) {
            return self::STATUS_CONFIRMED;
        }

        if ($paidRounded < $expectedRounded) {
            return self::STATUS_PARTIAL;
        }

        if ($paidRounded > $expectedRounded) {
            return self::STATUS_OVERPAID;
        }

        return self::STATUS_PENDING;
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

    private static function currencyColumnsAvailable(): array
    {
        try {
            return [
                'currency' => Schema::hasColumn(self::TABLE, 'currency'),
                'base_currency' => Schema::hasColumn(self::TABLE, 'base_currency'),
                'exchange_rate' => Schema::hasColumn(self::TABLE, 'exchange_rate'),
                'normalized_amount' => Schema::hasColumn(self::TABLE, 'normalized_amount'),
            ];
        } catch (Throwable $e) {
            return [
                'currency' => false,
                'base_currency' => false,
                'exchange_rate' => false,
                'normalized_amount' => false,
            ];
        }
    }
}
