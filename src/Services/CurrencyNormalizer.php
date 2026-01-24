<?php

namespace VendWeave\Gateway\Services;

class CurrencyNormalizer
{
    public static function normalize(float $amount, string $fromCurrency, string $toCurrency): array
    {
        $rate = CurrencyRateProvider::getRate($fromCurrency, $toCurrency);

        if ($rate === null) {
            return [
                'normalized_amount' => $amount,
                'exchange_rate' => null,
                'base_currency' => strtoupper($toCurrency),
                'currency' => strtoupper($fromCurrency),
            ];
        }

        return [
            'normalized_amount' => round($amount * $rate, 8),
            'exchange_rate' => $rate,
            'base_currency' => strtoupper($toCurrency),
            'currency' => strtoupper($fromCurrency),
        ];
    }
}
