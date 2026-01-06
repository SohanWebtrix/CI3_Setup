<?php
defined('BASEPATH') OR exit('No direct script access allowed');

	if(!function_exists('dateFormat')){

		function dateFormat($date,$format='Y-m-d')
		{
			if($date != "0000-00-00 00:00:00" && $date != "0000-00-00" && $date != null && $date != "") {
	      		return date("{$format}",strtotime($date));
	        }else
	        {
	            return null;
	        }
		}
	}
	function getFirstAndLastWordInitials($name) {
		// Remove extra spaces
		$name = trim($name);
	
		// Break the string into an array of words
		$words = explode(" ", $name);
	
		// Get the first and last word
		$firstWord = $words[0];
		$lastWord = end($words);
	
		// Get the first letter of the first and last word
		$firstLetter = strtoupper($firstWord[0]);
		$lastLetter = strtoupper($lastWord[0]);
	
		return $firstLetter . $lastLetter;
	}
	function formatString($string) {
		// Replace underscores with spaces
		$string = str_replace('_', ' ', $string);
		// Capitalize the first letter of each word
		$string = ucwords($string);
		return $string;
	}

	function breakPoint($printStack = [] , $brecking = false){
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
		$line = $bt['line'];
		$file = $bt['file'];
		echo "<pre style='background:#f0f0f0;padding:10px;border-left:5px solid #007bff;'>";
		echo "Debug at <strong>$file</strong> on line <strong>$line</strong>:\n";
		print_r('<pre>');
		foreach ($printStack as $key => $value) {
			print_r($value);
			print_r('<br>');
			print_r('<p>&nbsp;</p>');
		}
		if ($brecking) {
			exit;
		}
	}
// Put these in a common place (e.g., your controller base or a small helper class)

/**
 * Turn a raw payload value into an override spec that FilterBuilder understands.
 * Rules:
 * - empty_token (e.g., "nostatus")  => ['condition'=>'is_empty']
 * - "" (empty string)               => null (skip)
 * - "!a,b" (when allow_not_prefix)  => ['condition'=>'not_in','value'=>['a','b']]
 * - "a,b"                           => ['condition'=>'in','value'=>['a','b']]
 * - scalar                          => "a"  (shorthand => equal_to)
 * - cast_ints: cast numeric tokens to int
 */
function normalizeOverride($raw, array $opts = []) {
    $emptyToken     = $opts['empty_token']      ?? null;   // e.g. 'nostatus'
    $allowNotPrefix = $opts['allow_not_prefix'] ?? true;   // handle leading '!'
    $castInts       = $opts['cast_ints']        ?? true;   // cast digits to int
    $delimiter      = $opts['delimiter']        ?? ',';    // CSV splitter

    if ($raw === null) return null;

    // Preserve arrays as-is (assume caller wants IN/NOT_IN etc.)
    if (is_array($raw)) {
        if ($castInts) {
            $raw = array_map(function($v){ return ctype_digit((string)$v) ? (int)$v : $v; }, $raw);
        }
        return $raw; // FilterBuilder will turn ['a','b'] under 'in' later if you set it
    }

    $val = trim((string)$raw);

    if ($emptyToken !== null && strcasecmp($val, $emptyToken) === 0) {
        return ['condition' => 'is_empty'];
    }
    if ($val === '') return null;

    // NOT IN via prefix "!"
    if ($allowNotPrefix && $val[0] === '!' && strlen($val) > 1) {
        $list = substr($val, 1);
        $arr  = preg_split('/\s*'.preg_quote($delimiter,'/').'\s*/', $list, -1, PREG_SPLIT_NO_EMPTY);
        if ($castInts) {
            $arr = array_map(function($v){ return ctype_digit($v) ? (int)$v : $v; }, $arr);
        }
        return ['condition' => 'not_in', 'value' => $arr];
    }

    // IN if CSV
    if (strpos($val, $delimiter) !== false) {
        $arr = preg_split('/\s*'.preg_quote($delimiter,'/').'\s*/', $val, -1, PREG_SPLIT_NO_EMPTY);
        if ($castInts) {
            $arr = array_map(function($v){ return ctype_digit($v) ? (int)$v : $v; }, $arr);
        }
        return ['condition' => 'in', 'value' => $arr];
    }

    // Single scalar → shorthand equal_to
    if ($castInts && ctype_digit($val)) return (int)$val;
    return $val;
}

