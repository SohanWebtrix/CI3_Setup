<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[\AllowDynamicProperties]
class ValidateData
{

	public function __construct()
	{

		//parent::__construct();
		$this->CI = &get_instance();
		//$this->CI->load->library('response');
	}
	/**
	 * Validate & sanitize an input field (POST/GET).
	 *
	 * Backwards-compatible with your old signature, plus a $rules array:
	 *  $rules = [
	 *    'trim' => true,           // default true
	 *    'strip_tags' => true,     // default true
	 *    'xss_clean' => true,      // default true (uses CI Security)
	 *    'mb' => true,             // use mb_strlen for length checks
	 *    'type' => 'string'|'email'|'url'|'int'|'float'|'bool'|'date'|'datetime'|'json',
	 *    'regex' => '/^...$/u',
	 *    'in' => ['A','B'],        // whitelist
	 *    'not_in' => ['X','Y'],    // blacklist
	 *    'numeric_min' => 0,       // inclusive
	 *    'numeric_max' => 100,     // inclusive
	 *    'date_min' => '2020-01-01',
	 *    'date_max' => '2030-12-31',
	 *    'format' => 'Y-m-d',      // for type=date (default Y-m-d)
	 *    'datetime_format' => 'Y-m-d H:i:s', // for type=datetime
	 *    'allow_empty' => false    // overrides $isRequired=false semantics if needed
	 *  ]
	 */
	public function validate(
		$fieldName = '',
		$lable = null,
		$isRequired = false,
		$minLenth = array(),     // keep old spelling for BC: [true, N]
		$maxLenght = array(),    // keep old spelling for BC: [true, N]
		$method = 'post',
		array $rules = []
	) {
		// ---- helpers -----------------------------------------------------------
		$CI = $this->CI;

		$msg = function($code, $fallback) use ($CI) {
			try {
				$m = $CI->systemmsg->getErrorCode($code);
				return $m ?: $fallback;
			} catch (\Throwable $e) {
				return $fallback;
			}
		};

		$fail = function($code, $text, $data = []) use ($CI) {
			$status = [
				'msg' => $text,
				'statusCode' => $code,
				'data' => $data,
				'flag' => 'F',
			];
			$CI->response->output($status, 200);
		};

		$getLen = function($str, $useMb = true) {
			if ($str === null) return 0;
			if ($useMb && function_exists('mb_strlen')) return mb_strlen($str, 'UTF-8');
			return strlen($str);
		};

		$labelSafe = $lable ?: $fieldName;

		// ---- fetch raw input ---------------------------------------------------
		$raw = ($method === 'post')
			? $CI->input->post($fieldName, false) // false => don't XSS clean yet
			: $CI->input->get($fieldName, false);

		// Normalize nulls
		$val = ($raw !== null) ? $raw : null;

		// ---- defaults for rules ------------------------------------------------
		$rules = array_merge([
			'trim' => true,
			'strip_tags' => true,
			'safe_html' => true,         // NEW
			'xss_clean' => true,
			'mb' => true,
			'type' => 'string',
			'allow_empty' => false,
			'format' => 'Y-m-d',
			'datetime_format' => 'Y-m-d H:i:s',
		], $rules);

		// ---- sanitize ----------------------------------------------------------
		if (is_string($val)) {
			if (!empty($rules['trim']))          $val = trim($val);
			if (!empty($rules['strip_tags']))    $val = strip_tags($val);
			// collapse excessive whitespace
			$val = preg_replace('/\s+/u', ' ', $val);
		}

		if (!empty($rules['xss_clean']) && $val !== null) {
			// CI Security xss_clean is safe for strings/arrays
			if (method_exists($CI->security, 'xss_clean')) {
				$val = $CI->security->xss_clean($val);
			}
		}

		// ---- required / empty checks ------------------------------------------
		$isEmpty = (is_array($val) && count($val) === 0) || (!is_array($val) && ($val === null || $val === ''));
		if ($isRequired && $isEmpty && !$rules['allow_empty']) {
			$text = str_replace('{fieldName}', $labelSafe, $msg(218, "{$labelSafe} is required."));
			$fail(218, $text);
		}

		// If not required and empty, return null early (keeps old behavior)
		if (!$isRequired && $isEmpty) {
			return null;
		}

		// ---- length checks (strings only) --------------------------------------
		if (is_string($val)) {
			if (!empty($minLenth) && $minLenth[0] === true) {
				$len = $getLen($val, $rules['mb']);
				if ($len < (int)$minLenth[1]) {
					$t = str_replace(
						['{fieldName}', '{minchar}'],
						[$labelSafe, (int)$minLenth[1]],
						$msg(219, "{$labelSafe} must be at least {minchar} characters.")
					);
					$fail(219, $t);
				}
			}

			if (!empty($maxLenght) && $maxLenght[0] === true) {
				$len = $getLen($val, $rules['mb']);
				if ($len > (int)$maxLenght[1]) {
					$t = str_replace(
						['{fieldName}', '{maxchar}'],
						[$labelSafe, (int)$maxLenght[1]],
						$msg(220, "{$labelSafe} must be at most {maxchar} characters.")
					);
					$fail(220, $t);
				}
			}
			if (!empty($rules['safe_html'])) {
				// Allowed HTML tags
				$allowed = '<b><i><u><strong><em><p><div><span><br><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><table><thead><tbody><tr><td><th>';

				// Remove ONLY unwanted tags
				$val = strip_tags($val, $allowed);

				// Remove dangerous inline event handlers like onclick=""
				$val = preg_replace('/on\w+="[^"]*"/i', '', $val);
				$val = preg_replace("/on\w+='[^']*'/i", '', $val);

				// Remove javascript: URLs
				$val = preg_replace('/javascript:/i', '', $val);
			}
			$val = preg_replace('/\s+/u', ' ', $val);
		}

		// ---- type validations ---------------------------------------------------
		$type = strtolower((string)$rules['type']);

		$asNumber = null;
		$asDate   = null;

		switch ($type) {
			case 'email':
				if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
					$t = str_replace('{fieldName}', $labelSafe, $msg(221, "Invalid {$labelSafe}. Please enter a valid email."));
					$fail(221, $t);
				}
				break;

			case 'url':
				if (!filter_var($val, FILTER_VALIDATE_URL)) {
					$t = str_replace('{fieldName}', $labelSafe, $msg(221, "Invalid {$labelSafe}. Please enter a valid URL."));
					$fail(221, $t);
				}
				break;

			case 'int':
				if (filter_var($val, FILTER_VALIDATE_INT) === false) {
					$t = str_replace('{fieldName}', $labelSafe, $msg(221, "{$labelSafe} must be an integer."));
					$fail(221, $t);
				}
				$asNumber = (int)$val;
				$val = $asNumber;
				break;

			case 'float':
				if (filter_var($val, FILTER_VALIDATE_FLOAT) === false) {
					$t = str_replace('{fieldName}', $labelSafe, $msg(221, "{$labelSafe} must be a number."));
					$fail(221, $t);
				}
				$asNumber = (float)$val;
				$val = $asNumber;
				break;

			case 'bool':
				// Accept "1/0/true/false/on/off/yes/no"
				$bool = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
				if ($bool === null) {
					$t = str_replace('{fieldName}', $labelSafe, $msg(221, "{$labelSafe} must be true or false."));
					$fail(221, $t);
				}
				$val = (bool)$bool;
				break;

			case 'json':
				if (is_string($val)) {
					$decoded = json_decode($val, true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						$t = str_replace('{fieldName}', $labelSafe, $msg(226, "{$labelSafe} must be valid JSON."));
						$fail(226, $t);
					}
					$val = $decoded;
				} elseif (!is_array($val) && !is_object($val)) {
					$t = str_replace('{fieldName}', $labelSafe, $msg(226, "{$labelSafe} must be valid JSON."));
					$fail(226, $t);
				}
				break;

			case 'date':
			case 'datetime':
				$format = $type === 'date' ? $rules['format'] : $rules['datetime_format'];
				$dt = \DateTime::createFromFormat($format, (string)$val);
				$isValid = $dt && $dt->format($format) === (string)$val;
				if (!$isValid) {
					$hint = $type === 'date' ? $format : $rules['datetime_format'];
					$t = str_replace('{fieldName}', $labelSafe, $msg(221, "{$labelSafe} must match format {$hint}."));
					$fail(221, $t);
				}
				$asDate = $dt;
				break;

			case 'string':
			default:
				// nothing extra here
				break;
		}

