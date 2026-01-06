<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[\AllowDynamicProperties]
class Access
{
	public function __construct()
	{
		$this->CI = &get_instance();
		$this->CI->load->model('LoginModel');
		$this->CI->load->model('CommonModel');
		$this->CI->load->helper('url');
	}
	public function checkTokenKey()
	{
		$header = getallheaders2();
		$sendkey = "";
		if (!isset($header['SadminID']) || empty($header['SadminID'])) {
			$status['msg'] = $this->CI->systemmsg->getErrorCode(994);
			$status['statusCode'] = 994;
			$status['data'] = array();
			$status['flag'] = 'F';
			$this->CI->response->output($status, 200);
		}

		$memberSession = $this->CI->LoginModel->getSessionDetails($header['SadminID']);
		if (!isset($memberSession) || empty($memberSession)) {
			$status['msg'] = $this->CI->systemmsg->getErrorCode(994);
			$status['statusCode'] = 994;
			$status['data'] = array();
			$status['flag'] = 'F';
			$this->CI->response->output($status, 200);
		}

		$defaultCompany = $this->CI->CommonModel->getMasterDetails('admin','default_company,roleID',array('adminID'=>$header['SadminID']));
		if (!isset($defaultCompany) || empty($defaultCompany)) {
			$status['msg'] = $this->CI->systemmsg->getErrorCode(294);
			$status['statusCode'] = 294;
			$status['data'] = array();
			$status['flag'] = 'F';
			$this->CI->response->output($status, 200);
		}else{
			$this->CI->company_id = $defaultCompany[0]->default_company;
			$where = array("infoID" => $defaultCompany[0]->default_company, "status" => "active");
			$infoData = $this->CI->CommonModel->getMasterDetails('info_settings', 'fromEmail,ccEmail,fromName,companyName,record_per_page,status', $where);
			if(isset($infoData) && empty($infoData)){
				$status['data'] = array();
				$status['msg'] = $this->CI->systemmsg->getErrorCode(274);
				$status['statusCode'] = 994;
				$status['flag'] = 'F';
				$this->CI->response->output($status, 200);
			}
			$whereRole = array("roleID" => $defaultCompany[0]->roleID);
			$roleData = $this->CI->CommonModel->getMasterDetails('user_role_master', '', $whereRole);
			if(isset($roleData) && empty($roleData)){
				$this->role_slug = null;
				$status['msg'] = $this->CI->systemmsg->getErrorCode(274);
				$status['statusCode'] = 994;
				$status['data'] = array();
				$status['flag'] = 'F';
				$this->CI->response->output($status, 200);
			}else{
				$this->CI->role_slug = (isset($roleData[0]->slug) && !empty($roleData[0]->slug)) ? $roleData[0]->slug : null ;  
				$this->CI->role_ID = (isset($roleData[0]->roleID) && !empty($roleData[0]->roleID)) ? $roleData[0]->roleID : null ;  
			}
			(isset($infoData[0]->record_per_page) && !empty($infoData[0]->record_per_page)) ? $this->CI->config->config['pagination']['per_page'] = $infoData[0]->record_per_page : $this->CI->config->config['pagination']['per_page'] = '10';
			//(isset($infoData[0]->fromEmail) && !empty($infoData[0]->fromEmail)) ? $this->CI->fromEmail = $infoData[0]->fromEmail : $this->CI->fromEmail = '';
			($infoData[0]->fromEmail != "" || $infoData[0]->fromEmail != null) ? $this->CI->fromEmail = $infoData[0]->fromEmail : $this->CI->fromEmail = $this->CI->config->item('supportEmail') ;
			(isset($infoData[0]->ccEmail) && !empty($infoData[0]->ccEmail)) ? $this->CI->ccEmail = $infoData[0]->ccEmail : $this->CI->ccEmail = '';
			(isset($infoData[0]->fromName) && !empty($infoData[0]->fromName)) ? $this->CI->fromName = $infoData[0]->fromName : $this->CI->fromName = '';
			(isset($infoData[0]->companyName) && !empty($infoData[0]->companyName)) ? $this->CI->companyName = $infoData[0]->companyName : $this->CI->companyName = '';
		}

		$validate = false;
		$this->CI->LoginModel->updateSession($header['SadminID']);
		foreach ($memberSession as $key => $value) {
			$keyecypt = md5($value->sessionKey . $value->adminID);
			$sendkey .= $keyecypt . "\n";
			if ($keyecypt == $header['token']) {
				$validate = true;
				break;
			}
		}
		if (!$validate) {

			 // === NEW: device-bound signature (PoP) ===
			// If you want to enforce only for non-browser/mobile, gate on Origin:
			// $requireSignature = empty($_SERVER['HTTP_ORIGIN']);
			// If you want to enforce for everyone, just set:
			//$requireSignature = true;
			$requireSignature = empty($_SERVER['HTTP_ORIGIN']);

			if ($requireSignature) {
				$this->CI->load->library('core/SignatureAuth');
				$adminId = (int)$header['SadminID'];
				if (!$this->CI->signatureauth->verify($adminId)) {
					$status = [
						'msg' => 'Invalid signature',
						'statusCode' => 401,
						'data' => [],
						'flag' => 'F'
					];
					$this->CI->response->output($status, 200);
				}
			}


			if ($_SERVER['REMOTE_ADDR'] == "183.87.224.83") {
				$path = $_SERVER['DOCUMENT_ROOT'];
				$t = microtime();
				$myfile = fopen($path . "/log/test_" . $header['SadminID'] . ".txt", "a") or die("Unable to open file!");
				$txt = $header['token'] . "\n";
				$txt .= $sendkey . "\n";

				fwrite($myfile, $txt);
				fclose($myfile);
			}

			$status['msg'] = $this->CI->systemmsg->getErrorCode(994);
			$status['statusCode'] = 994;
			$status['data'] = array();
			$status['flag'] = 'F';
			$this->CI->response->output($status, 200);
		}
		$_POST['SadminID'] = $header['SadminID'];
	}
	public function checkaccess($accsessfile = '')
	{

		if ($accsessfile != "VerifyUser") {
			if (!isset($_SESSION['USER']['User_id']) && empty($_SESSION['USER']['User_id'])) {
				redirect("/");
				exit;
			}
		}
	}
	public function checksession()
	{

		if (!isset($_SESSION['USER']['User_id']) && empty($_SESSION['USER']['User_id'])) {
			redirect("/");
			exit;
		}
	}
	public function checkModuleAccess()
	{

		if (!isset($_SESSION['USER']['User_group']) || $_SESSION['USER']['User_group'] != "4") {
			redirect("/logout");
			exit;
		}
	}
	public function Datahashing($string = "")
	{
		return $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($_SESSION['KEY']), $string, MCRYPT_MODE_CFB, md5(md5($_SESSION['KEY']))));
	}
	public function DataDecyrpt($string = "")
	{
		$encrypted = $string;
		$decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($_SESSION['KEY']), base64_decode($encrypted), MCRYPT_MODE_CFB, md5(md5($_SESSION['KEY']))), "\0");
		return $decrypted;
	}
}
