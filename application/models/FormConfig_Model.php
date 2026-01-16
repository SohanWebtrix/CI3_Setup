<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class FormConfig_Model extends CI_Model
{
    public function __construct()
{
    parent::__construct();
    $this->load->model('CommonModel');
}


    protected $table = 'form_config';

    public function saveFormDefinition($data, $userId)

    {
        // Check existing form
        // $existing = $this->db
        //     ->get_where($this->table, ['form_id' => $data['form_id']])
        //     ->row();

        $existing = $this->CommonModel->getMasterDetails(
    $this->table,
    'form_id',
    ['form_id' => $data['form_id']]
);

        if ($existing) {
            // UPDATE
            $updateData = [
                'name'          => $data['name'],
                'description'   => $data['description'],
                'definition'    => $data['definition'],
                'updated_by'   => $userId,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->where('form_id', $data['form_id']);
            $updated = $this->db->update($this->table, $updateData);

            return $updated ? ['is_update' => true] : false;
        }

        // INSERT
        $insertData = [
            'form_id'      => $data['form_id'],
            'name'         => $data['name'],
            'description'  => $data['description'],
            'definition'   => $data['definition'],
            'created_by'   => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $inserted = $this->db->insert($this->table, $insertData);

        return $inserted ? ['is_update' => false] : false;
    }


     public function getFormByFormId($formId)
    {

//       $existing = $this->CommonModel->getMasterDetails(
//     $this->table,
//     'form_id',
//     ['form_id' => $data['form_id']]
// );

        $query = $this->db
            ->where('form_id', $formId)
            ->where('status', 'inactive') // optional but recommended
            ->limit(1)
            ->get($this->table);

        if (!$query || $query->num_rows() === 0) {
            return false;
        }

        return $query->row();
    }

   public function updateFormSettings($formId, array $patch)
{

    $table = $this->db->dbprefix($this->table);

    $exists = $this->db
        ->select('definition')
        ->where('form_id', $formId)
        ->get($this->table)
        ->row_array();

    if (!$exists) {
        return false;
    }

    $jsonSetParts = [];
    $bindings = [];

    foreach ($patch as $key => $value) {
        $jsonSetParts[] = "'$.settings.$key', ?";
        $bindings[] = ($value);
    }

    $sql = "
        UPDATE {$table}
        SET definition = JSON_SET(definition, ".implode(',', $jsonSetParts)."),
            updated_at = NOW()
        WHERE form_id = ?
    ";

    $bindings[] = $formId;

    $this->db->query($sql, $bindings);

    $row = $this->db
        ->select("JSON_EXTRACT(definition, '$.settings') AS settings", false)
        ->where('form_id', $formId)
        ->get($this->table)
        ->row_array();

    return json_decode($row['settings'], true);
}

public function getLastUpdatedForm()
{
        log_message('error', 'getLastUpdatedForm model executed.');

    return $this->db
            ->select('form_id, updated_at, created_at')
            ->from($this->table)
            ->order_by('updated_at', 'DESC')   // 🔥 MOST IMPORTANT LINE
            ->limit(1)
            ->get()
            ->row();

}
}