		// ---- regex --------------------------------------------------------------
		if (!empty($rules['regex']) && is_string($val)) {
			if (@preg_match($rules['regex'], '') === false || !preg_match($rules['regex'], $val)) {
				$t = str_replace('{fieldName}', $labelSafe, $msg(224, "{$labelSafe} has an invalid format."));
				$fail(224, $t);
			}
		}

		// ---- whitelist / blacklist ---------------------------------------------
		if (!empty($rules['in'])) {
			if (!in_array($val, (array)$rules['in'], true)) {
				$t = str_replace('{fieldName}', $labelSafe, $msg(222, "{$labelSafe} contains an invalid value."));
				$fail(222, $t, ['allowed' => array_values((array)$rules['in'])]);
			}
		}
		if (!empty($rules['not_in'])) {
			if (in_array($val, (array)$rules['not_in'], true)) {
				$t = str_replace('{fieldName}', $labelSafe, $msg(222, "{$labelSafe} contains a disallowed value."));
				$fail(222, $t);
			}
		}

		// ---- numeric ranges -----------------------------------------------------
		if ($asNumber !== null) {
			if (isset($rules['numeric_min']) && $asNumber < $rules['numeric_min']) {
				$t = str_replace(
					['{fieldName}', '{min}'],
					[$labelSafe, $rules['numeric_min']],
					$msg(223, "{$labelSafe} must be ≥ {min}.")
				);
				$fail(223, $t);
			}
			if (isset($rules['numeric_max']) && $asNumber > $rules['numeric_max']) {
				$t = str_replace(
					['{fieldName}', '{max}'],
					[$labelSafe, $rules['numeric_max']],
					$msg(223, "{$labelSafe} must be ≤ {max}.")
				);
				$fail(223, $t);
			}
		}

