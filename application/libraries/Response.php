<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[\AllowDynamicProperties]
class Response
{
	private $CI;
	private $httpVersion = "HTTP/1.1";
	public $responseType;
	private $requestContentType;
	private $outputType = 'json';
	public function __construct()
	{
		$this->CI = &get_instance();
		$this->setRequestType();
	}
	/*
		* function  	: setHttpHeaders
		* param    		: $contentType,$statusCode
		* Description 	: This function is used to set the response headers.
		*/
	public function setHttpHeaders($contentType = '', $statusCode = '')
	{

		$statusMessage = $this->getHttpStatusMessage($statusCode);
		header($this->httpVersion . " " . $statusCode . " " . $statusMessage);
		header("Content-Type:" . $contentType);
		// header("Content-Type: application/json; charset=utf-8");
	}

	/*
		* function  	: setHttpHeaders
		* param    		: $statusCode
		* Description 	: This function is used to get the status code description.
		*/
	public function getHttpStatusMessage($statusCode = '')
	{
		$httpStatus = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => '(Unused)',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported'
		);
		return ($httpStatus[$statusCode]) ? $httpStatus[$statusCode] : $httpStatus[500];
	}

	/*
		* function  	: output
		* param    		: $data,$statusCode,$type
		* Description 	: This function determine the request type and as per the request type it will send the result to the end user.
		*/
	// public function output($data = array(), $statusCode = '', $type = '')
	// {

	// 	if (!empty($type)) {
	// 		$format = $type;
	// 	} else {
	// 		$format = $this->outputType;
	// 	}

	// 	      log_message('error', 'Accept header (requestContentType): ' . ($this->requestContentType ?? 'NULL'));
    //       log_message('error', 'Resolved output format: ' . $format);
    //         log_message('error', 'Response data: ' . print_r($data, true));

	// 	$checkRequestFrom = $this->checkRequestType($this->requestContentType);
		
	// 	if (!$checkRequestFrom) {
	// 		return false;
	// 	}

	// 	    log_message('error', 'checkRequestType result: ' . ($checkRequestFrom ? 'TRUE' : 'FALSE'));


	// 	$this->setHttpHeaders($this->requestContentType, $statusCode);
	// 	$this->CI->load->library("outputFormats/" . $format);
	// 	$this->$format = new $format();
	// 	$this->$format->senddata($this->requestContentType, $data);
	// }

	public function output($data = array(), $statusCode = '', $type = '')
{
if (!empty($type)) {
			$format = $type;
		} else {
			$format = $this->outputType;
		}

 $checkRequestFrom = $this->checkRequestType($this->requestContentType);
		
		if (!$checkRequestFrom) {
			return false;
		}
    log_message('error', 'checkRequestType result: TRUE');

    // ✅ FIXED CONTENT-TYPE
    $contentType = 'application/json';
    if ($format === 'xml') {
        $contentType = 'application/xml';
    } elseif ($format === 'html') {
        $contentType = 'text/html';
    }

    $this->setHttpHeaders($contentType, $statusCode);

    $this->CI->load->library("outputFormats/" . $format);
    $this->$format = new $format();
    $this->$format->senddata($contentType, $data);
}

	/*
		* function  	: checkRequestType
		* param    		: 
		* Description 	: This function check the request type and send send the response.
		*/
	protected function checkRequestType()
	{
		$accept = $this->requestContentType ?? '';

		if (strpos($accept, 'application/json') !== false) {
			return true;
		}

		if (strpos($accept, 'text/html') !== false) {
			return true;
		}

		if (strpos($accept, 'application/xml') !== false) {
			return true;
		}

		// ✅ Wildcard means "accept anything" – treat as valid
		if (strpos($accept, '*/*') !== false) {
			return true;
		}

		return false; // ❌ No known content type found
	}
	/*
		* function  	: decodeRequest
		* param    		: $setdata,$type
		* Description 	: This function decode the request daa with the request type.
		* 				  $type is optional param. By default response will set as per the request type.
		*/
	public function decodeRequest($setdata = '', $type = '')
	{
		
		$checkRequestFrom = $this->checkRequestType($this->requestContentType);
		if (!$checkRequestFrom) {
			return false;
		}
		
		if (!empty($type)) {
			$format = $type;
		} else {
			$format = $this->outputType;
		}
		
		$this->CI->load->library("outputFormats/" . $format);
		$this->$format = new $format();
		return $this->$format->decode($setdata);
	}

	/*
		* function  	: setRequestType
		* param    		: 
		* Description 	: This function determine the request type and make available to the internal operations.
		*/
	// protected function setRequestType()
	// {
	// 	//print json_encode(array("te"=>$_SERVER['HTTP_ACCEPT']));exit;
	// 	//print $_SERVER['HTTP_ACCEPT'];
	// 	if (isset($_SERVER['HTTP_ACCEPT']) && !empty($_SERVER['HTTP_ACCEPT'])) {
	// 		$this->requestContentType = $_SERVER['HTTP_ACCEPT'];
	// 		$out = explode("/", $_SERVER['HTTP_ACCEPT']);
	// 		if ($out[1] == "html,application")
	// 			$this->outputType = 'xml';
	// 		else
	// 			$this->outputType = trim($out[1]);
	// 	}
	// }
	protected function setRequestType()
	{
		$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

		if (!empty($acceptHeader)) {
			$this->requestContentType = $acceptHeader;

			if (strpos($acceptHeader, 'application/json') !== false) {
				$this->outputType = 'json';
			} elseif (strpos($acceptHeader, 'application/xml') !== false || strpos($acceptHeader, 'text/xml') !== false) {
				$this->outputType = 'xml';
			} elseif (strpos($acceptHeader, 'text/html') !== false) {
				$this->outputType = 'html';
			} else {
				$this->outputType = 'json'; // fallback
			}
		} else {
			$this->outputType = 'json'; // default
		}
		
	}
	public function accesslog($orgID = '', $dirname = '', $linenumber = '', $method = '')
	{
		$data['orgID'] = $orgID;
		$data['directory'] = $dirname;
		$data['lineNumber'] = $linenumber;
		$data['method'] = $method;
		$data['ipAddress'] = $_SERVER['REMOTE_ADDR'];
		$this->CI->db->insert('accesslog', $data);
		return $this->CI->db->insert_id();
	}
	public function outputErrorResponse($errorCode='',$status = array()) {
		if (isset($errorCode) && empty($status)) {
			$status['msg'] = $this->CI->systemmsg->getErrorCode($errorCode);
			$status['statusCode'] = $errorCode;
			$status['data'] = array();
			$status['flag'] = 'F';
		}
		if (!isset($status) && empty($status)) {
			$status['msg'] = $this->CI->systemmsg->getErrorCode(325);
			$status['statusCode'] = 325;
			$status['data'] = array();
			$status['flag'] = 'F';
			$this->output($status, 200);
		}else{
			$this->output($status, 200);
		}
	}
}
