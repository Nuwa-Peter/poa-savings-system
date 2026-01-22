<?php
// includes/avatar_functions.php

/**
 * Generates initials from a username.
 * Example: "John Doe" -> "JD"
 *
 * @param string $name The user's full name or username.
 * @return string The generated initials.
 */
function get_initials($first_name, $surname, $username = '') {
    if (!empty($first_name) && !empty($surname)) {
        // Use the first letter of the first name and surname
        return strtoupper(substr($first_name, 0, 1) . substr($surname, 0, 1));
    }

    // Fallback to username if names are not available
    $username = $username ?? ''; // Ensure username is not null
    $words = explode(' ', trim($username));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } elseif (!empty($words[0])) {
        return strtoupper(substr($words[0], 0, 2));
    }

    // Default fallback
    return '??';
}

/**
 * Displays the user's avatar or their initials as a fallback.
 *
 * @param string|null $avatar_path The path to the user's avatar image.
 * @param string $username The user's username (used as a fallback).
 * @param string|null $first_name The user's first name.
 * @param string|null $surname The user's surname.
 * @return void Echos the HTML for the avatar.
 */
function display_avatar($avatar_path, $username, $first_name = null, $surname = null) {
    $size_class = 'h-10 w-10'; // Consistent size

    if ($avatar_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $avatar_path)) {
        // Display the user's uploaded avatar
        echo '<img src="' . htmlspecialchars($avatar_path) . '" alt="User Avatar" class="rounded-full ' . $size_class . ' object-cover">';
    } else {
        // Display the initials as a fallback
        $initials = get_initials($first_name, $surname, $username);
        echo '<div class="rounded-full ' . $size_class . ' bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">';
        echo htmlspecialchars($initials);
        echo '</div>';
    }
}
