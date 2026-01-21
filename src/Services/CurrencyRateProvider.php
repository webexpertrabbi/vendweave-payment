<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\Cache;

class CurrencyRateProvider
{
    public static function getRate(string $fromCurrency, string $toCurrency): ?float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = "vendweave:fx:{$from}:{$to}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (float) $cached;
        }

        $rate = null;

        if (self::source() === 'api') {
            $rate = self::fetchFromApi($from, $to);
        }

        if ($rate === null) {
            $rate = self::fetchFromStatic($from, $to);
        }

        if ($rate !== null) {
            Cache::put($cacheKey, $rate, now()->addMinutes(30));
        }

        return $rate;
    }

    private static function fetchFromApi(string $from, string $to): ?float
    {
        // Placeholder for future API integration. Keep safe fallback.
        return null;
    }

    private static function fetchFromStatic(string $from, string $to): ?float
    {
        $rates = config('vendweave.static_rates', []);
        $base = strtoupper(config('vendweave.base_currency', 'USD'));

        if ($from === $base && isset($rates[$to])) {
            $rate = (float) $rates[$to];
            return $rate > 0 ? 1 / $rate : null;
        }

        if ($to === $base && isset($rates[$from])) {
            return (float) $rates[$from];
        }

        if (isset($rates[$from]) && isset($rates[$to])) {
            $fromRate = (float) $rates[$from];
            $toRate = (float) $rates[$to];
            if ($fromRate <= 0) {
                return null;
            }
            return $toRate / $fromRate;
        }

        return null;
    }

    private static function source(): string
    {
        return strtolower((string) config('vendweave.exchange_rate_source', 'static'));
    }
}
