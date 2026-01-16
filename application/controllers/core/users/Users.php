<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Users extends CI_Controller
{

    /**
     * Index Page for this controller.
     *
     * Maps to the following URL
     *         http://example.com/index.php/welcome
     *    - or -
     *         http://example.com/index.php/welcome/index
     *    - or -
     * Since this controller is set as the default controller in
     * config/routes.php, it's displayed at http://example.com/
     *
     * So any other public methods not prefixed with an underscore will
     * map to /index.php/welcome/<method_name>
     * @see https://codeigniter.com/user_guide/general/urls.html
     */
    public $fromEmail = null;
    public $fromName = null;
    protected $columnNames = [
        "roleID" => ["table" => "user_role_master", "alias" => "r", "column" => "roleName", "key2" => "roleID", "select" => "t.roleID as roleID, r.roleName as roleName"],
    ];

    protected $customCol = [
        "default_company" => ["table" => "info_settings", "alias" => "dc", "column" => "companyName", "key2" => "infoID", "select" => ""],
        "modified_by" => ["table" => "admin", "alias" => "am", "column" => "name", "key2" => "adminID", "select" => ""],
        "created_by" => ["table" => "admin", "alias" => "ad", "column" => "name", "key2" => "adminID", "select" => ""],
    ];
    protected $Model;
    public $menuID;
    var $defaultColumns = ["name", "adminID", "email"];
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper('form');
        $this->load->model('SearchAdminModel');
        $this->load->model('CommonModel');
        $this->load->model('LoginModel');
        $this->load->library("pagination");

        $this->load->helper(['url', 'security']);
        $this->load->model('core/CommonModelNew');
        $this->Model = $this->CommonModelNew;
        $this->load->library("ValidateData");
        $this->load->library('core/FilterEngine', [], 'filterengine');
        $this->load->library('core/FilterBuilder', [], 'filterbuilder');
        $this->load->library('Filters', ['customCol' => $this->customCol, 'columnNames' => $this->columnNames], 'filters');
        if (!$this->config->item('development')) {
            // $this->load->library("Emails");
        }

        //  $this->load->library('Response');
        // $this->load->library('Access'); 
        // $this->load->library("Datatables");
        $this->load->library("Filters");
    }


    public function list()
    {
        //$this->access->checkTokenKey();
        $payload = json_decode($this->input->raw_input_stream, true);
        if (!is_array($payload)) $payload = $this->input->post() ?: [];

        log_message('error', 'PAYLOAD: ' . print_r($payload, true));

        $menuId = (int)($payload['menuId'] ?? 0);
        if ($menuId <= 0) {
            $this->response->output(['flag' => 'F', 'msg' => 'menuId is required', 'statusCode' => 422], 200);
            return;
        }

        // Logged-in user
        $userId = $this->input->get_request_header('SadminID', true);
        if (!$userId) $userId = $payload['SadminID'] ?? $this->input->post('SadminID');

        // Menu meta + PK
        $menuMeta = $this->Model->getMenuMeta($menuId);
        if (!$menuMeta) {
            $this->response->output(['flag' => 'F', 'msg' => 'Invalid menu', 'statusCode' => 422], 200);
            return;
        }
        $pk = $menuMeta['pk'];

        // Columns from user settings (fallbacks preserved)
        $columns = $this->Model->getUserSelectedColumns($menuId, (string)$userId, $pk);
        // CASE 1: If NO columns saved → use payload or default
        if (empty($columns)) {

            if (!empty($payload['columns']) && is_array($payload['columns'])) {
                $columns = $payload['columns'];

                // Ensure PK exists
                if ($pk && !in_array($pk, $columns, true)) {
                    array_unshift($columns, $pk);
                }
            } else {
                // Default full
                $columns = [$pk];
            }
        }

        // CASE 2: Only PK exists → treat as empty and load defaults
        else if (count($columns) === 1 && $pk && $columns[0] === $pk) {
            $columns = [$pk];
        }

        // CASE 3: User saved columns but forgot PK → prepend
        else {
            if ($pk && !in_array($pk, $columns, true)) {
                array_unshift($columns, $pk);
            }
        }


        //'completed_subtask_count','subtask_progress_percent'
        $required = ['shop_name', 'shop_email', 'shop_owner', 'status', 'created_date'];
        // Merge in correct order → Required first, then PK, then user columns
        $columns = array_unique(array_merge($required, $columns));

        $filters  = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
        // Collect overrides from payload
        $overrides = [];

        overrideFromPayload($overrides, $payload, 'task_status', [
            'empty_token' => 'nostatus',
            'cast_ints'   => true,
        ]);

        // type, status, company_id behave the same way (reusable!)
        overrideFromPayload($overrides, $payload, 'type');
        overrideFromPayload($overrides, $payload, 'status');
        $overrides['status'] = [
            'condition' => 'in',
            'value' => ['active', 'inactive']
        ];
        overrideFromPayload($overrides, $payload, 'company_id', ['cast_ints' => true]);
        //print_r($overrides);
        // Apply forced filters
        $filters = $this->filterbuilder->applyOverrideFilters($filters, $overrides);

        $freeTxt  = isset($payload['freeTextSearch']) ? trim((string)$payload['freeTextSearch']) : '';
        if (isset($payload['order'], $payload['orderBy'])) {
            // ensure sort is an array
            if (!isset($payload['sort']) || !is_array($payload['sort'])) {
                $payload['sort'] = [];
            }
            $payload['sort']['by']  = (string) $payload['orderBy'];
            $dir = strtoupper((string) $payload['order']);
            $payload['sort']['dir'] = ($dir === 'ASC') ? 'ASC' : 'DESC';
        }
        // Use it (with your default)
        $sort = $payload['sort'] ?? ['by' => 'created_date', 'dir' => 'DESC'];

        //$sort     = isset($payload['sort']) && is_array($payload['sort']) ? $payload['sort'] : ['by'=>'created_date','dir'=>'DESC'];

        $curPageIdx = isset($payload['curpage'])
            ? max(0, (int)$payload['curpage'])                                  // 0,1,2...
            : (isset($payload['page']) ? max(0, (int)$payload['page'] - 1) : 0); // compat for old 1-based 'page'

        $limit  = min(200, max(1, (int)($payload['limit'] ?? 20)));
        $offset = $curPageIdx * $limit;

        $plan  = $this->filterengine->buildPlan($menuId, $columns, $filters, $freeTxt, $sort, $this->columnNames, $this->customCol);
        $total = $this->Model->countByPlan($plan, $plan['pk']);
        $rows  = $this->Model->listByPlan($plan, $limit, $offset);

        $debugSql = $this->Model->compilePlanSQL($plan);

        //print $debugSql;exit;

        $totalPages = ($limit > 0) ? (int)ceil($total / $limit) : 1;
        $hasMore    = ($curPageIdx + 1) < $totalPages;

        $start = ($total === 0) ? 0 : $offset;                      // zero-based start index
        $end   = ($total === 0) ? 0 : min($offset + $limit, $total); // zero-based exclusive end

        $status = [];
        $status['data'] = $rows;
        $status['paginginfo'] = [
            "curPage"      => $curPageIdx,                              // 0-based current page
            "prevPage"     => ($curPageIdx > 0) ? ($curPageIdx - 1) : 0,
            "pageLimit"    => $limit,
            "nextpage"     => $hasMore ? ($curPageIdx + 1) : 0,         // 0 when no next page
            "totalRecords" => $total,
            "start"        => $start,                                   // 0-based
            "end"          => $end                                      // 0-based exclusive
        ];

        // Keep your flags/messages; if you prefer 200 even on last page, set statusCode=200 here.
        if ($total <= $end) {
            $status['msg'] = 'No more records';
            $status['statusCode'] = 400;   // <-- if frontend expects this in JSON; else set 200
            $status['flag'] = 'S';
            $status['loadstate'] = false;
        } else {
            $status['msg'] = 'OK';
            $status['statusCode'] = 200;
            $status['flag'] = 'S';
            $status['loadstate'] = true;
        }

        $this->response->output($status, 200);
    }


    public function getCompanyDetails($adminID)

    {
        if (!isset($this->company_id) && empty($this->company_id)) {
            $defaultCompany = $this->CommonModel->getMasterDetails('admin', 'default_company', array('adminID' => $adminID));
            if (!isset($defaultCompany) && empty($defaultCompany)) {
                $status['msg'] = $this->systemmsg->getErrorCode(294);
                $status['statusCode'] = 294;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            }

            $where = array("infoID" => $defaultCompany[0]->default_company);
            $infoData = $this->CommonModel->getMasterDetails('info_settings', '', $where);
            ($infoData[0]->fromEmail != "" || $infoData[0]->fromEmail != null) ? $this->fromEmail = $infoData[0]->fromEmail : $this->fromEmail = $this->config->item('supportEmail');
            ($infoData[0]->ccEmail) ? $this->ccEmail = $infoData[0]->ccEmail : $this->ccEmail = '';
            ($this->validateInfoDetails($infoData[0]->fromName, 'From Name')) ? $this->fromName = $infoData[0]->fromName : $this->fromName = '';
            ($this->validateInfoDetails($infoData[0]->companyName, 'companyName')) ? $this->companyName = $infoData[0]->companyName : $this->companyName = '';
        }
    }
    public function validateInfoDetails($field, $lable)
    {

        if (!isset($field) || empty($field)) {
            $status['msg'] = str_replace("{fieldName}", $lable, $this->systemmsg->getErrorCode(335));
            $status['statusCode'] = 335;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            return true;
        }
    }

    public function userDetails($adminID = '')
    {

        // $this->access->checkTokenKey();
        $this->response->decodeRequest();
        $this->menuID = $this->input->post('menuId');
        if ($this->menuID == "") {
            $this->menuID = $this->input->get('menuId');
        }

        $method = $this->input->method(true);
        $today = date("Y-m-d");
        if ($method == "POST" || $method == "PUT") {

            $adminDetails = $adminEextraDetails = array();
            $updateDate = date("Y/m/d H:i:s");
            $adminDetails['name'] = $this->validatedata->validate('name', 'Admin Name', true, '', array());
            $adminDetails['userName'] = $this->validatedata->validate('userName', 'User Name', true, '', array());
            $adminDetails['email'] = $this->validatedata->validate('email', 'Email-Id', true, '', array());
            $adminDetails['roleID'] = $this->validatedata->validate('roleID', 'User Role', true, '', array());
            $wherec = array("roleID" => $adminDetails['roleID']);
            $roleDetails = $this->CommonModel->getMasterDetails("user_role_master", $select = "role", $wherec);
            // $adminDetails['roleOfUser'] = $roleDetails[0]->role;
            $adminDetails['created_date'] = $updateDate;
            $adminDetails['status'] = $this->validatedata->validate('status', 'status', true, '', array());
            $adminDetails['is_approver'] = $this->validatedata->validate('is_approver', 'is_approver', true, '', array());
            $adminDetails['otp'] = rand(100000, 999999);
            $adminDetails['otp_exp_time'] = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $adminDetails['address'] = $this->validatedata->validate('address', 'Address', false, '', array());
            $adminDetails['contactNo'] = $this->validatedata->validate('contactNo', 'Contact No', false, '', array());
            $adminDetails['whatsappNo'] = $this->validatedata->validate('whatsappNo', 'Whatsapp No', false, '', array());
            $adminDetails['dateOfBirth'] = $this->validatedata->validate('dateOfBirth', 'Date Of Birth', false, '', array());
            $adminDetails['name'] = $this->validatedata->validate('name', 'Admin Name', true, '', array());
            $adminDetails['userName'] = $this->validatedata->validate('userName', 'User Name', true, '', array());
            $adminDetails['email'] = $this->validatedata->validate('email', 'Email-Id', true, '', array());
            $adminDetails['is_approver'] = $this->validatedata->validate('is_approver', 'is_approver', true, '', array());
            $adminDetails['roleID'] = $this->validatedata->validate('roleID', 'User Role', true, '', array());
            $adminDetails['company_id'] = $this->validatedata->validate('company_id', 'Company/Branch', true, '', array());
            $adminDetails['country_code'] = $this->validatedata->validate('country_code', 'Country Code', false, '', array());
            $adminDetails['google_location'] = $this->validatedata->validate('google_location', 'Google Location', false, '', array());
            $adminDetails['latitude'] = $this->validatedata->validate('latitude', 'Latitude', false, '', array());
            $adminDetails['longitude'] = $this->validatedata->validate('longitude', 'Longitude', false, '', array());
            $adminDetails['default_company'] = $this->validatedata->validate('default_company', 'Default Company', false, '', array());

            if (isset($adminDetails['dateOfBirth']) && !empty($adminDetails['dateOfBirth']) && $adminDetails['dateOfBirth'] != "0000-00-00") {
                $adminDetails['dateOfBirth'] = str_replace("-", "-", $adminDetails['dateOfBirth']);
                $adminDetails['dateOfBirth'] = date("Y-m-d", strtotime($adminDetails['dateOfBirth']));
            } 
            else {
                $adminDetails['dateOfBirth'] = null;
            }

            $countryCodeNumber = $this->input->post('whatsappCountryCodeNumber');
            if (isset($countryCodeNumber) && !empty($countryCodeNumber)) {
                $countryarray = explode(" ", $countryCodeNumber);
                $mobNumberArray = explode(" ", $adminDetails['whatsappNo']);
                $formattedNumber = $countryarray[0] . '-' . $mobNumberArray[0];
                $adminDetails['whatsappNo'] = $formattedNumber;
            }
            $adminDetails['time_zone'] = $this->validatedata->validate('time_zone', 'time zone', false, '', array());
            if (isset($adminDetails['company_id']) && !empty($adminDetails['company_id'])) {
                $cmpArr = explode(',', $adminDetails['company_id']);
                (in_array($this->company_id, $cmpArr)) ? $adminDetails['default_company'] = $this->company_id : $adminDetails['default_company'] = $cmpArr[0];
            }
            // $menuDetails = $this->datatables->getMenuDetails($this->menuID);
            // $fieldData = $this->datatables->mapDynamicFeilds($menuDetails->menuLink, $this->input->post());
            switch ($method) {
                case "PUT": {
                        //$adminDetails['password'] = $this->validatedata->validate('password', 'Password', false, '', array());
                        $adminDetails['isVerified'] = 'N';
                        $adminDetails['created_by'] = $this->input->post('SadminID');
                        $this->db->trans_start();
                        $where = array("email" => $adminDetails['email']);
                        $userEmail = $this->CommonModel->getMasterDetails('admin', '', $where);
                        if (isset($adminDetails['contactNo']) && !empty($adminDetails['contactNo'])) 
                            {
                            $where1 = array("contactNo" => $adminDetails['contactNo']);
                            $userMobile = $this->CommonModel->getMasterDetails('admin', '', $where1);
                            if (!empty($userMobile)) {
                                $status['msg'] = $this->systemmsg->getErrorCode(279);
                                $status['statusCode'] = 279;
                                $status['data'] = array();
                                $status['flag'] = 'F';
                                $this->response->output($status, 200);
                            }
                        }

                        $where2 = array("userName" => $adminDetails['userName']);
                        $db_userName = $this->CommonModel->getMasterDetails('admin', '', $where2);
                        if (!empty($db_userName)) {
                            $status['msg'] = $this->systemmsg->getErrorCode(297);
                            $status['statusCode'] = 297;
                            $status['data'] = array();
                            $status['flag'] = 'F';
                            $this->response->output($status, 200);
                        }
                        if (!empty($userEmail)) {
                            $status['msg'] = $this->systemmsg->getErrorCode(278);
                            $status['statusCode'] = 278;
                            $status['data'] = array();
                            $status['flag'] = 'F';
                            $this->response->output($status, 200);
                        }

                        $verification_required = $this->validatedata->validate('pass_update_on_reset', 'verification', false, '', array());
                        if ($verification_required == "no") {
                            $password = $this->validatedata->validate('password', 'password', true, '', array());
                            $adminDetails['isVerified'] = "Y";
                            $adminDetails['password'] = md5(trim($password));
                        }
                        $iscreated = $this->SearchAdminModel->saveAdminDetails($adminDetails);
                        $last_id = $this->db->insert_id();
                        if (!$iscreated) {
                            $this->db->trans_rollback();
                            $status['msg'] = $this->systemmsg->getErrorCode(998);
                            $status['statusCode'] = 998;
                            $status['data'] = array();
                            $status['flag'] = 'F';
                            $this->response->output($status, 200);
                        } else {
                            if ($verification_required == "no") {
                                $this->db->trans_commit();
                                $status['msg'] = $this->systemmsg->getSucessCode(400);
                                $status['statusCode'] = 400;
                                $status['data'] = array();
                                $status['flag'] = 'S';
                                $this->response->output($status, 200);
                            }

                            $adminID = $this->SearchAdminModel->getInsertedID();
                            $verificationCode = md5($adminDetails['otp']);
                            $verificationCode = substr($verificationCode, 0, 10);
                            $this->getCompanyDetails($adminID);
                            $baseURL = $this->config->item("app_url") . "verify-details?&vfcode=" . $verificationCode . "&auth-id=" . $adminID;
                            if ($adminDetails['status'] == "active") {
                                $sendEmail = $this->sendEmailsToUser('accountVerificationTemplate', $adminDetails['email'], $adminDetails['userName'], $baseURL, $adminDetails['name']);
                                if ($sendEmail) {
                                    $updateStatus = $this->SearchAdminModel->updateAdminDetails($array = array("isEmailSend" => "yes"), $adminID);
                                    $this->db->trans_commit();
                                    $status['msg'] = $this->systemmsg->getSucessCode(400);
                                    $status['statusCode'] = 400;
                                    $status['data'] = array("last_id" => $last_id);
                                    $status['flag'] = 'S';
                                    $this->response->output($status, 200);
                                } else {
                                    $this->db->trans_rollback();
                                    $status['msg'] = $this->systemmsg->getErrorCode(296);
                                    $status['statusCode'] = 296;
                                    $status['data'] = array();
                                    $status['flag'] = 'F';
                                    $this->response->output($status, 200);
                                }
                            } else {
                                $this->db->trans_commit();
                                $status['msg'] = $this->systemmsg->getSucessCode(400);
                                $status['statusCode'] = 400;
                                $status['data'] = array("last_id" => $last_id);
                                $status['flag'] = 'S';
                                $this->response->output($status, 200);
                            }
                        }
                        break;
                    }
                case "POST": {

                        if (empty($adminID)) {
                            $status['msg'] = 'Admin ID is required for update';
                            $status['statusCode'] = 400;
                            $status['data'] = [];
                            $status['flag'] = 'F';
                            $this->response->output($status, 200);
                        }


                        $updateDate = date("Y/m/d H:i:s");
                        $adminDetails['modified_date'] = $updateDate;
                        $adminDetails['modified_by'] = $this->input->post('SadminID');
                        $oldUserName = $this->CommonModel->getMasterDetails('admin', 'userName,email,name', array('adminID' => $adminID));
                        $where2 = array("userName" => $adminDetails['userName']);
                        $db_userName = $this->CommonModel->getMasterDetails('admin', '', $where2);
                        $newpass = $this->validatedata->validate('newPassword', 'Password', false, '', array());

                        log_message('error', 'Calling userDetails with adminID = ' . $adminID);
                        log_message('error', 'UPDATE DATA: ' . print_r($adminDetails, true));

                        if (isset($newpass) && !empty($newpass)) {
                            $adminDetails['password'] = md5(trim($newpass));
                        }
                        // add dynamic feild data
                        if (!empty($db_userName)) {
                            if ($db_userName[0]->adminID != $adminID) {
                                $status['msg'] = $this->systemmsg->getErrorCode(297);
                                $status['statusCode'] = 297;
                                $status['data'] = array();
                                $status['flag'] = 'F';
                                $this->response->output($status, 200);
                            }
                        }
                        if (!empty($oldUserName)) {
                            if ($adminDetails['userName'] != $oldUserName[0]->userName) {
                                $isEmailSend = $this->sendEmailsToUser("updateUserName", $oldUserName[0]->email, $adminDetails['userName'], '', $oldUserName[0]->name, '', $oldUserName[0]->userName);
                            }
                        }

                        $where = array("email" => $adminDetails['email']);
                        $userEmail = $this->CommonModel->getMasterDetails('admin', '', $where);
                        if (!empty($userEmail)) {
                            $isEqual = $userEmail[0]->adminID != $adminID ? true : false;
                            if ($isEqual) {
                                $status['msg'] = $this->systemmsg->getErrorCode(278);
                                $status['statusCode'] = 278;
                                $status['data'] = array();
                                $status['flag'] = 'F';
                                $this->response->output($status, 200);
                            }
                        }
                        $this->db->trans_start();

                        $iscreated = $this->SearchAdminModel->updateAdminDetails($adminDetails, $adminID);
                        if (!$iscreated) {
                            $this->db->trans_rollback();
                            $status['msg'] = $this->systemmsg->getErrorCode(998);
                            $status['statusCode'] = 998;
                            $status['data'] = array();
                            $status['flag'] = 'F';
                            $this->response->output($status, 200);
                        } else {
                            // $this->filters->upsertDynamicData($adminID);
                            $this->db->trans_commit();
                            $status['msg'] = $this->systemmsg->getSucessCode(400);
                            $status['statusCode'] = 400;
                            $status['data'] = array();
                            $status['flag'] = 'S';
                            $this->response->output($status, 200);
                        }
                        break;
                    }
                default: {
                        break;
                    }
            }
        } else {
            if ($adminID == "") {
                $status['msg'] = $this->systemmsg->getErrorCode(227);
                $status['statusCode'] = 227;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            }

            $record = $this->filterbuilder->fetchRecordFlat($this->menuID, (int)$adminID, 'labels', false);
            //print_r($rec['base']);
            //$status['data'] = [$rec['base'] + ['dynamic' => $rec['dynamicByKey']]]; // flatten for UI
            $status['data'][0] = $record;

            $status['msg'] = "";
            $status['statusCode'] = 200;
            //$status['data'] = array();
            $status['flag'] = 'S';
            $this->response->output($status, 200);

            // $this->filters->_initialize('yes');
            // $wherec = $join = array();
            // $wherec = $this->whereData["wherec"];
            // $other = $this->whereData["other"];
            // $join = $this->whereData["join"];
            // $selectC = $this->whereData["select"];	
            // $wherec["t.adminID ="] = "'".$adminID."'";	
            // if ($selectC != "") {
            //     $selectC = "t.*,r.roleName," . $selectC;
            // } else {
            //     $selectC = "t.*,r.roleName," . $selectC;
            // }
            // $adminHistory = $this->CommonModel->GetMasterListDetails($selectC, $this->menuDetails->table_name, $wherec, '', '', $join, array());
            // if (isset($adminHistory[0]->whatsappNo) && !empty($adminHistory[0]->whatsappNo)) {

            //     $fullNumber = $adminHistory[0]->whatsappNo;
            //     // Splitting the number into country code and mobile number
            //     $numberParts = explode('-', $fullNumber);

            //     if (count($numberParts) == 2) {
            //         $countryCode = $numberParts[0]; // The country code part
            //         $mobileNumber = $numberParts[1]; // The mobile number part

            //         // Assigning the separated values
            //         $adminHistory[0]->whatsappNo = $mobileNumber;
            //         $adminHistory[0]->whatsappCountryCodeNumber = $countryCode;
            //     } else {
            //         // Handle cases where the format is not as expected
            //         $adminHistory[0]->whatsappCountryCodeNumber = '';
            //         $adminHistory[0]->whatsappNo = $fullNumber;
            //     }
            // }
            // if (isset($adminHistory[0]->company_id) && !empty($adminHistory[0]->company_id)) {
            //     $array = explode(",", $adminHistory[0]->company_id);
            //     $companyNames = []; // Initialize an empty array to store the company names

            //     foreach ($array as $value) {
            //         $wherec = array();
            //         $wherec["t.infoID"] = ' = "' . $value . '"';

            //         // Fetch the company name based on the ID
            //         $companyName = $this->CommonModel->GetMasterListDetails($selectC = 'companyName', 'info_settings', $wherec, '', '', $join = array(), $other = array());

            //         if (!empty($companyName) && isset($companyName[0]->companyName)) {
            //             $companyNames[] = $companyName[0]->companyName;
            //         }
            //     }

            //     // Assign the company names array to the adminHistory object
            //     $adminHistory[0]->companyNames = $companyNames;
            // }
            // $adminHistory[0]->companyNamesString = implode(", ", $adminHistory[0]->companyNames);
            // if (isset($adminHistory) && !empty($adminHistory)) {
            //     $status['data'] = $adminHistory;
            //     $status['statusCode'] = 200;
            //     $status['flag'] = 'S';
            //     $this->response->output($status, 200);
            // } else {
            //     $status['msg'] = $this->systemmsg->getErrorCode(227);
            //     $status['statusCode'] = 227;
            //     $status['data'] = array();
            //     $status['flag'] = 'F';
            //     $this->response->output($status, 200);
            // }
        }
    }

    public function shopDetails()
    {
        $this->response->decodeRequest();

        $method = $this->input->method(true);

        // ONLY ADD SHOP
        if ($method !== 'PUT') {
            $this->response->output([
                'msg' => 'Invalid request method',
                'statusCode' => 405,
                'data' => [],
                'flag' => 'F'
            ], 200);
        }

        /* ========= VALIDATION ========= */

        $shopData = [];
        $shopData['shop_name']    = $this->validatedata->validate('shop_name', 'Shop Name', true);
        $shopData['shop_address'] = $this->validatedata->validate('shop_address', 'Shop Address', true);
        $shopData['shop_email']   = $this->validatedata->validate('shop_email', 'Shop Email', true);
        $shopData['shop_owner']   = $this->validatedata->validate('shop_owner', 'Shop Owner', true);
        $shopData['status']       = $this->validatedata->validate('status', 'Status', false) ?: 'active';

        /* ========= AUDIT ========= */

        $shopData['created_date']  = date('Y-m-d H:i:s');
        $shopData['created_by']    = $this->input->post('SadminID');
        $shopData['modified_date'] = date('Y-m-d H:i:s');
        $shopData['modified_by']   = $this->input->post('SadminID');

        /* ========= INSERT ========= */

        $this->db->trans_start();
        $this->db->insert('ab_shop', $shopData);
        $shopID = $this->db->insert_id();

        if (!$shopID) {
            $this->db->trans_rollback();
            $this->response->output([
                'msg' => 'Failed to create shop',
                'statusCode' => 500,
                'data' => [],
                'flag' => 'F'
            ], 200);
        }

        $this->db->trans_commit();

        $this->response->output([
            'msg' => 'Shop created successfully',
            'statusCode' => 200,
            'data' => ['shopID' => $shopID],
            'flag' => 'S'
        ], 200);
    }


    public function confirm_password()
    {
        $this->access->checkTokenKey();
        $this->response->decodeRequest();
        $adminID = $this->input->post('SadminID');
        $isUpdatePassword = $this->input->post('isUpdatePassword');
        //print_r($_POST); exit;
        if (isset($isUpdatePassword) && !empty($isUpdatePassword)) {
            if ($isUpdatePassword == 'yes') {
                $oldPass = $this->input->post('current_password');
                $newPass = $this->input->post('new_password');
                $confirmNewPass = $this->input->post('confirm_password');
                $newPass = $this->validatePass($adminID, $oldPass, $newPass, $confirmNewPass);
                $adminDetails['password'] = $newPass;
            }
        }
        $iscreated = $this->SearchAdminModel->updateAdminDetails($adminDetails, $adminID);
        if (!$iscreated) {
            $status['msg'] = $this->systemmsg->getErrorCode(998);
            $status['statusCode'] = 998;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            $where = array("adminID" => $adminID);
            $infoData = $this->CommonModel->getMasterDetails('admin', '', $where);
            if (!$this->config->item('development')) {
                if (isset($infoData) && !empty($infoData)) {
                    $this->sendEmailsToUser('updatePasswordTemp', $infoData[0]->email, $infoData[0]->userName, '', $infoData[0]->name);
                }
            }
            $status['msg'] = $this->systemmsg->getSucessCode(400);
            $status['statusCode'] = 400;
            $status['data'] = array();
            $status['flag'] = 'S';
            $this->response->output($status, 200);
        }
    }
    public function validatePass($adminID = '', $oldPass = '', $newPass = '', $cNewPass = '')
    {
        $where = array("adminID" => $adminID);
        $passwordDB = $this->CommonModel->getMasterDetails('admin', 'password', $where);
        if (isset($passwordDB) && !empty($passwordDB)) {
            $oldPass = md5($oldPass);
            //$oldPass = substr($oldPass, 0, 30);
            if ($passwordDB[0]->password == $oldPass) {
                if ($newPass == $cNewPass) {
                    $newPass = md5($newPass);
                    //$newPass = substr($newPass, 0, 30);
                    //if ($newPass != $cNewPass) {
                    return $newPass;
                    //}
                } else {
                    $status['msg'] = $this->systemmsg->getErrorCode(301);
                    $status['statusCode'] = 301;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
            } else {
                $status['msg'] = $this->systemmsg->getErrorCode(316);
                $status['statusCode'] = 316;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            }
        }
    }
    // VALIDATE OTP
    public function validateOtp($adminID = '')
    {
        $adminDetails = $adminEextraDetails = array();
        $where = array();
        $where['adminID'] = $adminID;
        $this->response->decodeRequest();
        $otp = $this->validatedata->validate('otp', 'otp', true, '', array());
        $password = $this->validatedata->validate('password', 'password', true, '', array());
        $confirmPassword = $this->validatedata->validate('confirmPassword', 'confirmPassword', true, '', array());
        $getOtp = $this->CommonModel->getMasterDetails("admin", $select = "otp", $where);
        if (($otp == $getOtp[0]->otp && $otp != 0)) {
            if ($password == $confirmPassword) {
                $adminEextraDetails['otp'] = 0;
                $isupdated = $this->SearchAdminModel->updateAdminDetails($adminEextraDetails, $adminID);
                $this->updatePassword($adminID, $password);
            } else {
                $status['msg'] = $this->systemmsg->getErrorCode(301);
                $status['statusCode'] = 301;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            }
        } else {
            $status['msg'] = $this->systemmsg->getErrorCode(302);
            $status['statusCode'] = 302;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        }
    }
    public function getSystemUserList()
    {
        $this->response->decodeRequest();
        $t = $this->input->post('text');
        $t = $t ?? '';
        $text = trim($t);
        $wherec = array();
        if (isset($text) && !empty($text)) {
            $wherec["email like  "] = "'%" . $text . "%'";
        }
        $updateAns = $this->CommonModel->GetMasterListDetails("adminID,email", "admin", $wherec);
        if (isset($updateAns) && !empty($updateAns)) {
            $status['msg'] = "sucess";
            $status['data'] = $updateAns;
            $status['statusCode'] = 400;
            $status['flag'] = 'S';
            $this->response->output($status, 200);
        }
    }
    public function getSystemUserNameList()
    {
        $this->response->decodeRequest();
        $t = $this->input->post('text');
        $t = $t ?? '';
        $text = trim($t);
        $wherec = array();
        if (isset($text) && !empty($text)) {
            $wherec["name like  "] = "'%" . $text . "%'";
        }
        $updateAns = $this->CommonModel->GetMasterListDetails("adminID,name,photo", "admin", $wherec);
        if (isset($updateAns) && !empty($updateAns)) {
            $status['msg'] = "sucess";
            $status['data'] = $updateAns;
            $status['statusCode'] = 400;
            $status['flag'] = 'S';
            $this->response->output($status, 200);
        }
    }
    // UPDATE USER fTOKEN
    public function updateToken()
    {
        $this->access->checkTokenKey();
        $this->response->decodeRequest();
        $adminID = trim($this->input->post("adminID"));
        $fToken = trim($this->input->post("fToken"));
        $adminDetails = array();
        $where = array();
        $where['adminID'] = $adminID;
        $adminDetails['fToken'] = $fToken;
        $isupdated = $this->SearchAdminModel->updateAdminDetails($adminDetails, $adminID);
        if (!$isupdated) {
            $status['msg'] = $this->systemmsg->getErrorCode(998);
            $status['statusCode'] = 998;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {

            $status['msg'] = "Token Updated Successfully";
            $status['statusCode'] = 400;
            $status['data'] = array();
            $status['flag'] = 'S';
            $this->response->output($status, 200);
        }
    }
    public function setprofilePic($memberID = '')
    {
        $this->load->library('slim');
        $images = $this->slim->getImages();
        if (!empty($images) && isset($images[0]['input']['name'])) {
            $imagename = $images[0]['input']['name'];
        } else {
            echo 'No image name found.';
        }
        $imagename = 'profile_' . time() . ".jpg";
        try {
            $images = $this->slim->getImages();
        } catch (Exception $e) {
            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'Unknown',
            ));
            return;
        }
        // No image found under the supplied input name
        if ($images === false) {
            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No data posted',
            ));
            return;
        }
        // Should always be one image (when posting async), so we'll use the first on in the array (if available)
        $image = array_shift($images);
        if (!isset($image)) {
            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No images found',
            ));
            return;
        }
        if (!isset($image['output']['data']) && !isset($image['input']['data'])) {
            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No image data',
            ));
            return;
        }
        // if we've received output data save as file
        if (isset($image['output']['data'])) {
            // get the name of the file
            $name = $image['output']['name'];
            // get the crop data for the output image
            $data = $image['output']['data'];
            $output = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/profilePic/');
        }
        if (isset($image['input']['data'])) {
            // get the name of the file
            $name = $image['input']['name'];
            // get the crop data for the output image
            $data = $image['input']['data'];
            $input = $this->slim->saveFile($data, $name, $_SERVER['DOCUMENT_ROOT'] . '/LMS/website/uploads/profilephoto/' . $memberID . '/profilePic/');
        }
        $response = array(
            'status' => SlimStatus::SUCCESS,
            'newFileName' => $imagename,
        );
        if (isset($output) && isset($input)) {
            $response['output'] = array(
                'file' => $output['name'],
                'path' => $output['path'],
            );
            $response['input'] = array(
                'file' => $input['name'],
                'path' => $input['path'],
            );
        } else {
            $response['file'] = isset($output) ? $output['name'] : $input['name'];
            $response['path'] = isset($output) ? $output['path'] : $input['path'];
        }
        $updateDate = date("Y/m/d H:i:s");
        $data = array("photo" => $imagename);
        $isrename = rename($this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/profilePic/' . $response['file'], $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/profilePic/' . $imagename);
        $where = array("adminID" => $memberID);
        $isupdate = $this->CommonModel->updateMasterDetails('admin', $data, $where);
        /*if (isset($_SESSION['USER']['profile_pic']) && !empty($_SESSION['USER']['profile_pic'])) {
        if ($_SESSION['USER']['profile_pic'] != 'default') {

        if(file_exists($_SERVER["DOCUMENT_ROOT"].'/uploads/profilePic/' . $_SESSION['USER']['profile_pic'])){
        unlink($_SERVER["DOCUMENT_ROOT"].'/uploads/profilePic/' . $_SESSION['USER']['profile_pic']);
        }

        }
        }*/
        $this->slim->outputJSON($response);
    }
    public function setLogo($memberID = '')
    {
        //    print_r($memberID);exit;
        $this->load->library('slim');
        $images = $this->slim->getImages();

        if (!empty($images) && isset($images[0]['input']['name'])) {
            $imagename = $images[0]['input']['name'];
        } else {
            echo 'No image name found.';
        }
        $imagename = 'logo_' . time() . ".jpg";
        try {
            $images = $this->slim->getImages();
        } catch (Exception $e) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'Unknown',
            ));
            return;
        }
        // No image found under the supplied input name
        if ($images === false) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No data posted',
            ));
            return;
        }

        // Should always be one image (when posting async), so we'll use the first on in the array (if available)
        $image = array_shift($images);

        if (!isset($image)) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No images found',
            ));

            return;
        }

        if (!isset($image['output']['data']) && !isset($image['input']['data'])) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No image data',
            ));

            return;
        }

        // if we've received output data save as file
        if (isset($image['output']['data'])) {

            // get the name of the file
            $name = $image['output']['name'];

            // get the crop data for the output image
            $data = $image['output']['data'];
            $output = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/logo/');
        }

        if (isset($image['input']['data'])) {

            // get the name of the file
            $name = $image['input']['name'];

            // get the crop data for the output image
            $data = $image['input']['data'];
            $input = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/logo/');
        }

        $response = array(
            'status' => SlimStatus::SUCCESS,
            'newFileName' => $imagename,
        );

        if (isset($output) && isset($input)) {

            $response['output'] = array(
                'file' => $output['name'],
                'path' => $output['path'],
            );

            $response['input'] = array(
                'file' => $input['name'],
                'path' => $input['path'],

            );
        } else {
            $response['file'] = isset($output) ? $output['name'] : $input['name'];
            $response['path'] = isset($output) ? $output['path'] : $input['path'];
        }

        $updateDate = date("Y/m/d H:i:s");
        $data = array("logo" => $imagename);

        $isrename = rename($this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/logo/' . $response['file'], $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/logo/' . $imagename);
        $where = array("adminID" => $memberID);
        $isupdate = $this->CommonModel->updateMasterDetails('admin', $data, $where);
        /*if (isset($_SESSION['USER']['profile_pic']) && !empty($_SESSION['USER']['profile_pic'])) {
        if ($_SESSION['USER']['profile_pic'] != 'default') {

        if(file_exists($_SERVER["DOCUMENT_ROOT"].'/uploads/profilePic/' . $_SESSION['USER']['profile_pic'])){
        unlink($_SERVER["DOCUMENT_ROOT"].'/uploads/profilePic/' . $_SESSION['USER']['profile_pic']);
        }

        }
        }*/
        $this->slim->outputJSON($response);
    }
    public function setCoverImage($memberID = '')
    {
        //    print_r($memberID);exit;
        $this->load->library('slim');
        $images = $this->slim->getImages();

        if (!empty($images) && isset($images[0]['input']['name'])) {
            $imagename = $images[0]['input']['name'];
        } else {
            echo 'No image name found.';
        }
        $imagename = 'cover_image_' . time() . ".jpg";
        try {
            $images = $this->slim->getImages();
        } catch (Exception $e) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'Unknown',
            ));
            return;
        }
        // No image found under the supplied input name
        if ($images === false) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No data posted',
            ));
            return;
        }

        // Should always be one image (when posting async), so we'll use the first on in the array (if available)
        $image = array_shift($images);

        if (!isset($image)) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No images found',
            ));

            return;
        }

        if (!isset($image['output']['data']) && !isset($image['input']['data'])) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No image data',
            ));

            return;
        }

        // if we've received output data save as file
        if (isset($image['output']['data'])) {

            // get the name of the file
            $name = $image['output']['name'];

            // get the crop data for the output image
            $data = $image['output']['data'];
            $output = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/coverImage/');
        }

        if (isset($image['input']['data'])) {

            // get the name of the file
            $name = $image['input']['name'];

            // get the crop data for the output image
            $data = $image['input']['data'];
            $input = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/coverImage/');
        }

        $response = array(
            'status' => SlimStatus::SUCCESS,
            'newFileName' => $imagename,
        );

        if (isset($output) && isset($input)) {

            $response['output'] = array(
                'file' => $output['name'],
                'path' => $output['path'],
            );

            $response['input'] = array(
                'file' => $input['name'],
                'path' => $input['path'],

            );
        } else {
            $response['file'] = isset($output) ? $output['name'] : $input['name'];
            $response['path'] = isset($output) ? $output['path'] : $input['path'];
        }

        $updateDate = date("Y/m/d H:i:s");
        $data = array("profile_cover_img" => $imagename);

        $isrename = rename($this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/coverImage/' . $response['file'], $this->config->item("mediaPATH") . 'profilephoto/' . $memberID . '/coverImage/' . $imagename);
        $where = array("adminID" => $memberID);
        $isupdate = $this->CommonModel->updateMasterDetails('admin', $data, $where);
        /*if (isset($_SESSION['USER']['profile_pic']) && !empty($_SESSION['USER']['profile_pic'])) {
        if ($_SESSION['USER']['profile_pic'] != 'default') {

        if(file_exists($_SERVER["DOCUMENT_ROOT"].'/uploads/profilePic/' . $_SESSION['USER']['profile_pic'])){
        unlink($_SERVER["DOCUMENT_ROOT"].'/uploads/profilePic/' . $_SESSION['USER']['profile_pic']);
        }

        }
        }*/
        $this->slim->outputJSON($response);
    }
    public function removeProfilePicFile($userID = '')
    {
        $where = array("adminID" => $userID);
        $formData = array();
        $path = $this->config->item("mediaPATH") . 'profilephoto/' . $userID . '/profilePic/';
        $images = $this->CommonModel->getMasterDetails('admin', 'photo', $where);
        $image = $images[0]->photo;
        $formData['photo'] = null;

        if (isset($image) && !empty($image)) {
            if (file_exists($path . $image)) {
                $formData['adminID'] = $userID;
                $iscreated = $this->CommonModel->updateMasterDetails("admin", $formData, array('adminID' => $userID));
                unlink($path . $image);
                $status['msg'] = $this->systemmsg->getSucessCode(400);
                $status['data'] = "";
                $status['flag'] = 'S';
                echo json_encode($status);
                exit;
            } else {
                $status['msg'] = $this->systemmsg->getSucessCode(400);
                $status['data'] = "";
                $status['flag'] = 'S';
                echo json_encode($status);
                exit;
            }
        }
    }
    public function userGallery($memberID = '')
    {
        //    print_r($memberID);exit;
        $this->load->library('slim');
        $images = $this->slim->getImages();

        if (!empty($images) && isset($images[0]['input']['name'])) {
            $imagename = $images[0]['input']['name'];
        } else {
            echo 'No image name found.';
        }
        $imagename = 'gallery_' . time() . ".jpg";
        try {
            $images = $this->slim->getImages();
        } catch (Exception $e) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'Unknown',
            ));
            return;
        }
        // No image found under the supplied input name
        if ($images === false) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No data posted',
            ));
            return;
        }

        // Should always be one image (when posting async), so we'll use the first on in the array (if available)
        $image = array_shift($images);

        if (!isset($image)) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No images found',
            ));

            return;
        }

        if (!isset($image['output']['data']) && !isset($image['input']['data'])) {

            $this->slim->outputJSON(array(
                'status' => SlimStatus::FAILURE,
                'message' => 'No image data',
            ));

            return;
        }

        // if we've received output data save as file
        if (isset($image['output']['data'])) {

            // get the name of the file
            $name = $image['output']['name'];

            // get the crop data for the output image
            $data = $image['output']['data'];
            $output = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'userGallery/' . $memberID . '/');
        }

        if (isset($image['input']['data'])) {

            // get the name of the file
            $name = $image['input']['name'];

            // get the crop data for the output image
            $data = $image['input']['data'];
            $input = $this->slim->saveFile($data, $name, $this->config->item("mediaPATH") . 'userGallery/' . $memberID . '/');
        }

        $response = array(
            'status' => SlimStatus::SUCCESS,
            'newFileName' => $imagename,
        );

        if (isset($output) && isset($input)) {
            $response['output'] = array(
                'file' => $output['name'],
                'path' => $output['path'],
            );
            $response['input'] = array(
                'file' => $input['name'],
                'path' => $input['path'],

            );
        } else {
            $response['file'] = isset($output) ? $output['name'] : $input['name'];
            $response['path'] = isset($output) ? $output['path'] : $input['path'];
        }

        $updateDate = date("Y/m/d H:i:s");
        $data = array();
        $data["user_id"] = $memberID;
        $data["file_name"] = $imagename;
        $data["file_type"] = '';
        $data["created_by"] = $memberID;
        $data["created_date"] = $updateDate;
        $data["record_type"] = 'gallery';

        $f = $this->config->item("mediaPATH") . 'userGallery/' . $memberID . '/' . $imagename;
        $nf = $this->config->item("mediaPATH") . 'userGallery/' . $memberID . '/' . $response['file'];
        // print_r($nf.'  =  '.$f);
        if (file_exists($nf)) {

            $isrename = rename($this->config->item("mediaPATH") . 'userGallery/' . $memberID . '/' . $response['file'], $this->config->item("mediaPATH") . 'userGallery/' . $memberID . '/' . $imagename);
        } else {
            print_r('not found');
        }

        $isupdate = $this->CommonModel->saveMasterDetails('user_gallery', $data);
        $this->slim->outputJSON($response);
    }
    public function deleteUploadedPic($userID = '')
    {
        $where = array("adminID" => $userID);
        $path = $this->config->item("mediaPATH") . 'profilephoto/' . $userID;
        $pic_type = $this->input->post("pic_type");
        $formData = array();
        if ($pic_type == 'coverImage') {
            $path = $this->config->item("mediaPATH") . 'profilephoto/' . $userID . '/coverImage/';
            $images = $this->CommonModel->getMasterDetails('admin', 'profile_cover_img', $where);
            $image = $images[0]->profile_cover_img;
            $formData['profile_cover_img'] = '';
        }
        if ($pic_type == 'profilePic') {
            $path = $this->config->item("mediaPATH") . 'profilephoto/' . $userID . '/profilePic/';
            $images = $this->CommonModel->getMasterDetails('admin', 'photo', $where);
            $image = $images[0]->photo;
            $formData['photo'] = '';
        }
        if ($pic_type == 'logo') {
            $path = $this->config->item("mediaPATH") . 'profilephoto/' . $userID . '/logo/';
            $images = $this->CommonModel->getMasterDetails('admin', 'logo', $where);
            $image = $images[0]->logo;
            $formData['logo'] = '';
        }

        if (isset($image) && !empty($image)) {
            if (file_exists($path . $image)) {
                $formData['adminID'] = $userID;
                $iscreated = $this->CommonModel->updateMasterDetails("admin", $formData, array('adminID' => $userID));
                unlink($path . $image);
                $status['msg'] = $this->systemmsg->getSucessCode(400);
                $status['data'] = "";
                $status['flag'] = 'S';
                echo json_encode($status);
                exit;
            } else {
                $status['msg'] = $this->systemmsg->getSucessCode(400);
                $status['data'] = "";
                $status['flag'] = 'S';
                echo json_encode($status);
                exit;
            }
        }
    }
    public function verifyUser()
    {
        $this->response->decodeRequest();
        $adminDetails = $adminEextraDetails = array();
        $where = array();
        $adminID = $this->validatedata->validate('userID', 'userID', true, '', array());
        $password = $this->validatedata->validate('password', 'password', true, '', array());

        $confirmPassword = $this->validatedata->validate('confirmPassword', 'confirmPassword', true, '', array());
        $md_vfCode = $this->validatedata->validate('vfcode', 'vfcode', true, '', array());
        $where['adminID'] = $adminID;
        if (!isset($adminID) && empty($adminID)) {
            $status['msg'] = $this->systemmsg->getErrorCode(304);
            $status['statusCode'] = 304;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            $getOtp = $this->CommonModel->getMasterDetails("admin", $select = "otp,isVerified", $where);
            if (isset($getOtp) && !empty($getOtp)) {
                $isVerified = $getOtp[0]->isVerified;
                $dbotp = md5($getOtp[0]->otp);
                $dbotp = substr($dbotp, 0, 10);

                if (($md_vfCode == $dbotp && $dbotp != 0)) {
                    if ($password == $confirmPassword) {
                        $adminEextraDetails['otp'] = 0;
                        $adminEextraDetails['isverified'] = 'Y';
                        $isupdated = $this->SearchAdminModel->updateAdminDetails($adminEextraDetails, $adminID);
                        $this->updatePassword($adminID, $confirmPassword, $isVerified);
                    } else {
                        $status['msg'] = $this->systemmsg->getErrorCode(301);
                        $status['statusCode'] = 301;
                        $status['data'] = array();
                        $status['flag'] = 'F';
                        $this->response->output($status, 200);
                    }
                } else {
                    $status['msg'] = $this->systemmsg->getErrorCode(304);
                    $status['statusCode'] = 304;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
            } else {
                $status['msg'] = $this->systemmsg->getErrorCode(305);
                $status['statusCode'] = 305;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            }
        }
    }
    public function updatePassword($adminID = '', $password = '', $isVerified = 'N')
    {
        $adminDetails = array();
        $where = array();
        $this->getCompanyDetails($adminID);
        $where['adminID'] = $adminID;
        $adminDetails['password'] = md5($password);
        $adminDetails['password'] = substr($adminDetails['password'], 0, 30);
        $isupdated = $this->SearchAdminModel->updateAdminDetails($adminDetails, $adminID);
        if (!$isupdated) {
            $status['msg'] = $this->systemmsg->getErrorCode(998);
            $status['statusCode'] = 998;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            $where = array("adminID" => $adminID);
            $infoData = $this->CommonModel->getMasterDetails('admin', '', $where);
            if (!$this->config->item('development')) {
                if (isset($infoData) && !empty($infoData)) {
                    $this->sendFrom = 'updatePassword';
                    if ($isVerified == 'N') {
                        $this->sendEmailsToUser('welcomeToUser', $infoData[0]->email, $infoData[0]->userName, '', $infoData[0]->name);
                    } else {
                        $this->sendEmailsToUser('updatePasswordTemp', $infoData[0]->email, $infoData[0]->userName, '', $infoData[0]->name);
                    }
                }
            }
            $status['msg'] = $this->systemmsg->getSucessCode(425);
            $status['statusCode'] = 425;
            $status['data'] = array();
            $status['flag'] = 'S';
            $this->response->output($status, 200);
        }
    }
    public function setModuleDefaultView()
    {
        $this->access->checkTokenKey();
        $this->response->decodeRequest();
        $json = $this->input->post('jsonForm');
        $adminID = $this->input->post('SadminID');
        $adminDetails['user_setting'] = $json;
        // print_r($json);exit;
        // print_r($adminDetails);exit;
        $iscreated = $this->CommonModel->updateMasterDetails('admin', $adminDetails, array('adminID' => $adminID));
        if (!$iscreated) {
            $status['msg'] = $this->systemmsg->getErrorCode(998);
            $status['statusCode'] = 998;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            $status['msg'] = $this->systemmsg->getSucessCode(400);
            $status['statusCode'] = 400;
            $status['data'] = array();
            $status['flag'] = 'S';
            $this->response->output($status, 200);
        }
    }

    public function deleteUser()
    {
        // $this->access->checkTokenKey();
        $this->response->decodeRequest();

        $adminID = $this->input->post('id');
        $action = $this->input->post('action'); // reassignAndDeactivate | deleteAll
        $updatedAdmin = $this->input->post('updatedAdmin') ?? null;

        if (empty($adminID)) {
            return $this->response->output([
                'flag' => 'F',
                'msg' => 'Invalid user ID.',
                'statusCode' => 400
            ], 200);
        }

        $this->db->trans_start();

        try {

            if ($action === 'reassignAndDeactivate') {
                // Reassign related records and deactivate user
                //$this->reassignRecords($adminID, $updatedAdmin);
                $this->softDeleteUser($adminID);
                $this->logDeletion($adminID, $action, $updatedAdmin);
            } elseif ($action === 'deleteAll') {
                // Soft delete user and all related data
                $this->cascadeSoftDeleteUser($adminID);
                $this->logDeletion($adminID, $action);
            } else {
                throw new Exception('Invalid delete action');
            }

            $this->db->trans_commit();

            $status = [
                'flag' => 'S',
                'msg' => 'User processed successfully.',
                'statusCode' => 200
            ];
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', 'User delete failed: ' . $e->getMessage());
            $status = [
                'flag' => 'F',
                'msg' => 'Failed to delete user: ' . $e->getMessage(),
                'statusCode' => 500
            ];
        }

        $this->response->output($status, 200);
    }

    private function softDeleteUser($adminID)
    {
        $updateData = [
            'status' => 'delete',
            'deleted_at' => date('Y-m-d H:i:s')
        ];
        $isUpdated = $this->CommonModel->updateMasterDetails('admin', $updateData, ['adminID' => $adminID]);
        if (!$isUpdated) {
            throw new Exception('Unable to mark user delete');
        }
    }
    private function reassignRecords($oldAdminID, $newAdminID)
    {
        if (empty($newAdminID)) {
            throw new Exception('New assignee not provided');
        }

        // Define all tables that have user ownership
        $assignableModules = [
            'tasks'     => 'assignee',
            'tasks'     => 'created_by',
            'customer'  => 'assignee',
            'customer'  => 'created_by',
            //'leads'     => 'assignee',
            'invoices'  => 'created_by',
            'tickets'   => 'assignee'
        ];

        foreach ($assignableModules as $table => $field) {
            $where = [$field => $oldAdminID];
            $data  = [$field => $newAdminID];
            $this->CommonModel->updateMasterDetails($table, $data, $where);
        }
    }
    private function cascadeSoftDeleteUser($adminID)
    {
        // Define modules/tables that may have user-assigned data
        $relatedModules = [
            'tasks'     => 'assignee',
            'tasks'     => 'created_by',
            'customer'  => 'assignee',
            'customer'  => 'created_by',
            //'leads'     => 'assignee',
            'invoices'  => 'created_by',
            'tickets'   => 'assignee'
        ];

        // Current timestamp for reuse
        $now = date('Y-m-d H:i:s');

        foreach ($relatedModules as $table => $field) {
            // ✅ Step 1: Check if table exists
            if (!$this->db->table_exists($table)) {
                log_message('debug', "cascadeSoftDeleteUser: Table '{$table}' does not exist, skipping.");
                continue;
            }

            // ✅ Step 2: Perform safe soft delete update
            $updateData = [
                'status' => 'deleted',
                'modified_date' => $now,
            ];

            try {
                $this->CommonModel->updateMasterDetails($table, $updateData, [$field => $adminID]);
            } catch (Exception $e) {
                log_message('error', "cascadeSoftDeleteUser: Failed to update {$table} - " . $e->getMessage());
            }
        }

        // ✅ Step 3: Soft delete the user record itself
        $this->softDeleteUser($adminID);
    }

    public function changeStatus()
    {
        $this->access->checkTokenKey();
        $this->response->decodeRequest();

        $action = trim($this->input->post("action") ?? '');
        $ids = $this->input->post("list");
        $statusCode = $this->input->post("action");

        // Only allow status change — delete is no longer handled here
        if ($statusCode !== "delete") {
            if (empty($ids) || $statusCode === null) {
                $status = [
                    'flag' => 'F',
                    'msg' => 'Invalid parameters: list or status missing.',
                    'statusCode' => 400,
                    'data' => []
                ];
                return $this->response->output($status, 200);
            }

            $changeStatus = $this->SearchAdminModel->changeMemberStatus($statusCode, $ids);

            if ($changeStatus) {
                $status = [
                    'flag' => 'S',
                    'msg' => 'Status updated successfully.',
                    'statusCode' => 200,
                    'data' => []
                ];
            } else {
                $status = [
                    'flag' => 'F',
                    'msg' => $this->systemmsg->getErrorCode(996),
                    'statusCode' => 996,
                    'data' => []
                ];
            }

            return $this->response->output($status, 200);
        }

        // Invalid or missing action
        $status = [
            'flag' => 'F',
            'msg' => 'Invalid action.',
            'statusCode' => 405,
            'data' => []
        ];
        return $this->response->output($status, 200);
    }


    private function logDeletion($deletedUser, $action, $replacementUser = null)
    {
        $performedBy =  $this->input->post('SadminID');

        $logData = [
            'deleted_user_id' => $deletedUser,
            'action' => $action,
            'replacement_user_id' => $replacementUser,
            'performed_by' => $performedBy,
            'performed_at' => date('Y-m-d H:i:s')
        ];
        $this->CommonModel->saveMasterDetails('user_deletion_log', $logData);
    }


    // SEND RESET PASSWORD REQUEST
    public function resetPasswordRequest()
    {
        $this->response->decodeRequest();
        //print_r($_POST); exit;
        $email = $this->validatedata->validate('email', 'Email-Id', false, '', array());
        $userName = $this->validatedata->validate('username', 'User Name', false, '', array());
        if (isset($email) && !empty($email)) {
            $userName = $email;
        }
        $password = $this->validatedata->validate('password', 'Password', false, '', array());
        $checkEmail = $this->LoginModel->verifyUserDetails($userName, $password);
        if (empty($checkEmail)) {
            (isset($email) && !empty($email)) ? $errorCode = 328 : $errorCode = 325;
            $status['msg'] = $this->systemmsg->getErrorCode($errorCode);
            $status['statusCode'] = $errorCode;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            if (isset($email) && !empty($email)) {
                if ($checkEmail[0]->isVerified == 'N') {
                    $status['msg'] = $this->systemmsg->getErrorCode(327);
                    $status['statusCode'] = 327;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
            }
            $otp = rand(100000, 999999);
            $adminID = $checkEmail[0]->adminID;
            $this->getCompanyDetails($adminID);
            $adminEextraDetails = array("otp" => $otp);
            $adminEextraDetails['otp_exp_time'] = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $verificationCode = md5($otp);
            $verificationCode = substr($verificationCode, 0, 10);
            $baseURL = $this->config->item("app_url") . "verify-details?&vfcode=" . $verificationCode . "&type=pb&&auth-id=" . $adminID;

            $isupdated = $this->SearchAdminModel->forgotPassword($adminEextraDetails, $adminID);
            if (!$isupdated) {
                $status['msg'] = $this->systemmsg->getErrorCode(296);
                $status['statusCode'] = 296;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            } else {
                $isEmailSend = $this->sendEmailsToUser("forgotPasswordOTPSendTemp", $checkEmail[0]->email, $checkEmail[0]->userName, $baseURL, $checkEmail[0]->name);
                if ($isEmailSend) {
                    $status['data'] = array("userID" => $adminID);
                    $status['msg'] = $this->systemmsg->getSucessCode(424);
                    $status['statusCode'] = 400;
                    $status['flag'] = 'S';
                    $this->response->output($status, 200);
                } else {
                    $status['msg'] = $this->systemmsg->getErrorCode(296);
                    $status['statusCode'] = 296;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
            }
        }
    }
    // SEND EMAILS
    public function sendEmailsToUser($action, $email = '', $userName = '', $appLink = '', $name = '', $password = '', $oldUserName = '')
    {
        $currentDate = date("d M Y");
        $where = array('status' => 'active');
        switch ($action) {
            // SEND MAIL WITH ACOUNT VERIFICATION LINK
            case 'accountVerificationTemplate':
                $appLink = '<a href="' . $appLink . '"> Click Here To Verify </a>';
                $where["tempName"] = "accountVerificationTemplate";
                break;
            // SEND MAIL WITH RESET PASSWORD LINK
            case 'forgotPasswordOTPSendTemp':
                $appLink = '<a href="' . $appLink . '"> Click Here To Verify </a>';
                $where["tempName"] = "forgotPasswordOTPSendTemp";
                break;
            // SEND MAIL WITH UPDATED PASSWORD
            case 'updatePasswordTemp':
                $appLink = '<a href="' . $appLink . '"> Click Here To Verify </a>';
                $where["tempName"] = "updatePasswordTemp";
                break;
            // SEND EMAILS OTHER
            default:
                $where["tempName"] = $action;
                break;
        }
        $tempData = $this->CommonModel->getMasterDetails('email_master', '', $where);
        if (isset($tempData) && !empty($tempData)) {
            $mailContent = $tempData[0]->emailContent;
            (strpos($mailContent, "{{userName}}") !== false) ?
                $mailContent = str_replace("{{userName}}", $userName, $mailContent) :
                $mailContent = str_replace("{{userName}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{password}}") !== false) ?
                $mailContent = str_replace("{{password}}", $password, $mailContent) :
                $mailContent = str_replace("{{password}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{appLink}}") !== false) ?
                $mailContent = str_replace("{{appLink}}", $appLink, $mailContent) :
                $mailContent = str_replace("{{appLink}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{name}}") !== false) ?
                $mailContent = str_replace("{{name}}", $name, $mailContent) :
                $mailContent = str_replace("{{name}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{oldUserName}}") !== false) ?
                $mailContent = str_replace("{{oldUserName}}", $oldUserName, $mailContent) :
                $mailContent = str_replace("{{oldUserName}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{email}}") !== false) ?
                $mailContent = str_replace("{{email}}", $email, $mailContent) :
                $mailContent = str_replace("{{email}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{company_name}}") !== false) ?
                $mailContent = str_replace("{{company_name}}", $this->companyName, $mailContent) :
                $mailContent = str_replace("{{company_name}}", '[data not exists]', $mailContent);
            (strpos($mailContent, "{{date}}") !== false) ?
                $mailContent = str_replace("{{date}}", $currentDate, $mailContent) :
                $mailContent = str_replace("{{date}}", '[data not exists]', $mailContent);
            $from = $this->fromEmail;
            $to = $email;
            $subject = $tempData[0]->subjectOfEmail;
            $msg = $mailContent;
            $fromName = $this->fromName;
            $this->sendFrom = 'passwordUpdate';
            if (!$this->config->item('development')) {
                return $this->emails->sendMailDetails($from, $fromName, $to, $cc = '', $bcc = '', $subject, $msg);
            } else {
                return false;
            }
        } else {
            $emailError['message'] = $this->systemmsg->getErrorCode(313) . " - [ ' " . $action . " '] ";
            $emailError['code'] = 313;
            $this->errorlogs->checkDBError($emailError, 'Email Error', dirname(__FILE__), __LINE__, __METHOD__);
            return false;
        }
    }
}
