<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Order Adapter Service
 * 
 * This service provides a flexible mapping layer between the package
 * and your Laravel application's order model. It allows integration
 * without requiring changes to your existing database schema.
 * 
 * Enterprise-grade solution for field name mismatches.
 */
class OrderAdapter
{
    /**
     * Field mapping from config.
     */
    protected array $fieldMap;

    /**
     * Status mapping from config.
     */
    protected array $statusMap;

    /**
     * Order model class.
     */
    protected string $modelClass;

    /**
     * Amount detection service.
     */
    protected AmountDetectionService $amountDetector;

    public function __construct(AmountDetectionService $amountDetector = null)
    {
        $this->fieldMap = config('vendweave.order_mapping', [
            'id' => 'id',
            'amount' => 'total',
            'payment_method' => 'payment_method',
            'status' => 'status',
            'trx_id' => 'trx_id',
            'payment_reference' => 'payment_reference',
        ]);

        $this->statusMap = config('vendweave.status_mapping', [
            'paid' => 'paid',
            'pending' => 'pending',
            'failed' => 'failed',
        ]);

        $this->modelClass = config('vendweave.order_model', 'App\\Models\\Order');
        
        // Initialize amount detector
        $this->amountDetector = $amountDetector ?? new AmountDetectionService();
    }

    /**
     * Get the mapped field name.
     *
     * @param string $field Package field name
     * @return string Actual database column name
     */
    public function getField(string $field): string
    {
        return $this->fieldMap[$field] ?? $field;
    }

    /**
     * Get value from order using mapped field.
     *
     * @param Model $order
     * @param string $field Package field name
     * @return mixed
     */
    public function getValue(Model $order, string $field): mixed
    {
        $actualField = $this->getField($field);
        return $order->{$actualField};
    }

    /**
     * Set value on order using mapped field.
     *
     * @param Model $order
     * @param string $field Package field name
     * @param mixed $value
     * @return void
     */
    public function setValue(Model $order, string $field, mixed $value): void
    {
        $actualField = $this->getField($field);
        $order->{$actualField} = $value;
    }

    /**
     * Get the amount from order using intelligent detection.
     * 
     * This method uses AmountDetectionService to intelligently detect
     * the actual payable amount (grand total) using:
     * - Priority-based field detection
     * - Mathematical validation (subtotal - discount + shipping)
     * - Conflict resolution
     * 
     * SDK detects money logically, not linguistically.
     *
     * @param Model $order
     * @return float
     */
    public function getAmount(Model $order): float
    {
        // Use intelligent detection instead of blind field lookup
        return $this->amountDetector->extractAmount($order);
    }

    /**
     * Get the payment method from order.
     *
     * @param Model $order
     * @return string
     */
    public function getPaymentMethod(Model $order): string
    {
        return strtolower((string) $this->getValue($order, 'payment_method'));
    }

    /**
     * Get the order ID.
     *
     * @param Model $order
     * @return mixed
     */
    public function getOrderId(Model $order): mixed
    {
        return $this->getValue($order, 'id');
    }

    /**
     * Get the transaction ID from order.
     *
     * @param Model $order
     * @return string|null
     */
    public function getTrxId(Model $order): ?string
    {
        return $this->getValue($order, 'trx_id');
    }

    /**
     * Get the payment reference from order.
     *
     * @param Model $order
     * @return string|null
     */
    public function getReference(Model $order): ?string
    {
        return $this->getValue($order, 'payment_reference');
    }

    /**
     * Set the payment reference on order.
     *
     * @param Model $order
     * @param string $reference
     * @return void
     */
    public function setReference(Model $order, string $reference): void
    {
        $this->setValue($order, 'payment_reference', $reference);
    }

    /**
     * Map VendWeave status to application status.
     *
     * @param string $vendweaveStatus (paid, pending, failed)
     * @return mixed Your application's status value
     */
    public function mapStatus(string $vendweaveStatus): mixed
    {
        return $this->statusMap[$vendweaveStatus] ?? $vendweaveStatus;
    }

    /**
     * Update order status to paid.
     *
     * @param Model $order
     * @param string $trxId Transaction ID
     * @return bool
     */
    public function markAsPaid(Model $order, string $trxId, ?string $reference = null): bool
    {
        $this->setValue($order, 'status', $this->mapStatus('paid'));
        $this->setValue($order, 'trx_id', $trxId);
        
        // Store payment reference if provided
        if ($reference !== null) {
            $this->setValue($order, 'payment_reference', $reference);
        }
        
        return $order->save();
    }

    /**
     * Update order status to failed.
     *
     * @param Model $order
     * @return bool
     */
    public function markAsFailed(Model $order): bool
    {
        $this->setValue($order, 'status', $this->mapStatus('failed'));
        
        return $order->save();
    }

    /**
     * Find order by ID.
     *
     * @param mixed $orderId
     * @return Model|null
     */
    public function findOrder(mixed $orderId): ?Model
    {
        $idField = $this->getField('id');
        
        return $this->modelClass::where($idField, $orderId)->first();
    }

    /**
     * Get the order model class.
     *
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Get all field mappings.
     *
     * @return array
     */
    public function getFieldMappings(): array
    {
        return $this->fieldMap;
    }

    /**
     * Get all status mappings.
     *
     * @return array
     */
    public function getStatusMappings(): array
    {
        return $this->statusMap;
    }
}
