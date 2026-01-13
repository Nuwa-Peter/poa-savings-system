<?php
// config/app_config.php

// Currency Conversion Settings
define('BASE_CURRENCY', 'UGX');
define('TARGET_CURRENCY', 'USD');
define('EXCHANGE_RATE_UGX_TO_USD', 1 / 3700); // Example rate: 1 USD = 3700 UGX

/**
 * Formats a number as currency.
 *
 * @param float $amount The amount to format.
 * @param string $currency The currency code (e.g., 'UGX', 'USD').
 * @return string The formatted currency string.
 */
function format_currency($amount, $currency = 'UGX') {
    $amount = (float)$amount;
    if ($currency === 'UGX') {
        return 'UGX ' . number_format($amount, 0);
    }
    if ($currency === 'USD') {
        return '$' . number_format($amount, 2);
    }
    return number_format($amount, 2);
}

/**
 * Converts an amount from the base currency (UGX) to the target currency (USD).
 *
 * @param float $amount_ugx The amount in UGX.
 * @return float The equivalent amount in USD.
 */
function convert_ugx_to_usd($amount_ugx) {
    return (float)$amount_ugx * EXCHANGE_RATE_UGX_TO_USD;
}
