<?php
// Define the hardcoded exchange rate
define('UGX_TO_USD_RATE', 3700);

/**
 * Converts a UGX amount to USD using a hardcoded exchange rate.
 *
 * @param float $ugx_amount The amount in Ugandan Shillings.
 * @return float The equivalent amount in US Dollars.
 */
function convert_ugx_to_usd($ugx_amount) {
    if (!is_numeric($ugx_amount)) {
        return 0.0;
    }
    return $ugx_amount / UGX_TO_USD_RATE;
}
?>
