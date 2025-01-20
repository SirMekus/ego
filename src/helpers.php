<?php

/**
 * Searches for a specific value in a nested array.
 *
 * @param string $needle The value to search for in the array.
 * @param array $haystack The array to search through.
 *
 * @return string|null The found value if it exists, otherwise null.
 */
function searchArray(string $needle, array $haystack): ?string
{
    foreach ($haystack as $key => $value) {
        if ($key === $needle && !empty($haystack[$key])) {
            return $haystack[$key];
        } elseif (is_array($haystack[$key])) {
            $result = searchArray($needle, $haystack[$key]);
            if ($result !== null) {
                return $result;
            }
        }
    }
    return null;
}