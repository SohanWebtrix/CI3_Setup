<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Shops extends CI_Controller
{
    public function __construct()
    {
            parent::__construct(); // ✅ REQUIRED

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

        //  $this->load->library('Response');
    // $this->load->library('Access'); 
        // $this->load->library("Datatables");
        $this->load->library("Filters");
    }

    public function shopDetails()
    
    {
                // $this->access->checkTokenKey();

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
  
    public function updateShop()
{
            // $this->access->checkTokenKey();

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

    /* ========= REQUIRED ID ========= */
    $shopID = (int)$this->input->post('shopID');
    if (empty($shopID)) {
        $this->response->output([
            'msg' => 'Shop ID is required for update',
            'statusCode' => 400,
            'data' => [],
            'flag' => 'F'
        ], 200);
    }

    log_message('error', 'UPDATE SHOP: shopID = '.$shopID);

    /* ========= VALIDATION ========= */
// Only update fields that are sent
if ($this->input->post('shop_name') !== null) {
    $shopData['shop_name'] =
        $this->validatedata->validate('shop_name', 'Shop Name', true);
}

if ($this->input->post('shop_address') !== null) {
    $shopData['shop_address'] =
        $this->validatedata->validate('shop_address', 'Shop Address', false);
}

if ($this->input->post('shop_email') !== null) {
    $shopData['shop_email'] =
        $this->validatedata->validate('shop_email', 'Shop Email', true);
}

if ($this->input->post('shop_owner') !== null) {
    $shopData['shop_owner'] =
        $this->validatedata->validate('shop_owner', 'Shop Owner', false);
}

if ($this->input->post('status') !== null) {
    $shopData['status'] =
        $this->validatedata->validate('status', 'Status', false);
}

    /* ========= AUDIT ========= */
    $shopData['modified_date'] = date('Y-m-d H:i:s');
    $shopData['modified_by']   = $this->input->post('SadminID');


    /* ========= CHECK SHOP EXISTS ========= */
    $exists = $this->CommonModel->getMasterDetails(
        'shop',
        'shopID',
        ['shopID' => $shopID]
    );

    if (empty($exists)) {
        $this->response->output([
            'msg' => 'Shop not found',
            'statusCode' => 404,
            'data' => [],
            'flag' => 'F'
        ], 200);
    }

    /* ========= UPDATE ========= */
    $this->db->trans_start();

    $this->db->where('shopID', $shopID);
    $updated = $this->db->update('ab_shop', $shopData);

    if (!$updated) {
        $this->db->trans_rollback();
        $this->response->output([
            'msg' => 'Failed to update shop',
            'statusCode' => 500,
            'data' => [],
            'flag' => 'F'
        ], 200);
    }

    $this->db->trans_commit();

    $this->response->output([
        'msg' => 'Shop updated successfully',
        'statusCode' => 200,
        'data' => [],
        'flag' => 'S'
    ], 200);
}

}