/** Convenience: if payload has $key, add normalized override to $overrides. */
function overrideFromPayload(array &$overrides, array $payload, string $key, array $opts = []) : void {
    if (!array_key_exists($key, $payload)) return;
    $spec = normalizeOverride($payload[$key], $opts);
    if ($spec !== null) $overrides[$key] = $spec;
}
function arrayToObject($array) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = arrayToObject($value);
			}
		}
		return (object) $array;
	}

	if (!function_exists('capitalize_words')) {
    function capitalize_words(string $str, string $encoding = 'UTF-8'): string
    {
        if ($str === '') return $str;

        // Normalize to lowercase first (multibyte-safe)
        $s = mb_strtolower($str, $encoding);

        // Treat these as word boundaries
        $delims = " \t\r\n\f\v\-_./–—";

        // Uppercase any letter that starts the string OR follows a delimiter
        $pattern = '/(^|[' . preg_quote($delims, '/') . '])(\p{L})/u';

        return preg_replace_callback($pattern, function ($m) use ($encoding) {
            return $m[1] . mb_strtoupper($m[2], $encoding);
        }, $s);
    }
}

/**
 * Capitalize ONLY the very first character of the string (leave the rest as-is).
 */
if (!function_exists('capitalize_first')) {
    function capitalize_first(string $str, string $encoding = 'UTF-8'): string
    {
        if ($str === '') return $str;
        $first = mb_substr($str, 0, 1, $encoding);
        $rest  = mb_substr($str, 1, null, $encoding);
        return mb_strtoupper($first, $encoding) . $rest;
    }
}

	if (!function_exists('capitalize_words')) {
    function capitalize_words(string $str, string $encoding = 'UTF-8'): string
    {
        if ($str === '') return $str;

        // Normalize to lowercase first (multibyte-safe)
        $s = mb_strtolower($str, $encoding);

        // Treat these as word boundaries
        $delims = " \t\r\n\f\v\-_./–—";

        // Uppercase any letter that starts the string OR follows a delimiter
        $pattern = '/(^|[' . preg_quote($delims, '/') . '])(\p{L})/u';

        return preg_replace_callback($pattern, function ($m) use ($encoding) {
            return $m[1] . mb_strtoupper($m[2], $encoding);
        }, $s);
    }
}

/**
 * Capitalize ONLY the very first character of the string (leave the rest as-is).
 */
if (!function_exists('capitalize_first')) {
    function capitalize_first(string $str, string $encoding = 'UTF-8'): string
    {
        if ($str === '') return $str;
        $first = mb_substr($str, 0, 1, $encoding);
        $rest  = mb_substr($str, 1, null, $encoding);
        return mb_strtoupper($first, $encoding) . $rest;
    }
}

