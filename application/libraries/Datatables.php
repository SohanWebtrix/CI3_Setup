<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Datatables extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        //$this->CI = &get_instance();
        $this->load->database();
        $this->load->dbforge(); // Load the dbforge library
        $this->load->model('CommonModel');
    }

    // Create a new table based on field array
    /*    $fields = array(
    'column_name' => array(
    'type' => 'data_type',
    'constraint' => 'constraint_value',
    'unsigned' => TRUE/FALSE,
    'auto_increment' => TRUE/FALSE,
    // additional properties based on data type
    ),
    // additional columns
    );
    Let's break down the properties commonly used in the field array:
    'column_name': The name of the column in the database table.
    'type': The data type of the column, such as INT, VARCHAR, DATETIME, etc.
    'constraint': The maximum length or value for the column, often used with VARCHAR or INT types.
    'unsigned': Specify whether the column is unsigned, which means it only allows positive values. Use TRUE or FALSE to set it.
    'auto_increment': Specify whether the column should auto-increment on each new row. Use TRUE or FALSE to set it.

    ex
    'id' => array(
    'type' => 'INT',
    'constraint' => 5,
    'unsigned' => TRUE,
    'auto_increment' => TRUE,
    'null' => TRUE,
    'default' => NULL,
    'comments' => '',
    ),

    $fields = array(
    'id' => array(
    'type' => 'INT',
    'constraint' => 5,
    'unsigned' => TRUE,
    'auto_increment' => TRUE
    ),
    'name' => array(
    'type' => 'VARCHAR',
    'constraint' => '100',
    ),
    'email' => array(
    'type' => 'VARCHAR',
    'constraint' => '100',
    ),
    );
    do for each for above array check property of each field and set default as per mysql rule if not passed in call
     */

    // public function create_table($table_name, $fields)
    // {
    //     if (!empty($fields)) {
    //         $this->dbforge->add_field($fields);
    //     }
    //     //$this->dbforge->add_key('id', TRUE);
    //     $this->dbforge->create_table($table_name);
    // }

    public function create_table($table_name, $fields = array())
    {
         if (empty($table_name)) {
            $this->db->trans_rollback();
            $status['msg'] = $this->systemmsg->getErrorCode(282);
            $status['statusCode'] = 994;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        }
        
        $sql = "SHOW TABLES LIKE '" . $this->db->dbprefix . $table_name . "'";
        $query = $this->db->query($sql);
        $rowcount = $query->num_rows();
        if ($rowcount > 0) {
            $this->db->trans_rollback();
            $status['msg'] = $this->systemmsg->getErrorCode(287);
            $status['statusCode'] = 200;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        }

       
        //check is table exit
        $fields1 = array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ),
            'created_by' => array(
                'type' => 'INT',
                'constraint' => 11,
            ),
            'created_date datetime default current_timestamp',
            'modified_by' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => null,
            ),
            'modified_date' => array(
                'type' => 'DATETIME',
                'default' => null,
            ),
        );
        $this->dbforge->add_field($fields1);
        $this->dbforge->add_key('id', true);
        if (!is_array($fields) || empty($fields)) {

            //throw new Exception('Fields must be a non-empty array.');
        } else {
            // Perform additional validations for each field
            // $this->dbforge->add_field("id INT(11) AUTO_INCREMENT");
            // $this->dbforge->add_key('id', TRUE);

            foreach ($fields as $field_name => $field) {

                if (empty($field_name) || !is_string($field_name)) {
                    $this->db->trans_rollback();
                    $status['msg'] = $this->systemmsg->getErrorCode(283);
                    $status['statusCode'] = 994;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
                if (!is_array($field) || empty($field['type']) || !is_string($field['type'])) {
                    $this->db->trans_rollback();
                    //throw new Exception('Invalid field type for ' . $field_name . ', type must be a string.');
                    $status['msg'] = $this->systemmsg->getErrorCode(285);
                    $status['statusCode'] = 994;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
                if (isset($field['constraint']) && (!is_numeric($field['constraint']) || $field['constraint'] <= 0)) {
                    $this->db->trans_rollback();
                    //throw new Exception('Invalid constraint value for ' . $field_name . ', constraint must be a positive numeric value.');
                    $status['msg'] = $this->systemmsg->getErrorCode(286);
                    $status['statusCode'] = 994;
                    $status['data'] = array();
                    $status['flag'] = 'F';
                    $this->response->output($status, 200);
                }
                // Add field as received if auto_increment is set to TRUE
                if (isset($field['auto_increment']) && $field['auto_increment'] === true) {
                    $this->dbforge->add_field("$field_name INT(11) AUTO_INCREMENT");
                    $this->dbforge->add_key($field_name, true);
                } else {
                    $this->dbforge->add_field(array($field_name => $field));
                }
            }
        }
        $this->dbforge->create_table($table_name);
    }

    // Modify an existing table by adding a column
    public function add_column($table_name, $fields,$tabName)
    {
        // chek in core table as well
        $sql = "SHOW COLUMNS FROM " . $this->db->dbprefix . $tabName . "";
        $query = $this->db->query($sql);
        $result = $query->result();
        $fCol = array_column($result, "Field");
        $isexits = false;
        foreach ($fields as $key => $value) {
            if (in_array($key, $fCol)) {
                $isexits = true;
            }
        }
        //var_dump($isexits);
        if ($isexits) {
            $this->db->trans_rollback();
            $status['msg'] = $this->systemmsg->getErrorCode(288);
            $status['statusCode'] = 200;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        }

        $sql = "SHOW COLUMNS FROM " . $this->db->dbprefix . $table_name . "";
        $query = $this->db->query($sql);
        $result = $query->result();
        $fCol = array_column($result, "Field");
        $isexits = false;
        foreach ($fields as $key => $value) {
            //print$key."<br>";
            if (in_array($key, $fCol)) {
                $isexits = true;
            }
        }
        //var_dump($isexits);
        if ($isexits) {
            $this->db->trans_rollback();
            $status['msg'] = $this->systemmsg->getErrorCode(288);
            $status['statusCode'] = 200;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        }
        $this->dbforge->add_column($table_name, $fields);
    }

    // Set primary key for a table
    public function set_primary_key($table_name, $column_name)
    {
        if (empty($column_name)) {
            throw new Exception("Column name cannot be empty for setting primary key.");
        }
        $this->dbforge->add_key($column_name, true);
        $this->dbforge->modify_column($table_name, array($column_name => array('type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'auto_increment' => true)));
    }

    // Modify column in a table
    public function modify_column($table_name, $fields)
    {
        $isexits = false;
        $key = array_keys($fields);
        if (isset($fields[$key[0]]['name']) && $key[0] != $fields[$key[0]]['name']) {
            $sql = "SHOW COLUMNS FROM " . $this->db->dbprefix . $table_name . "";
            $query = $this->db->query($sql);
            $result = $query->result();
            $fCol = array_column($result, "Field");
            $isexits = false;
            foreach ($fields as $key => $value) {
                if (in_array($key[0], $fCol)) {
                    $isexits = true;
                }
            }
            if ($isexits) {
                $this->db->trans_rollback();
                $status['msg'] = $this->systemmsg->getErrorCode(288);
                $status['statusCode'] = 200;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->response->output($status, 200);
            }
        }
        $this->dbforge->modify_column($table_name, $fields);
    }

    // Modify column in a table
    public function remove_column($table_name, $column_name)
    {
        return $this->dbforge->drop_column($table_name, $column_name);
    }

    // Change table type
    public function change_table_type($table_name, $type)
    {
        $this->dbforge->db->query('ALTER TABLE ab_' . $table_name . ' ENGINE = ' . $type);
    }

    // Change table type
    public function check_table_exist($table_name)
    {
        $sql = "SHOW TABLES LIKE '" . $this->db->dbprefix . $table_name . "'";
        $query = $this->db->query($sql);
        return $query->num_rows();
        // if ($rowcount > 0) {
        //     return true;
        // }else{
        //     return false;
        // }
        // $tdata = $this->db->query("SELECT COUNT('ab_" . $table_name . "') as cnt FROM information_schema.tables WHERE table_schema = 'lms' AND table_name = 'ab_" . $table_name . "' LIMIT 1");
        // //echo "SELECT COUNT('ab_" . $table_name . "') as cnt FROM information_schema.tables WHERE table_schema = 'lms' AND table_name = 'ab_" . $table_name . "' LIMIT 1";
        // foreach ($tdata->result() as $row) {
        //     $rcnt = $row->cnt;
        // }
        // return $rcnt; //$tdata;
    }

    // Delete table
    public function delete_table($table_name)
    {
        $this->dbforge->drop_table($table_name, true);
    }
    public function check_field_exists($table_name, $field_name)
    {
        if ($this->db->field_exists($field_name, $table_name)) {
            return true;
        } else {
            return false;
        }
    }
    public function mapDynamicFeilds($module_name, $data)
    {
        $customData = array();
        // get module details
        //$where = array("menuLink" => $module_name);
        $where = array("menuID" => $module_name);
        $menuDetails = $this->CommonModel->getMasterDetails("menu_master", "", $where);
        if (!isset($menuDetails) || empty($menuDetails)) {
            return array();
        }
        if (!isset($data) || empty($data)) {
            return array();
        }
        // get field from database
        $wherec = array();
        $wherec["menuID = "] = $menuDetails[0]->menuID;
        $fieldDetails = $this->CommonModel->GetMasterListDetails($selectC = '', 'dynamic_fields', $wherec, '', '', array(), array());
        foreach ($fieldDetails as $key => $value) {
            if (array_key_exists($value->column_name, $data)) {
                if ($value->fieldType == "Datepicker") {
                    $customData[$value->column_name] = dateFormat($data[$value->column_name]);
                } else {
                    if (!isset($data[$value->column_name]) || empty($data[$value->column_name])) {
                        $customData[$value->column_name] = null;
                    } else {
                        if (is_array($data[$value->column_name])) {
                            $customData[$value->column_name] = implode(',', $data[$value->column_name]);
                        } else {
                            $customData[$value->column_name] = $data[$value->column_name];
                        }
                    }
                }
            }
        }
        return $customData;
    }
    public function getDynamicFeildJoin($isDefualt)
    {
        $_dynamicJoin = array(
            'd_select' => '',
            'd_join' => array(),
        );
        //$_tableName = 'dynamic_' . $this->menuDetails->menuLink;
        // change this to menu id as we can edit the name of the link and it will affect the exiting created dynamic table
        $_tableName = 'dynamic_' . $this->menuDetails->menuID;
        $table_name = $this->menuDetails->table_name;
        if (!$this->dyanamicForm_Fields) {
            return array();
        } else {
            $_alias = uniqid("D") . "_" . substr($_tableName, 8, 4);
            $d_select = ' ';
            $d_columns = [];
            if (isset($this->menuDetails->c_metadata) && !empty($this->menuDetails->c_metadata)) {
                $colData = array_column(json_decode($this->menuDetails->c_metadata), "column_name");
                foreach ($this->dyanamicForm_Fields as $key => $value) {
                    if ($isDefualt == 'no') {
                        if (in_array($value->column_name, $colData)) {
                            $d_select .= ($d_select == ' ') ? $_alias . '.' . $value->column_name : ',' . $_alias . '.' . $value->column_name;
                        }
                    } else {
                        $d_select .= ($d_select == ' ') ? $_alias . '.' . $value->column_name : ',' . $_alias . '.' . $value->column_name;
                    }
                    $d_columns[] = $value->column_name;
                }
            }
            $_dynamicJoin['d_select'] = $d_select;
            if ($this->menuDetails->custom_module == 'yes')    {
                $_primaryKey = $this->getPrimaryKey($table_name, 'y');
                $_dynamicJoin['d_join']['type'] = "LEFT JOIN";
                $_dynamicJoin['d_join']['table'] = $_tableName;
                $_dynamicJoin['d_join']['alias'] = $_alias;
                $_dynamicJoin['d_join']['key1'] = $_primaryKey;
                $_dynamicJoin['d_join']['key2'] = $_primaryKey;
                $_dynamicJoin['d_join']['is_dynamic'] = true;
                $_dynamicJoin['d_join']['column'] = $d_columns;
            } else {
                $_alias = 't';
            }
            
        }
        return $_dynamicJoin;
    }
    public function getMenuDetails($menuID)
    {
        if (!isset($menuID) || empty($menuID)) {
            $status['msg'] = str_replace("{fieldName}", 'Menu ID', $this->systemmsg->getErrorCode(218));
            $this->systemmsg->getErrorCode(218);
            $status['statusCode'] = 343;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        }
        $where = array("menuID" => $menuID);
        $menuDetails = $this->CommonModel->getMasterDetails("menu_master", "", $where);
        if (!isset($menuDetails) || empty($menuDetails)) {
            $status['msg'] = $this->systemmsg->getErrorCode(338);
            $status['statusCode'] = 338;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->response->output($status, 200);
        } else {
            return $menuDetails[0];
        }
    }
    // RETURN PRIMARY KEY
    public function getPrimaryKey($tableName = '', $onlyKey = '')
    {
        $sql = "SHOW COLUMNS FROM ab_" . $tableName;
        $res = $this->CommonModel->getdata($sql, array());
        if (isset($res) && !empty($res)) {
            foreach ($res as $key => $value) {
                if ($value->Key == 'PRI') {
                    if ($onlyKey == 'y') {
                        return $value->Field;
                    } else {
                        $foreignfield[$value->Field] = array(
                            'type' => 'INT',
                            'constraint' => 11,
                            'unsigned' => true,
                        );
                        return $foreignfield;
                    }
                }
            }
        }
    }
}