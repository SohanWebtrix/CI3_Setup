<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class EmailsTemp extends CI_Controller
{

 protected $columnNames = [
        "roleID" => ["table" => "user_role_master", "alias" => "r", "column" => "roleName", "key2" => "roleID", "select" => "t.roleID as roleID, r.roleName as roleName"],
    ];

    protected $customCol = [
        "default_company" => ["table" => "info_settings", "alias" => "dc", "column" => "companyName", "key2" => "infoID", "select" => ""],
        "modified_by" => ["table" => "admin", "alias" => "am", "column" => "name", "key2" => "adminID", "select" => ""],
        "created_by" => ["table" => "admin", "alias" => "ad", "column" => "name", "key2" => "adminID", "select" => ""],
    ];
    protected $Model;

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
        $this->load->library("Filters");
        $this->load->model('EmailTemplate_Model', 'EmailTemplateModel');
    }

public function saveEmail()
{
            //$this->access->checkTokenKey();


    if ($this->input->method(true) !== 'PUT') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid request method',
            'statusCode' => 405
        ], 200);
    }


 // decodeRequest populates $_POST with JSON data
 $this->response->decodeRequest();
   $payload = $_POST;


 //  log_message('debug', 'PAYLOAD: ' . print_r($payload, true));

 if (empty($payload)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid JSON payload',
            'statusCode' => 400
        ], 200);
    }

 // ✅ Extract values
    $tempName       = $payload['tempName']       ?? '';
    $subjectOfEmail = $payload['subjectOfEmail'] ?? '';
    $emailContent   = $payload['emailContent'] ?? '';
    $rawObject    = $payload['rawObject']   ?? '';
    $smsContent     = $payload['smsContent']  ?? '';
    $userId         = $payload['SadminID']    ?? 1;
    $templateStyle= $payload['templateStyle'] ??'';


    // ✅ Validate HTML
    if (empty(trim($emailContent))) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Email HTML content is required',
            'statusCode' => 422
        ], 200);
    }
   

     // ✅ Prepare DB data
    $data = [
        'tempUniqueID'   => uniqid('TMP'),
        'is_sys_temp'    => 'no',
        'tempName'       => $tempName,
        'subjectOfEmail' => $subjectOfEmail,
        'emailContent'   => $emailContent, // LONGTEXT
        'rawObject'    => json_encode($rawObject, JSON_UNESCAPED_UNICODE), 
        'smsContent'     => $smsContent,
        'created_by'     => $userId,
        'created_date'   => date('Y-m-d'),
        'status'         => 'active',
        'templateStyle' =>json_encode($templateStyle, JSON_UNESCAPED_UNICODE)
    ];

    $this->db->trans_start();
    $inserted = $this->EmailTemplateModel->saveTemplate($data);

    if (!$inserted) {
        $this->db->trans_rollback();
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Failed to save email template',
            'statusCode' => 500
        ], 200);
    }

    $this->db->trans_commit();

    return $this->response->output([
        'flag' => 'S',
        'msg' => 'Email template saved successfully',
        'statusCode' => 200
    ], 200);
}

