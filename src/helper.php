<?php

function searchArray(string $needle, array $haystack)
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