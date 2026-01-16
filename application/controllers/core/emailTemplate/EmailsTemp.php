<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class EmailsTemp extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('ValidateData');
        $this->load->model('EmailTemplate_Model', 'EmailTemplateModel');
    }

public function saveEmail()
{
    if ($this->input->method(true) !== 'POST') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Invalid request method',
            'statusCode' => 405
        ], 200);
    }

    // ✅ Check file
    if (empty($_FILES['templateFile']['tmp_name'])) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'HTML file is required',
            'statusCode' => 422
        ], 200);
    }

    // ✅ Upload directory
    $uploadDir = FCPATH . 'uploads/email_templates/';// FCPATH - Absolute path to project root
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ✅ Generate safe unique filename
    $originalName = $_FILES['templateFile']['name'];
    $extension    = pathinfo($originalName, PATHINFO_EXTENSION);

    if ($extension !== 'html') {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Only HTML files are allowed',
            'statusCode' => 422
        ], 200);
    }
    

    $fileName = 'email_' . time() . '_' . uniqid() . '.html'; // create unique file name
    $filePath = $uploadDir . $fileName;

    // ✅ Move uploaded file (IMPORTANT)
    if (!move_uploaded_file($_FILES['templateFile']['tmp_name'], $filePath)) {
        return $this->response->output([
            'flag' => 'F',
            'msg' => 'Failed to save file',
            'statusCode' => 500
        ], 200);
    }

    // ✅ Other POST fields
    $tempName       = $this->input->post('tempName', true);
    $subjectOfEmail = $this->input->post('subjectOfEmail', true);
    $smsContent     = $this->input->post('smsContent', true);
    $emailSource    = $this->input->post('emailSource', false);

    $userId = $this->input->post('SadminID') ?: 1;

    // ✅ Save ONLY FILE PATH in DB
    $data = [
        'tempUniqueID'   => uniqid('TMP'),
        'is_sys_temp'    => 'no',
        'tempName'       => $tempName,
        'subjectOfEmail' => $subjectOfEmail,
        'emailContent'   => 'uploads/email_templates/' . $fileName, // ✅ FILE PATH
        'emailSource'    => $emailSource,
        'smsContent'     => $smsContent,
        'created_by'     => $userId,
        'created_date'   => date('Y-m-d'),
        'status'         => 'active'
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


}
