<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[\AllowDynamicProperties]
class Errorlogs extends CI_Log
{
	public function __construct(){
		parent::__construct();
		$this->CI = &get_instance();
	}
	public function catchError($description = '', $heading = '', $filename = '', $lineNumber = '', $functionName = ''){
		$errorDetails['description'] = $description;
		$errorDetails['heading'] = $heading;
		$errorDetails['file'] = $filename;
		$errorDetails['loginUser'] =  '1';
		$errorDetails['lineNumber'] =  $lineNumber;
		$errorDetails['function'] =  $functionName;
		$errorDetails['deviceCall'] =  $_SERVER['HTTP_USER_AGENT'];
		$errorDetails['ipAddress'] =  $_SERVER['REMOTE_ADDR'];
		$isinsert = $this->CI->db->insert('error_logs', $errorDetails);
	}
	function write_log($level = 'error', $msg = '', $php_error = FALSE){
		if ($result == TRUE && strtoupper($level) == 'ERROR') {
			$message = "An error occurred: \n\n";
			$message .= $level . ' - ' . date($this->_date_fmt) . ' --> ' . $msg . "\n";
			$errorDetails['description'] = $message;
			$errorDetails['heading'] = $level;
			$errorDetails['file'] = '';
			$errorDetails['loginUser'] =  '1';
			$errorDetails['lineNumber'] = '';
			$errorDetails['function'] =  '';
			$errorDetails['deviceCall'] =  $_SERVER['HTTP_USER_AGENT'];
			$errorDetails['ipAddress'] =  $_SERVER['REMOTE_ADDR'];
			$isinsert = $this->CI->db->insert('error_logs', $errorDetails);
		}
	}
	public function checkDBError($error = '',$heading= '', $dir = '', $line = '', $method = ''){
		if ($error['code'] != '' && $error['message'] != '') {
			$message = "Code : {$error['code']} <br> Message : {$error['message']}";
			$this->catchError($message, $heading, $dir, $line, $method);
			$status['msg'] = "System Error : ".$error['message'].". Please contact support team.";
			$status['flag'] = 'F';	
			$status['data'] = array();
			$status['statusCode'] = '995';
			$this->CI->response->output($status, 200);
		} else {
			return true;
		}
	}
}
