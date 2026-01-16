<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class EmailTemplate_Model extends CI_Model
{

    public function __construct()
{
    parent::__construct();
    $this->load->model('CommonModel');
}


    protected $table = 'email_master';

    public function saveTemplate(array $data)
    {
        return $this->db->insert($this->table, $data);
    }
}
