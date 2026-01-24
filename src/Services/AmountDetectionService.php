<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\Log;

/**
 * Intelligent Payable Amount Detection Service.
 * 
 * This service detects the actual payable amount (grand total) from order data
 * using mathematical validation and priority-based field detection.
 * 
 * Philosophy: SDK detects money logically, not linguistically.
 * 
 * Strategy:
 * 1. Priority-based candidate detection (expected_amount, grand_total, etc.)
 * 2. Mathematical validation (subtotal - discount + shipping = expected payable)
 * 3. Conflict resolution when multiple candidates exist
 * 4. Graceful fallback with logging
 */
class AmountDetectionService
{
    /**
     * Detect the actual payable amount from order data.
     * 
     * @param array $orderData Raw order data with various amount fields
     * @return float Detected payable amount
     */
    public function detectPayableAmount(array $orderData): float
    {
        // Step 1: Get all candidate amounts
        $candidates = $this->getCandidateAmounts($orderData);

        if (empty($candidates)) {
            Log::warning('[VendWeave] No amount fields found in order data', [
                'available_fields' => array_keys($orderData),
            ]);
            return 0.0;
        }

        // Step 2: If only one candidate, use it
        if (count($candidates) === 1) {
            $field = array_key_first($candidates);
            $amount = $candidates[$field];
            
            Log::debug('[VendWeave] Single amount candidate detected', [
                'field' => $field,
                'amount' => $amount,
            ]);
            
            return $amount;
        }

        // Step 3: Try mathematical validation
        $validatedAmount = $this->validateWithMath($orderData, $candidates);
        
        if ($validatedAmount !== null) {
            return $validatedAmount;
        }

        // Step 4: Use priority-based selection
        return $this->selectByPriority($candidates, $orderData);
    }

    /**
     * Get candidate amounts from order data based on priority groups.
     * 
     * @param array $orderData
     * @return array Associative array of field => amount
     */
    private function getCandidateAmounts(array $orderData): array
    {
        $primaryFields = config('vendweave.amount_detection.primary_fields', [
            'expected_amount',
            'payable_amount',
            'final_amount',
            'grand_total',
            'total_amount',
            'order_total_amount',
        ]);

        $secondaryFields = config('vendweave.amount_detection.secondary_fields', [
            'total',
            'subtotal',
            'product_total',
        ]);

        $candidates = [];

        // Check primary fields first
        foreach ($primaryFields as $field) {
            if (isset($orderData[$field]) && is_numeric($orderData[$field])) {
                $candidates[$field] = (float) $orderData[$field];
            }
        }

        // If no primary candidates, check secondary fields
        if (empty($candidates)) {
            foreach ($secondaryFields as $field) {
                if (isset($orderData[$field]) && is_numeric($orderData[$field])) {
                    $candidates[$field] = (float) $orderData[$field];
                }
            }
        }

        return $candidates;
    }

    /**
     * Validate amount using mathematical calculation.
     * 
     * If subtotal, discount, shipping exist:
     * calculated = subtotal - discount + shipping
     * 
     * Match with candidates to find the payable amount.
     * 
     * @param array $orderData
     * @param array $candidates
     * @return float|null Validated amount or null if validation not possible
     */
    private function validateWithMath(array $orderData, array $candidates): ?float
    {
        // Check if we have component fields for calculation
        $hasComponents = isset($orderData['subtotal']) || isset($orderData['sub_total']);
        
        if (!$hasComponents) {
            return null;
        }

        $subtotal = (float) ($orderData['subtotal'] ?? $orderData['sub_total'] ?? 0);
        $discount = (float) ($orderData['discount'] ?? $orderData['discount_amount'] ?? $orderData['coupon_discount'] ?? 0);
        $shipping = (float) ($orderData['shipping'] ?? $orderData['shipping_cost'] ?? $orderData['shipping_amount'] ?? 0);
        $tax = (float) ($orderData['tax'] ?? $orderData['tax_amount'] ?? 0);

        // Calculate expected payable
        $calculated = $subtotal - $discount + $shipping + $tax;

        Log::debug('[VendWeave] Mathematical validation attempted', [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'tax' => $tax,
            'calculated' => $calculated,
        ]);

        // Find matching candidate (with 0.01 tolerance for floating point)
        foreach ($candidates as $field => $amount) {
            if (abs($amount - $calculated) < 0.01) {
                Log::info('[VendWeave] Amount validated mathematically', [
                    'field' => $field,
                    'amount' => $amount,
                    'calculation' => "subtotal({$subtotal}) - discount({$discount}) + shipping({$shipping}) + tax({$tax})",
                ]);
                return $amount;
            }
        }

        // Calculation didn't match any candidate
        // This could mean incomplete data or unexpected structure
        return null;
    }

    /**
     * Select amount by priority when validation fails.
     * 
     * Rules:
     * - Prefer primary fields over secondary
     * - If multiple primary exist, choose highest (likely final amount)
     * - Log warning about ambiguity
     * 
     * @param array $candidates
     * @param array $orderData
     * @return float
     */
    private function selectByPriority(array $candidates, array $orderData): float
    {
        $primaryFields = config('vendweave.amount_detection.primary_fields', [
            'expected_amount',
            'payable_amount',
            'final_amount',
            'grand_total',
            'total_amount',
            'order_total_amount',
        ]);

        // Separate primary and secondary candidates
        $primaryCandidates = [];
        $secondaryCandidates = [];

        foreach ($candidates as $field => $amount) {
            if (in_array($field, $primaryFields)) {
                $primaryCandidates[$field] = $amount;
            } else {
                $secondaryCandidates[$field] = $amount;
            }
        }

        // Prefer primary candidates
        $activeCandidates = !empty($primaryCandidates) ? $primaryCandidates : $secondaryCandidates;

        // If multiple candidates, use the highest amount (most likely final payable)
        // This assumes grand_total > subtotal in typical e-commerce
        $selectedField = null;
        $selectedAmount = 0.0;

        foreach ($activeCandidates as $field => $amount) {
            if ($amount > $selectedAmount) {
                $selectedAmount = $amount;
                $selectedField = $field;
            }
        }

        // Log ambiguity warning
        if (count($candidates) > 1) {
            Log::warning('[VendWeave] Payable amount ambiguous. Auto-selected based on priority.', [
                'selected_field' => $selectedField,
                'selected_amount' => $selectedAmount,
                'all_candidates' => $candidates,
                'strategy' => 'highest_primary_field',
            ]);
        }

        return $selectedAmount;
    }

    /**
     * Extract amount from order object or array.
     * 
     * This is the public interface for amount detection.
     * 
     * @param mixed $order Order model, array, or object
     * @return float
     */
    public function extractAmount($order): float
    {
        // Convert to array
        if (is_object($order)) {
            if (method_exists($order, 'toArray')) {
                $orderData = $order->toArray();
            } else {
                $orderData = get_object_vars($order);
            }
        } elseif (is_array($order)) {
            $orderData = $order;
        } else {
            Log::error('[VendWeave] Invalid order data type', [
                'type' => gettype($order),
            ]);
            return 0.0;
        }

        return $this->detectPayableAmount($orderData);
    }
}
