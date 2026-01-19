<?php
defined('BASEPATH') or exit('No direct script access allowed');

// this file is required to get the methods details.
require_once("OutPut.php");
#[\AllowDynamicProperties]
class Json extends OutPut
{

	public function __construct()
	{
		$this->CI = &get_instance();
		$this->CI->load->helper('form');
	}

	public function senddata($requestContentType = '', $data = '')
	{
		if (strpos($requestContentType,'*/*') !== false) {
			$response = json_encode($data);
			echo $response;
		}else if (strpos($requestContentType, 'application/json') !== false) {
			$response = json_encode($data);
			echo $response;
			exit();
		}
		exit();
	}

	public function decode($setdata = '')
{
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    $jsonString = file_get_contents("php://input");

    // Decode JSON or fallback to $_POST or 'data' param
    if (stripos($contentType, 'application/json') !== false) {
        $REQUESTDATA = json_decode($jsonString, true) ?? [];
    } elseif (!empty($_POST['data'])) {
        $REQUESTDATA = json_decode($_POST['data'], true) ?? [];
    } else {
        $REQUESTDATA = $_POST;
    }

    // Optionally add headers to $_POST
    $header = function_exists('getallheaders2') ? getallheaders2() : [];
    $_POST['SadminID'] = !empty($header['SadminID']) ? $header['SadminID'] : "";
    $_POST['accessFrom'] = "mobile";

    // Only assign top-level fields to $_POST (nested arrays/objects remain as arrays in $_POST)
    foreach ($REQUESTDATA as $key => $value) {
        $_POST[$key] = $value;
    }

    // Optionally return array/object
    if ($setdata === 'array') return $_POST;
    if ($setdata === 'object') return (object)$_POST;
    return true;
}

	// Recursive flatten utility for nested arrays
	private function array_flatten_with_keys($array, $prefix = '')
	{
		$result = [];
		foreach ($array as $key => $value) {
			$new_key = $prefix ? "{$prefix}_{$key}" : $key;
			if (is_array($value)) {
				$result += $this->array_flatten_with_keys($value, $new_key);
			} else {
				$result[$new_key] = $value;
			}
		}
		return $result;
	}
}
