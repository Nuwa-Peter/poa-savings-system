<?php
// includes/avatar_functions.php

/**
 * Generates initials from a username.
 * Example: "John Doe" -> "JD"
 *
 * @param string $name The user's full name or username.
 * @return string The generated initials.
 */
function get_initials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    if (count($words) >= 2) {
        // Use the first letter of the first two words
        $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } elseif (!empty($words[0])) {
        // Use the first two letters of a single word name
        $initials = strtoupper(substr($words[0], 0, 2));
    } else {
        // Default fallback
        $initials = '??';
    }
    return $initials;
}

/**
 * Displays the user's avatar or their initials as a fallback.
 *
 * @param string|null $avatar_path The path to the user's avatar image.
 * @param string $username The user's username.
 * @return void Echos the HTML for the avatar.
 */
function display_avatar($avatar_path, $username) {
    $size_class = 'h-10 w-10'; // Consistent size

    if ($avatar_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $avatar_path)) {
        // Display the user's uploaded avatar
        echo '<img src="' . htmlspecialchars($avatar_path) . '" alt="User Avatar" class="rounded-full ' . $size_class . ' object-cover">';
    } else {
        // Display the initials as a fallback
        $initials = get_initials($username);
        echo '<div class="rounded-full ' . $size_class . ' bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">';
        echo htmlspecialchars($initials);
        echo '</div>';
    }
}