		// ---- date ranges --------------------------------------------------------
		if ($asDate instanceof \DateTime) {
			if (!empty($rules['date_min'])) {
				$minD = new \DateTime($rules['date_min']);
				if ($asDate < $minD) {
					$t = str_replace(
						['{fieldName}', '{min}'],
						[$labelSafe, $minD->format('Y-m-d')],
						$msg(225, "{$labelSafe} must be on/after {min}.")
					);
					$fail(225, $t);
				}
			}
			if (!empty($rules['date_max'])) {
				$maxD = new \DateTime($rules['date_max']);
				if ($asDate > $maxD) {
					$t = str_replace(
						['{fieldName}', '{max}'],
						[$labelSafe, $maxD->format('Y-m-d')],
						$msg(225, "{$labelSafe} must be on/before {max}.")
					);
					$fail(225, $t);
				}
			}
		}

		// ---- all good: return sanitized/typed value -----------------------------
		return ($val === '' ? null : $val);
	}

	// public function validate($fieldName = '', $lable = null, $isRequired = false, $minLenth = array(), $maxLenght = array(), $method = 'post')
	// {
	// 	//print $fieldName."\n";// exit;
	// 	if ($method == "post") {
	// 		$t = $this->CI->input->post("{$fieldName}");
	// 		if($t != null){$textCheck = trim($t);}else{$textCheck = null;}
	// 	} else {
	// 		$t = $this->CI->input->get("{$fieldName}");
	// 		if($t != null){$textCheck = trim($t);}else{$textCheck = null;}
	// 	}

	// 	// echo $textCheck;exit();
	// 	// validate only required fields
	// 	if ($isRequired == true) {
	// 		if (!isset($textCheck) || empty($textCheck)) {
	// 			$status['msg'] = str_replace("{fieldName}", $lable, $this->CI->systemmsg->getErrorCode(218));
	// 			$status['statusCode'] = 218;
	// 			$status['data'] = array();
	// 			$status['flag'] = 'F';
	// 			$this->CI->response->output($status, 200);
	// 		}
	// 	}

	// 	// validate min length validation
	// 	if (isset($minLenth) && !empty($minLenth) && $minLenth[0] == true) {

	// 		$textLenth = strlen($textCheck);
	// 		if ($textLenth < $minLenth[1]) {

	// 			$minmsg = str_replace("{fieldName}", $lable, $this->CI->systemmsg->getErrorCode(219));
	// 			$minnumbermsg = str_replace("{minchar}", $minLenth[1], $minmsg);
	// 			$status['msg'] = $minnumbermsg;
	// 			$status['statusCode'] = 219;
	// 			$status['flag'] = 'F';
	// 			$status['data'] = array();
	// 			$this->CI->response->output($status, 200);
	// 		}
	// 	}

	// 	// validate max length validation
	// 	if (isset($maxLenght) && !empty($maxLenght) && $maxLenght[0] == true) {

	// 		$textLenth = strlen($textCheck);
	// 		if ($textLenth > $maxLenght[1]) {

	// 			$maxmsg = str_replace("{fieldName}", $lable, $this->CI->systemmsg->getErrorCode(220));
	// 			$maxnumbermsg = str_replace("{maxchar}", $maxLenght[1], $maxmsg);

	// 			$status['msg'] = $maxnumbermsg;
	// 			$status['statusCode'] = 220;
	// 			$status['data'] = array();
	// 			$status['flag'] = 'F';
	// 			$this->CI->response->output($status, 200);
	// 		}
	// 	}
	// 	//$t = $this->CI->input->post("{$fieldName}");
	// 	return empty($textCheck) ? null : $textCheck;
	// 	//if($t != null){ return trim($t);}else{return null;}
	// }
	public function validateEmail($fieldName = '')
	{
	}
	// how to use
	/*
	// Email, required, max len 100
	$email = $this->validate('email', 'Email', true, [], [true, 100], 'post', [
	'type' => 'email'
	]);

	// Optional URL with regex constraint
	$site = $this->validate('website', 'Website', false, [], [true, 255], 'post', [
	'type' => 'url',
	'regex' => '~^https?://~i'
	]);

	// Integer between 1 and 120
	$age = $this->validate('age', 'Age', true, [], [], 'post', [
	'type' => 'int',
	'numeric_min' => 1,
	'numeric_max' => 120
	]);

	// Date in Y-m-d within range
	$dob = $this->validate('dob', 'Date of Birth', true, [], [], 'post', [
	'type' => 'date',
	'format' => 'Y-m-d',
	'date_min' => '1900-01-01',
	'date_max' => date('Y-m-d')
	]);

	// JSON payload
	$meta = $this->validate('meta', 'Meta', false, [], [], 'post', [
	'type' => 'json'
	]);
	*/
}
