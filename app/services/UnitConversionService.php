<?php

declare(strict_types=1);

namespace App\Services;

final class UnitConversionService
{
    public function normalizeFactor(float $factor): float
    {
        if ($factor <= 0) {
            return 1.0;
        }

        return $factor;
    }

    public function toUsageFromPurchase(float $purchaseQty, float $factor): float
    {
        return round($purchaseQty * $this->normalizeFactor($factor), 6);
    }

    public function toPurchaseFromUsage(float $usageQty, float $factor): float
    {
        $normalizedFactor = $this->normalizeFactor($factor);
        return round($usageQty / $normalizedFactor, 6);
    }

    public function roundPurchaseByMultiple(float $purchaseQty, float $multiple): float
    {
        if ($purchaseQty <= 0) {
            return 0.0;
        }

        $normalizedMultiple = $multiple > 0 ? $multiple : 1.0;
        $lots = (int) ceil($purchaseQty / $normalizedMultiple);

        return round($lots * $normalizedMultiple, 6);
    }

    public function planPurchase(
        float $requiredUsageQty,
        float $factor,
        float $lotMin,
        ?float $purchaseMultiple = null
    ): array {
        $requiredPurchaseQty = $this->toPurchaseFromUsage($requiredUsageQty, $factor);
        $multiple = ($purchaseMultiple !== null && $purchaseMultiple > 0) ? $purchaseMultiple : ($lotMin > 0 ? $lotMin : 1.0);
        $purchaseQtyRounded = $this->roundPurchaseByMultiple($requiredPurchaseQty, $multiple);
        $usageQtyFromPurchase = $this->toUsageFromPurchase($purchaseQtyRounded, $factor);

        return [
            'required_usage_qty' => round($requiredUsageQty, 6),
            'required_purchase_qty' => $requiredPurchaseQty,
            'purchase_qty' => $purchaseQtyRounded,
            'usage_qty_from_purchase' => $usageQtyFromPurchase,
            'remaining_usage_qty' => round($usageQtyFromPurchase - $requiredUsageQty, 6),
            'multiple_applied' => $multiple,
            'factor' => $this->normalizeFactor($factor),
        ];
    }

    public function usageUnitPriceFromPurchase(float $purchaseUnitPrice, float $factor): float
    {
        $normalizedFactor = $this->normalizeFactor($factor);
        return round($purchaseUnitPrice / $normalizedFactor, 6);
    }
}
