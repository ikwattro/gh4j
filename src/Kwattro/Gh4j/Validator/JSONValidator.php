<?php

namespace Kwattro\Gh4j\Validator;

class JSONValidator
{
	public function validate($string, $assoc = false)
	{
		if (!is_string($string)) {
			return false;
		}
		$string = trim($string);

		$firstChar = substr($string, 0, 1);
		$lastChar = substr($string, -1);

		if (!$firstChar || !$lastChar) {
			return false;
		}

		if ($firstChar !== '{' && $firstChar !== '[') {
			return false;
		}

		if ($lastChar !== '}' && $lastChar !== ']') {
			return false;
		}

		$decoded = json_decode($string, $assoc);

		if (json_last_error() === JSON_ERROR_NONE) {
			return $decoded;
		}
		return false;
		
	}
}