public function getTemplate($tempUniqueID = '')
{

        //$this->access->checkTokenKey();

    // METHOD CHECK
    if ($this->input->method(true) !== 'GET') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid request method',
            'statusCode' => 405,
            'data' => []
        ], 200, 'json');
    }

    // ID VALIDATION
    if (empty($tempUniqueID)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Template ID is required',
            'statusCode' => 400,
            'data' => []
        ], 200, 'json');
    }

    // FETCH FROM MODEL
    $record = $this->EmailTemplateModel->getTemplateById($tempUniqueID);

    if (!$record) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Email template not found',
            'statusCode' => 404,
            'data' => []
        ], 200, 'json');
    }

    // SUCCESS RESPONSE
    return $this->response->output([
        'flag' => 'S',
        'msg' => 'Template fetched successfully',
        'statusCode' => 200,
        'data' => [
            'tempUniqueID'   => $record->tempUniqueID,
            'tempName'       => $record->tempName,
            'subjectOfEmail' => $record->subjectOfEmail,
            'emailContent'   => $record->emailContent,
            'rawObject'      => $record->rawObject,
            'smsContent'     => $record->smsContent,
            'templateStyle'  => $record->templateStyle,
            'created_by'     => $record->created_by,
            'created_date'   => $record->created_date
        ]
    ], 200, 'json');
}


 public function deleteEmailTemplate($tempUniqueID = '')
{

        //$this->access->checkTokenKey();

    // Method check
    if ($this->input->method(true) !== 'DELETE') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid request method',
            'statusCode' => 405
        ], 200);
    }

    // Validate ID from URL
    if (empty($tempUniqueID)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Template ID is required',
            'statusCode' => 422
        ], 200);
    }

    $this->db->trans_start();

    $deleted = $this->EmailTemplateModel->deleteTemplate($tempUniqueID);

    if($deleted['status']===false){
        $this->db->trans_rollback();

        if(isset($deleted['reason']) && $deleted['reason']==='system_template')
            {
                return $this->response->output([
                'flag'=>'F',
                'mas'=>'System tempalte cannot be deleted',
                'statusCode'=>403
                ],200);
            }

            if(isset($deleted['reason']) && $deleted['reason']=== 'not_found')
                {
                    return $this->response->output([
                        'flag'=>'F',
                        'mas'=>'Email template not found',
                        'statusCode'=>404
                    ],200);
                }

                  return $this->response->output([
            'flag' => 'F',
            'msg' => 'Failed to delete email template',
            'statusCode' => 500
        ], 200);
        
             
    }

    if (!$deleted) {
        $this->db->trans_rollback();
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Failed to delete email template',
            'statusCode' => 500
        ], 200);
    }

    $this->db->trans_commit();

    return $this->response->output([
        'flag' => 'S',
        'msg' => 'Email template deleted successfully',
        'statusCode' => 200
    ], 200);
}

public function updateTemplate($tempUniqueID = '')
{
    // METHOD CHECK
    if ($this->input->method(true) !== 'POST') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid request method',
            'statusCode' => 405
        ], 200);
    }

    // URL ID VALIDATION
    if (empty($tempUniqueID)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Template ID is required in URL',
            'statusCode' => 400
        ], 200);
    }

    // Decode JSON → $_POST
    $this->response->decodeRequest();
    $payload = $_POST;

    if (empty($payload)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid JSON payload',
            'statusCode' => 400
        ], 200);
    }

    // Validate required content
    if (empty(trim($payload['emailContent'] ?? ''))) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Email HTML content is required',
            'statusCode' => 422
        ], 200);
    }

    // Prepare update data
    $data = [
        'tempName'       => $payload['tempName'] ?? '',
        'subjectOfEmail' => $payload['subjectOfEmail'] ?? '',
        'emailContent'   => $payload['emailContent'],
        'smsContent'     => $payload['smsContent'] ?? '',
        'rawObject'      => isset($payload['rawObject'])
            ? json_encode($payload['rawObject'], JSON_UNESCAPED_UNICODE)
            : null,
        'templateStyle'  => isset($payload['templateStyle'])
            ? json_encode($payload['templateStyle'], JSON_UNESCAPED_UNICODE)
            : null,
        'modified_by'    => $payload['SadminID'] ?? 1,
        'modified_date'  => date('Y-m-d H:i:s')
    ];

    // TRANSACTION
    $this->db->trans_start();
    $updated = $this->EmailTemplateModel->updateTemplateById($tempUniqueID, $data);

    if (!$updated) {
        $this->db->trans_rollback();
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Failed to update email template',
            'statusCode' => 500
        ], 200);
    }

    $this->db->trans_commit();

    return $this->response->output([
        'flag' => 'S',
        'msg' => 'Email template updated successfully',
        'statusCode' => 200
    ], 200);
}
 

 public function list()
    {
        //$this->access->checkTokenKey();
        $payload = json_decode($this->input->raw_input_stream, true);
        if (!is_array($payload)) $payload = $this->input->post() ?: [];

        // log_message('error', 'PAYLOAD: ' . print_r($payload, true));

        $menuId = (int)($payload['menuId'] ?? 138);
        if ($menuId <= 0) {
            $this->response->output(['flag' => 'F', 'msg' => 'menuId is required', 'statusCode' => 422], 200, 'json');
            return;
        }

        // Logged-in user
        $userId = $this->input->get_request_header('SadminID', true);
        if (!$userId) $userId = $payload['SadminID'] ?? $this->input->post('SadminID');

        // Menu meta + PK
        $menuMeta = $this->Model->getMenuMeta($menuId);
        if (!$menuMeta) {
            $this->response->output(['flag' => 'F', 'msg' => 'Invalid menu', 'statusCode' => 422], 200, 'json');
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
        $required = ['tempID', 'tempUniqueID', 'tempName','rawObject','templateStyle','created_date','emailContent'];
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

        $this->response->output($status, 200, 'json');
    }
}

