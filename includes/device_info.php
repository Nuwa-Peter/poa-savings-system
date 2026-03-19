<?php
/**
 * Device Info Utility - Captures Browser, OS, and Screen Size for security logging.
 */

function getDeviceInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Browser Detection
    $browser = "Unknown Browser";
    if (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) $browser = 'Internet Explorer';
    elseif (strpos($userAgent, 'Edge') !== false) $browser = 'Microsoft Edge';
    elseif (strpos($userAgent, 'Firefox') !== false) $browser = 'Mozilla Firefox';
    elseif (strpos($userAgent, 'Chrome') !== false) $browser = 'Google Chrome';
    elseif (strpos($userAgent, 'Safari') !== false) $browser = 'Apple Safari';
    elseif (strpos($userAgent, 'Opera') !== false || strpos($userAgent, 'OPR') !== false) $browser = 'Opera';

    // OS Detection
    $os = "Unknown OS";
    if (strpos($userAgent, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) $os = 'iOS';
    elseif (strpos($userAgent, 'Android') !== false) $os = 'Android';
    elseif (strpos($userAgent, 'Macintosh') !== false || strpos($userAgent, 'Mac OS') !== false) $os = 'Mac OS';
    elseif (strpos($userAgent, 'Linux') !== false) $os = 'Linux';

    return [
        'browser' => $browser,
        'os' => $os,
        'user_agent' => $userAgent,
        'is_mobile' => (strpos($os, 'iOS') !== false || strpos($os, 'Android') !== false)
    ];
}
