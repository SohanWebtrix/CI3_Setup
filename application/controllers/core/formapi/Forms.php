<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Forms extends CI_Controller
{
    public function __construct()
    {
        parent::__construct(); 

        $this->load->database();
        $this->load->helper('form'); 
        $this->load->model('SearchAdminModel'); 
        $this->load->model('CommonModel');
        $this->load->model('LoginModel'); 
        $this->load->library("pagination"); 
        
        $this->load->helper(['url','security']);
        $this->load->model('core/CommonModelNew');
        $this->Model = $this->CommonModelNew;
        $this->load->library("ValidateData");
        $this->load->library('core/FilterEngine', [], 'filterengine');
        $this->load->library('core/FilterBuilder', [], 'filterbuilder');
        if (!$this->config->item('development')) {
            // $this->load->library("Emails");
        }

        $this->load->library("Filters");
        $this->load->model('FormConfig_Model', 'FormConfigModel');
    }


 

    public function saveDefinition()
    {
        // Decode JSON / form-data
        $this->response->decodeRequest();

        $method = $this->input->method(true);
    

        /* ========= METHOD CHECK ========= */
        if ($method !== 'POST') {
            $this->response->output([
                'msg' => 'Invalid request method',
                'statusCode' => 405,
                'data' => [],
                'flag' => 'F'
            ], 200);
        }

        /* ========= VALIDATION ========= */
        $formData = [];

        $formData['form_id'] = $this->validatedata->validate(
            'formId', 'Form ID', true
        );

        $formData['name'] = $this->validatedata->validate(
            'name', 'Form Name', true
        );

        $definition = $this->validatedata->validate(
            'definition', 'Form Definition', true
        );

        $formData['description'] = $this->validatedata->validate(
            'description', 'Description', false
        ) ?: '';

        /* ========= PREPARE DATA ========= */
        $formData['definition'] = json_encode($definition);

        // TEMP → replace later with token user
        $userId = $this->input->post('SadminID') ?: 1;

        $formData['modified_by'] = $userId;
        $formData['modified_date'] = date('Y-m-d H:i:s');


        /* ========= DB OPERATION ========= */
        $this->db->trans_start();

        $result = $this->FormConfigModel->saveFormDefinition($formData, $userId);

        if (!$result) {
            $this->db->trans_rollback();

            $this->response->output([
                'msg' => 'Failed to save form definition',
                'statusCode' => 500,
                'data' => [],
                'flag' => 'F'
            ], 200);
        }

        $this->db->trans_commit();

        /* ========= RESPONSE ========= */
        $this->response->output([
            'msg' => $result['is_update']
                ? 'Form definition updated successfully'
                : 'Form definition saved successfully',
            'statusCode' => 200,
            'data' => [],
            'flag' => 'S'
        ], 200);
    }


    public function getForm($formId = '')
{
    
    if (empty($formId)) {
        $this->response->output([
            'msg' => 'Form ID is required',
            'statusCode' => 400,
            'data' => [],
            'flag' => 'F'
        ], 200);
    }


    $record = $this->FormConfigModel->getFormByFormId($formId);

    if (!$record) {
        $this->response->output([
            'msg' => 'Form not found',
            'statusCode' => 404,
            'data' => [],
            'flag' => 'F'
        ], 200);
    }

    
    $this->response->output([
        'msg' => 'OK',
        'statusCode' => 200,
        'flag' => 'S',
        'data' => [
            'formId'      => $record->form_id,
            'name'        => $record->name,
            'description' => $record->description,
            'definition'  => json_decode($record->definition, true)
        ]
    ], 200);
}

 
public function updateSettings($formId)
{
    $this->response->decodeRequest();
    $method = $this->input->method(true);

    if ($method !== 'PUT') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid request method',
            'statusCode' => 405
        ], 200);
    }

    // Read JSON body
    // $payload = json_decode($this->input->raw_input_stream, true);
        $payload = $_POST;


    $patch   = $payload['patch'] ?? [];

    if (empty($formId) || empty($patch) || !is_array($patch)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid formId or patch data',
            'statusCode' => 422
        ], 200);
    }

    log_message('error', 'FORM SETTINGS PATCH: '.json_encode($patch));

    $result = $this->FormConfigModel->updateFormSettings($formId, $patch);

    if (!$result) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Form not found',
            'statusCode' => 404
        ], 200);
    }

    return $this->response->output([
        'flag' => 'S',
        'msg' => 'Form settings updated successfully',
        'statusCode' => 200,
        'data' => [
            'settings' => $result
        ]
    ], 200);
}

public function getLastForm()
    {
  
        $method = $this->input->method(true);

        log_message('error', 'getLastForm model executed.');

        if ($method !== 'GET') {
            $this->response->output([
                'msg' => 'Invalid request method',
                'statusCode' => 405,
                'data' => [],
                'flag' => 'F'
            ], 200);
        }

        $lastForm = $this->FormConfigModel->getLastUpdatedForm();

        error_log('Last form data: ' . print_r($lastForm, true));


        if (empty($lastForm)) {
            $this->response->output([
                'msg' => 'No forms found',
                'statusCode' => 404,
                'data' => [],
                'flag' => 'F'
            ], 200);
        }

        $this->response->output([
            'msg' => 'Last form fetched successfully',
            'statusCode' => 200,
            'data' => [
                'formId'    => $lastForm->form_id,
                'updatedAt' => $lastForm->updated_at
            ],
            'flag' => 'S'
        ], 200);
    }


  public function submit()
    {

      $this->response->decodeRequest();

        $method = $this->input->method(true);

        if ($method !== 'POST') {
            return $this->response->output([
                'flag' => 'F',
                'msg' => 'Invalid request method',
                'statusCode' => 405
            ], 200);
        }
        
    //   Honey pot check 
        if (!empty($this->input->post('company_website'))) {
            log_message('error', 'Honeypot triggered: '.json_encode($_POST));

            return $this->response->output([
                'flag' => 'F',
                'msg' => 'Spam detected',
                'statusCode' => 400
            ], 200);
        }

    //   bot detection 
        $renderTime = (int)$this->input->post('_form_render_time');

        if (!$renderTime || $renderTime < 2000) {
            return $this->response->output([
                'flag' => 'F',
                'msg' => 'Suspicious submission detected',
                'statusCode' => 400
            ], 200);
        }

  
        $formId = $this->input->post('form_id');
        if (empty($formId)) {
            return $this->response->output([
                'flag' => 'F',
                'msg' => 'Missing form_id',
                'statusCode' => 422
            ], 200);
        }

     

        // Get all POST fields
        $postData = $this->input->post();

        // Remove system keys
        unset(
            $postData['form_id'],
            $postData['_form_render_time'],
            $postData['company_website']
        );

        // Optional: sanitize values
        $postData = $this->security->xss_clean($postData);

        $insertData = [
        'form_id' => $formId,
        'submission_data' => json_encode($postData),
        'submitted_at' => date('Y-m-d H:i:s')
       ];

    
     $saved = $this->db->insert('form_submissions', $insertData);

        if (!$saved) {
            return $this->response->output([
                'flag' => 'F',
                'msg' => 'Failed to save submission',
                'statusCode' => 500
            ], 200);
        }

        return $this->response->output([
            'flag' => 'S',
            'msg' => 'Form submitted successfully',
            'statusCode' => 200
        ], 200);
    }

}
