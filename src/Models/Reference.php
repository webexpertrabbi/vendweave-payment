<?php

namespace VendWeave\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reference lifecycle model.
 * 
 * Tracks payment references through their lifecycle:
 * RESERVED → MATCHED | EXPIRED | REPLAYED | CANCELLED
 * 
 * @property int $id
 * @property int $store_id
 * @property string $order_id
 * @property string $reference
 * @property string $status
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $matched_at
 * @property int $replay_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Reference extends Model
{
    /**
     * Reference lifecycle states.
     */
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REPLAYED = 'replayed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The table associated with the model.
     */
    protected $table = 'vendweave_references';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'store_id',
        'order_id',
        'reference',
        'status',
        'expires_at',
        'matched_at',
        'replay_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'store_id' => 'integer',
        'expires_at' => 'datetime',
        'matched_at' => 'datetime',
        'replay_count' => 'integer',
    ];

    /**
     * Check if reference is reserved and valid.
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_RESERVED 
            && $this->expires_at->isFuture();
    }

    /**
     * Check if reference is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED 
            || ($this->status === self::STATUS_RESERVED && $this->expires_at->isPast());
    }

    /**
     * Check if reference is already matched.
     */
    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }

    /**
     * Check if reference is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Scope: Reserved references.
     */
    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    /**
     * Scope: Expired references (past expires_at but still reserved).
     */
    public function scopeExpiredReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED)
                     ->where('expires_at', '<', now());
    }

    /**
     * Scope: By store.
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope: By reference code.
     */
    public function scopeByReference($query, string $reference)
    {
        return $query->where('reference', $reference);
    }
}
