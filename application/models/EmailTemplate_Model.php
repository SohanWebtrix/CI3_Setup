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


public function deleteTemplate($tempUniqueID)
{ 
    $query= $this->db->select('is_sys_temp')
    ->from($this->table)
    ->where('tempUniqueID',$tempUniqueID)
    ->limit(1)
    ->get();

    if($query->num_rows()===0)
        {
            return['status'=>false,'reason'=>'not_found'];
        }
 
        $row = $query->row();

        if(strtolower($row->is_sys_temp)==='yes')
            {
                return['status'=>false,'reason'=>'system_template'];
            }

    $this->db->where('tempUniqueID', $tempUniqueID);
    $this->db->limit(1);
    return $this->db->delete($this->table);
}

public function getTemplateById($tempUniqueID)
{
    $query = $this->db
        ->where('tempUniqueID', $tempUniqueID)
        ->limit(1)
        ->get($this->table);

    if (!$query || $query->num_rows() === 0) {
        return false;
    }

    return $query->row();
}

public function updateTemplateById($tempUniqueID, array $data)
{
    return $this->db
        ->where('tempUniqueID', $tempUniqueID)
        ->limit(1)
        ->update($this->table, $data);
}


}
