<?php

namespace VendWeave\Gateway\Services;

use VendWeave\Gateway\Models\Reference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reference Governance Engine.
 * 
 * Manages payment reference lifecycle:
 * - Reservation
 * - Matching
 * - Expiry
 * - Replay prevention
 * - Cancellation
 * - Analytics
 */
class ReferenceGovernor
{
    /**
     * Check if governance table exists.
     */
    public static function isEnabled(): bool
    {
        try {
            return Schema::hasTable('vendweave_references');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reserve a reference for an order.
     *
     * @param int $storeId
     * @param string $orderId
     * @param string $reference
     * @param int $ttlMinutes
     * @return Reference|null
     */
    public static function reserve(
        int $storeId,
        string $orderId,
        string $reference,
        int $ttlMinutes = 30
    ): ?Reference {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            // Check if reference already exists for this store
            $existing = Reference::forStore($storeId)
                ->byReference($reference)
                ->whereIn('status', [Reference::STATUS_RESERVED, Reference::STATUS_MATCHED])
                ->first();

            if ($existing) {
                Log::warning('[VendWeave] Reference already exists', [
                    'reference' => $reference,
                    'store_id' => $storeId,
                    'existing_status' => $existing->status,
                ]);
                return null;
            }

            $ref = Reference::create([
                'store_id' => $storeId,
                'order_id' => $orderId,
                'reference' => $reference,
                'status' => Reference::STATUS_RESERVED,
                'expires_at' => now()->addMinutes($ttlMinutes),
                'replay_count' => 0,
            ]);

            Log::info('[VendWeave] Reference reserved', [
                'reference' => $reference,
                'store_id' => $storeId,
                'order_id' => $orderId,
                'expires_at' => $ref->expires_at->toIso8601String(),
            ]);

            return $ref;
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to reserve reference', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mark reference as matched (payment confirmed).
     *
     * @param string $reference
     * @param int|null $storeId
     * @return Reference|null
     */
    public static function match(string $reference, ?int $storeId = null): ?Reference
    {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $query = Reference::byReference($reference)
                ->where('status', Reference::STATUS_RESERVED);

            if ($storeId !== null) {
                $query->forStore($storeId);
            }

            $ref = $query->first();

            if (!$ref) {
                return null;
            }

            // Check if expired
            if ($ref->expires_at->isPast()) {
                $ref->update(['status' => Reference::STATUS_EXPIRED]);
                Log::warning('[VendWeave] Reference expired before match', [
                    'reference' => $reference,
                    'expired_at' => $ref->expires_at->toIso8601String(),
                ]);
                return null;
            }

            $ref->update([
                'status' => Reference::STATUS_MATCHED,
                'matched_at' => now(),
            ]);

            Log::info('[VendWeave] Reference matched', [
                'reference' => $reference,
                'store_id' => $ref->store_id,
                'order_id' => $ref->order_id,
                'matched_at' => $ref->matched_at->toIso8601String(),
            ]);

            return $ref;
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to match reference', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Expire all overdue reserved references.
     *
     * @return int Number of expired references
     */
    public static function expireOverdue(): int
    {
        if (!self::isEnabled()) {
            return 0;
        }

        try {
            $count = Reference::expiredReserved()->update([
                'status' => Reference::STATUS_EXPIRED,
            ]);

            if ($count > 0) {
                Log::info('[VendWeave] Expired overdue references', [
                    'count' => $count,
                ]);
            }

            return $count;
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to expire references', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Cancel a reference.
     *
     * @param string $reference
     * @param int|null $storeId
     * @return bool
     */
    public static function cancel(string $reference, ?int $storeId = null): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            $query = Reference::byReference($reference)
                ->where('status', Reference::STATUS_RESERVED);

            if ($storeId !== null) {
                $query->forStore($storeId);
            }

            $updated = $query->update(['status' => Reference::STATUS_CANCELLED]);

            if ($updated > 0) {
                Log::info('[VendWeave] Reference cancelled', [
                    'reference' => $reference,
                    'store_id' => $storeId,
                ]);
            }

            return $updated > 0;
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to cancel reference', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark reference as replayed (attempted reuse).
     *
     * @param string $reference
     * @param int|null $storeId
     * @return Reference|null
     */
    public static function markReplay(string $reference, ?int $storeId = null): ?Reference
    {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $query = Reference::byReference($reference)
                ->where('status', Reference::STATUS_MATCHED);

            if ($storeId !== null) {
                $query->forStore($storeId);
            }

            $ref = $query->first();

            if (!$ref) {
                return null;
            }

            $ref->increment('replay_count');
            $ref->update(['status' => Reference::STATUS_REPLAYED]);

            Log::warning('[VendWeave] Replay attack detected', [
                'reference' => $reference,
                'store_id' => $ref->store_id,
                'order_id' => $ref->order_id,
                'replay_count' => $ref->replay_count,
            ]);

            return $ref;
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to mark replay', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate a reference and return its status.
     *
     * @param string $reference
     * @param int|null $storeId
     * @return array{valid: bool, status: string|null, reference: Reference|null}
     */
    public static function validate(string $reference, ?int $storeId = null): array
    {
        if (!self::isEnabled()) {
            return ['valid' => true, 'status' => null, 'reference' => null];
        }

        try {
            $query = Reference::byReference($reference);

            if ($storeId !== null) {
                $query->forStore($storeId);
            }

            $ref = $query->latest()->first();

            if (!$ref) {
                return ['valid' => false, 'status' => 'missing', 'reference' => null];
            }

            // Check status
            switch ($ref->status) {
                case Reference::STATUS_RESERVED:
                    if ($ref->expires_at->isPast()) {
                        $ref->update(['status' => Reference::STATUS_EXPIRED]);
                        return ['valid' => false, 'status' => 'expired', 'reference' => $ref];
                    }
                    return ['valid' => true, 'status' => 'reserved', 'reference' => $ref];

                case Reference::STATUS_MATCHED:
                    return ['valid' => false, 'status' => 'replayed', 'reference' => $ref];

                case Reference::STATUS_EXPIRED:
                    return ['valid' => false, 'status' => 'expired', 'reference' => $ref];

                case Reference::STATUS_REPLAYED:
                    return ['valid' => false, 'status' => 'replayed', 'reference' => $ref];

                case Reference::STATUS_CANCELLED:
                    return ['valid' => false, 'status' => 'cancelled', 'reference' => $ref];

                default:
                    return ['valid' => false, 'status' => 'unknown', 'reference' => $ref];
            }
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to validate reference', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            // Graceful fallback
            return ['valid' => true, 'status' => null, 'reference' => null];
        }
    }

    /**
     * Get reference statistics for a store.
     *
     * @param int $storeId
     * @return array
     */
    public static function stats(int $storeId): array
    {
        if (!self::isEnabled()) {
            return [
                'enabled' => false,
                'total_reserved' => 0,
                'total_matched' => 0,
                'total_expired' => 0,
                'total_replayed' => 0,
                'total_cancelled' => 0,
                'conversion_rate' => 0,
            ];
        }

        try {
            $reserved = Reference::forStore($storeId)->where('status', Reference::STATUS_RESERVED)->count();
            $matched = Reference::forStore($storeId)->where('status', Reference::STATUS_MATCHED)->count();
            $expired = Reference::forStore($storeId)->where('status', Reference::STATUS_EXPIRED)->count();
            $replayed = Reference::forStore($storeId)->where('status', Reference::STATUS_REPLAYED)->count();
            $cancelled = Reference::forStore($storeId)->where('status', Reference::STATUS_CANCELLED)->count();

            $total = $reserved + $matched + $expired + $replayed + $cancelled;
            $conversionRate = $total > 0 ? round(($matched / $total) * 100, 2) : 0;

            return [
                'enabled' => true,
                'total_reserved' => $reserved,
                'total_matched' => $matched,
                'total_expired' => $expired,
                'total_replayed' => $replayed,
                'total_cancelled' => $cancelled,
                'conversion_rate' => $conversionRate,
            ];
        } catch (\Exception $e) {
            Log::error('[VendWeave] Failed to get stats', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);
            return [
                'enabled' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