if (!function_exists('getCurrentSubdomain')) {
    function getCurrentSubdomain() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = strtolower($host);
        $parts = explode('.', $host);
        return (count($parts) >= 3) ? $parts[0] : null;
    }
}
if (!function_exists('ensureMediaPath')) {
    function validateMediaPath(string $path = null): array
    {
        if (empty($path)) {
            return [
                'flag'       => 'F',
                'statusCode' => 227,
                'msg'        => 'File system configuration missing'
            ];
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            return [
                'flag'       => 'F',
                'statusCode' => 228,
                'msg'        => 'Media upload directory does not exist'
            ];
        }

        if (!is_dir($realPath)) {
            return [
                'flag'       => 'F',
                'statusCode' => 229,
                'msg'        => 'Media path is not a directory'
            ];
        }

        if (!is_writable($realPath)) {
            return [
                'flag'       => 'F',
                'statusCode' => 230,
                'msg'        => 'Media directory is not writable'
            ];
        }

        return [
            'flag' => 'S',
            'path' => rtrim($realPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        ];
    }
}

function getTenantKey(): array
{
    $CI = &get_instance();
    static $cache = null;

    if ($cache !== null) {
        return [
            'flag' => 'S',
            'tenant_key' => $cache
        ];
    }

    $row = $CI->db
        ->select('tenant_key')
        ->limit(1)
        ->get('tenant_details')
        ->row();

    if (!$row || empty($row->tenant_key)) {
        return [
            'flag'       => 'F',
            'statusCode' => 227,
            'msg'        => 'Tenant configuration missing'
        ];
    }

    $cache = $row->tenant_key;

    return [
        'flag' => 'S',
        'tenant_key' => $cache
    ];
}


function getCompanyKey($company_id=''): array
{
    $CI = &get_instance();
    static $cache = null;

    if ($cache !== null) {
        return [
            'flag' => 'S',
            'company_key' => $cache
        ];
    }

    // Prefer session / context variable
    $companyId = $company_id ?? $CI->company_id;

    if (empty($companyId)) {
        return [
            'flag'       => 'F',
            'statusCode' => 231,
            'msg'        => 'Company not selected'
        ];
    }

    $row = $CI->db
        ->select('company_key')
        ->where('infoID', $companyId)
        ->limit(1)
        ->get('info_settings')
        ->row();

    if (!$row || empty($row->company_key)) {
        return [
            'flag'       => 'F',
            'statusCode' => 232,
            'msg'        => 'Invalid company'
        ];
    }

    $cache = $row->company_key;

    return [
        'flag' => 'S',
        'company_key' => $cache
    ];
}


function ensureTenantMediaPath(
    string $basePath = null,
    string $tenantKey = null,
    string $companyKey = null,
    string $type = 'files'
): array {

    // 1️⃣ Validate base path
    if (empty($basePath)) {
        return [
            'flag' => 'F',
            'statusCode' => 227,
            'msg' => 'File system configuration missing'
        ];
    }

    $baseReal = realpath($basePath);

    if ($baseReal === false || !is_dir($baseReal)) {
        return [
            'flag' => 'F',
            'statusCode' => 228,
            'msg' => 'Base media directory does not exist'
        ];
    }

    // Base must be writable
    if (!is_writable($baseReal)) {
        return [
            'flag' => 'F',
            'statusCode' => 229,
            'msg' => 'Base media directory is not writable'
        ];
    }

    // 2️⃣ Validate tenant & company
    if (empty($tenantKey) || empty($companyKey)) {
        return [
            'flag' => 'F',
            'statusCode' => 231,
            'msg' => 'Tenant or company context missing'
        ];
    }

    // 3️⃣ Build path
    $path = rtrim($baseReal, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . $tenantKey
        . DIRECTORY_SEPARATOR . $companyKey
        . DIRECTORY_SEPARATOR . $type
        . DIRECTORY_SEPARATOR;

    // 4️⃣ Create directory if missing
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            return [
                'flag' => 'F',
                'statusCode' => 232,
                'msg' => 'Failed to create upload directory'
            ];
        }
    }

    // 5️⃣ Fix permission if not writable
    if (!is_writable($path)) {
        // Try safe chmod
        @chmod($path, 0755);

        // Re-check
        if (!is_writable($path)) {
            return [
                'flag' => 'F',
                'statusCode' => 233,
                'msg' => 'Upload directory is not writable. Please check server permissions.'
            ];
        }
    }

    // 6️⃣ Final safety: path traversal protection
    $realFinal = realpath($path);
    if ($realFinal === false || strpos($realFinal, $baseReal) !== 0) {
        return [
            'flag' => 'F',
            'statusCode' => 234,
            'msg' => 'Invalid upload path detected'
        ];
    }

    return [
        'flag' => 'S',
        'path' => $realFinal . DIRECTORY_SEPARATOR
    ];
}

if (!function_exists('buildR2Key')) {
     function buildR2Key(string $fileName,int $companyId): string
    {
        $tenant = getTenantKey();
        $company = getCompanyKey($companyId);

        return
            "tenant_{$tenant['tenant_key']}/".
            "company_{$company['company_key']}/files/".
            $fileName;
    }
}
?>