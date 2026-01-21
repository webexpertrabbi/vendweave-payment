<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReferenceGovernor
{
    public const TABLE = 'vendweave_references';

    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_MATCHED = 'MATCHED';
    public const STATUS_REPLAYED = 'REPLAYED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_EXPIRED = 'EXPIRED';

    private const STATUS_MAP = [
        'reserved' => self::STATUS_RESERVED,
        'matched' => self::STATUS_MATCHED,
        'replayed' => self::STATUS_REPLAYED,
        'cancelled' => self::STATUS_CANCELLED,
        'expired' => self::STATUS_EXPIRED,
    ];

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

    public static function reserve(
        string|int $storeId,
        string $orderId,
        string $reference,
        int|Carbon|null $ttlOrExpiresAt = null
    ): ?array {
        if (!self::isAvailable()) {
            return null;
        }

        $expiresAt = $ttlOrExpiresAt instanceof Carbon
            ? $ttlOrExpiresAt
            : now()->addMinutes($ttlOrExpiresAt ?? self::ttlMinutes());

        try {
            $existing = DB::table(self::TABLE)->where('reference', $reference)->first();
            if ($existing) {
                return (array) $existing;
            }

            DB::table(self::TABLE)->insert([
                'reference' => $reference,
                'order_id' => $orderId,
                'store_id' => (string) $storeId,
                'status' => self::STATUS_RESERVED,
                'expires_at' => $expiresAt,
                'matched_at' => null,
                'replay_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::log('info', '[VendWeave] Reference reserved', [
                'reference' => $reference,
                'status' => self::STATUS_RESERVED,
                'order_id' => $orderId,
                'store_id' => (string) $storeId,
                'expires_at' => $expiresAt,
                'matched_at' => null,
                'replay_count' => 0,
            ]);

            return (array) DB::table(self::TABLE)->where('reference', $reference)->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function match(
        string $reference,
        string|int|null $storeId = null,
        ?Carbon $matchedAt = null
    ): ?array {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $record = DB::table(self::TABLE)->where('reference', $reference)->first();
            if (!$record) {
                return null;
            }

            if ($storeId !== null && (string) $record->store_id !== (string) $storeId) {
                return null;
            }

            if ($record->status === self::STATUS_MATCHED || $record->status === self::STATUS_REPLAYED) {
                return self::markReplay($reference, $storeId);
            }

            if (!empty($record->expires_at) && now()->greaterThan($record->expires_at)) {
                DB::table(self::TABLE)->where('reference', $reference)->update([
                    'status' => self::STATUS_EXPIRED,
                    'updated_at' => now(),
                ]);
                return null;
            }

            $matchedAt = $matchedAt ?? now();

            DB::table(self::TABLE)->where('reference', $reference)->update([
                'status' => self::STATUS_MATCHED,
                'matched_at' => $matchedAt,
                'updated_at' => now(),
            ]);

            self::log('info', '[VendWeave] Reference matched', [
                'reference' => $reference,
                'status' => self::STATUS_MATCHED,
                'order_id' => $record->order_id,
                'store_id' => $record->store_id,
                'expires_at' => $record->expires_at,
                'matched_at' => $matchedAt,
                'replay_count' => $record->replay_count,
            ]);

            return (array) DB::table(self::TABLE)->where('reference', $reference)->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function markReplay(string $reference, string|int|null $storeId = null): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $record = DB::table(self::TABLE)->where('reference', $reference)->first();
            if (!$record) {
                return null;
            }

            if ($storeId !== null && (string) $record->store_id !== (string) $storeId) {
                return null;
            }

            DB::table(self::TABLE)->where('reference', $reference)->update([
                'status' => self::STATUS_REPLAYED,
                'replay_count' => (int) $record->replay_count + 1,
                'updated_at' => now(),
            ]);

            $updated = DB::table(self::TABLE)->where('reference', $reference)->first();

            self::log('warning', '[VendWeave] Reference replay detected', [
                'reference' => $reference,
                'status' => self::STATUS_REPLAYED,
                'order_id' => $updated->order_id ?? null,
                'store_id' => $updated->store_id ?? null,
                'expires_at' => $updated->expires_at ?? null,
                'matched_at' => $updated->matched_at ?? null,
                'replay_count' => $updated->replay_count ?? null,
            ]);

            return (array) $updated;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function cancel(string $reference, string|int|null $storeId = null): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $record = DB::table(self::TABLE)->where('reference', $reference)->first();
            if (!$record) {
                return null;
            }

            if ($storeId !== null && (string) $record->store_id !== (string) $storeId) {
                return null;
            }

            DB::table(self::TABLE)->where('reference', $reference)->update([
                'status' => self::STATUS_CANCELLED,
                'updated_at' => now(),
            ]);

            $updated = DB::table(self::TABLE)->where('reference', $reference)->first();

            self::log('info', '[VendWeave] Reference cancelled', [
                'reference' => $reference,
                'status' => self::STATUS_CANCELLED,
                'order_id' => $updated->order_id ?? null,
                'store_id' => $updated->store_id ?? null,
                'expires_at' => $updated->expires_at ?? null,
                'matched_at' => $updated->matched_at ?? null,
                'replay_count' => $updated->replay_count ?? null,
            ]);

            return (array) $updated;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function expireOverdue(): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        try {
            $now = now();
            $count = DB::table(self::TABLE)
                ->whereIn('status', [self::STATUS_RESERVED, 'reserved'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->update([
                    'status' => self::STATUS_EXPIRED,
                    'updated_at' => $now,
                ]);

            if ($count > 0) {
                self::log('info', '[VendWeave] References expired', [
                    'reference' => null,
                    'status' => self::STATUS_EXPIRED,
                    'order_id' => null,
                    'store_id' => null,
                    'expires_at' => $now,
                    'matched_at' => null,
                    'replay_count' => null,
                    'expired_count' => $count,
                ]);
            }

            return $count;
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function validate(
        string $reference,
        ?string $orderId = null,
        ?string $storeId = null
    ): ?string {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $record = DB::table(self::TABLE)->where('reference', $reference)->first();
            if (!$record) {
                return null;
            }

            if ($orderId !== null && (string) $record->order_id !== (string) $orderId) {
                return self::STATUS_REPLAYED;
            }

            if ($storeId !== null && $record->store_id !== null && (string) $record->store_id !== (string) $storeId) {
                return self::STATUS_REPLAYED;
            }

            return self::normalizeStatus($record->status ?? null);
        } catch (Throwable $e) {
            return null;
        }
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

            $stats = [];
            foreach ($rows as $row) {
                $status = self::normalizeStatus($row->status) ?? (string) $row->status;
                $stats[$status] = ($stats[$status] ?? 0) + (int) $row->total;
            }

            return $stats;
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $key = strtolower($status);
        return self::STATUS_MAP[$key] ?? strtoupper($status);
    }

    private static function isEnabled(): bool
    {
        return (bool) config('vendweave.reference_governance.enabled', true);
    }

    private static function ttlMinutes(): int
    {
        return (int) config('vendweave.reference_governance.ttl_minutes', config('vendweave.reference_ttl', 30));
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
