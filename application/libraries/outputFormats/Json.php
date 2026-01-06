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
	/*
		* function  	: senddata
		* param    		: $requestContentType,$data
		* Description 	: This function encode the data in json and response to the client request.
		*/
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
	/*
		* function  	: decode
		* param    		: $setdata
		* Description 	: This function decode the json data and set data to post array or new array as per the setdata type.
		*/
	// public function decode($setdata = '')
	// {
		
	// 	//print json_encode(array("te"=>"fs"));exit;
	// 	$header = getallheaders2();

	// 	$method = $this->CI->input->method(TRUE);
	// 	$REQUESTDATA = array();
	// 	switch ($method) {
	// 		case 'POST': {
	// 			$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
	// 			$jsonString = file_get_contents("php://input");

	// 			if (stripos($contentType, 'application/json') !== false) {
	// 				$REQUESTDATA = json_decode($jsonString, true); // decode as array
	// 			} elseif (!empty($_POST['data'])) {
	// 				// Handle: multipart/form-data with JSON string in 'data' field
	// 				$REQUESTDATA = json_decode($_POST['data'], true);
	// 				if (json_last_error() !== JSON_ERROR_NONE) {
	// 					$REQUESTDATA = [];
	// 				}
	// 			} else {
	// 				// fallback to $_POST or URL-encoded raw input
	// 				$REQUESTDATA = $_POST;
	// 				$jsonString = file_get_contents("php://input");
	// 				$REQUESTDATA = json_decode(trim($jsonString));

	// 				if (!isset($REQUESTDATA) || empty($REQUESTDATA)) {
	// 					parse_str(file_get_contents("php://input"), $REQUESTDATA);
	// 				}
	// 			}
	// 			//
	// 		}
	// 		break;
	// 		case 'GET': {
	// 				$REQUESTDATA = $_GET;
	// 			}
	// 			break;
	// 		case 'DELETE': {
	// 				//$jsonString = file_get_contents("php://input");
	// 				$REQUESTDATA = $_GET;
	// 				//$REQUESTDATA = json_decode($jsonString);
	// 				//var_dump($jsonString);
	// 			}
	// 			break;
	// 		case 'PUT': {
	// 				//$jsonString = file_get_contents("php://input");
	// 				//var_dump($jsonString); exit;
	// 				$jsonString = file_get_contents("php://input");
	// 				$REQUESTDATA = json_decode(trim($jsonString));

	// 				if (!isset($REQUESTDATA) || empty($REQUESTDATA)) {
	// 					parse_str(file_get_contents("php://input"), $REQUESTDATA);
	// 				}
	// 			}
	// 			break;
	// 		default: {
	// 			}
	// 			break;
	// 	}
	// 	if (isset($header['SadminID']) && !empty($header['SadminID'])) {
	// 		$_POST['SadminID'] = $header['SadminID'];
	// 	} else {
	// 		$_POST['SadminID'] = "";
	// 	}
	// 	foreach ($REQUESTDATA as $key => $value) {
	// 		if (is_array($value) || is_object($value)) {
	// 			foreach ($value as $subSuperKey => $valueSubSuperIndex) {
	// 				if (is_array($valueSubSuperIndex) || is_object($valueSubSuperIndex)) {
	// 					foreach ($valueSubSuperIndex as $subkey => $valuesubindex) {
	// 						$_POST[$key . "_" . $subSuperKey . "_" . $subkey] = $valuesubindex;
	// 					}
	// 				} else {
	// 					$_POST[$key] = $valueSubSuperIndex;
	// 				}
	// 			}
	// 		} else {
	// 			$_POST[$key] = $value;
	// 		}
	// 		$_POST["accessFrom"] = "mobile";
	// 		$_POST[$key] = $value;
	// 	}
		
	// 	if (!empty($_POST['data'])) {
	// 		$res = json_decode($_POST['data']);
	// 	} elseif (!empty($REQUESTDATA)) {
	// 		$res = (object) $REQUESTDATA; // force array/object to match previous behavior
	// 	} else {
	// 		return true;
	// 	}
		
		
	// 	$responsearray = array();
	// 	switch ($setdata) {
	// 		case 'array': {
	// 				foreach ($res as $key => $value) {
	// 					$responsearray[$key] = $value;
	// 				}
	// 				$responsearray["accessFrom"] = "mobile";
	// 				return $responsearray;
	// 			}
	// 			break;

	// 		default: {
	// 				foreach ($res as $key => $value) {
	// 					if (is_array($value) || is_object($value)) {
	// 						foreach ($value as $subSuperKey => $valueSubSuperIndex) {
	// 							if (is_array($valueSubSuperIndex) || is_object($valueSubSuperIndex)) {
	// 								foreach ($valueSubSuperIndex as $subkey => $valuesubindex) {
	// 									$_POST[$key . "_" . $subSuperKey . "_" . $subkey] = $valuesubindex;
	// 								}
	// 							} else {
	// 								$_POST[$key] = $valueSubSuperIndex;
	// 							}
	// 						}
	// 					} else {
	// 						$_POST[$key] = $value;
	// 					}
	// 					$_POST["accessFrom"] = "mobile";
	// 					$_POST[$key] = $value;
	// 				}
	// 				return true;
	// 			}
	// 			break;
	// 	}
	// }
	// updated with new code kiran.
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
