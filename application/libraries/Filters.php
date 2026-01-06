<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[\AllowDynamicProperties]
class Filters
{
    public $module = "";
    public $module_data = "";
    public $menuID = "";
    public $dyanamicForm_Fields = "";
    public $menuDetails = "";
    public $linkedFields = null;
    public $SadminID = null;
    public $defaultSelect = "";
    protected $customCol = [];
    protected $columnNames = [];
    
    public function __construct($params = []){
        $this->CI = &get_instance();
         if (isset($params['customCol'])) {
            $this->customCol = $params['customCol'];
        }elseif(isset($this->CI->customCol)){
            $this->customCol = $this->CI->customCol;
        }
        if (isset($params['columnNames'])) {
            $this->columnNames = $params['columnNames'];
        }elseif(isset($this->CI->columnNames)){
            $this->columnNames = $this->CI->columnNames;
        }
    }
    public function _initialize($isDefualt='no'){
        
        $this->menuID = $this->CI->menuID;
        $this->getMenuData();
        $this->CI->dyanamicForm_Fields = $this->dyanamicForm_Fields;
        $this->CI->menuDetails = $this->menuDetails;
        $postData = $_POST;
        $this->CI->whereData = $this->prepareFilterData($postData,$isDefualt);
        if (isset($this->menuDetails->c_metadata) && !empty($this->menuDetails->c_metadata)) {
            $colData = array_column(json_decode($this->menuDetails->c_metadata), "column_name");
            foreach ($this->customCol as $cu_column_key => $cu_column) {
                if (in_array($cu_column_key, $colData)) {
                    $this->columnNames[$cu_column_key] = $cu_column;
                }
            }
        }
        $selectC = $this->CI->whereData['select'];
        foreach ($this->columnNames as $columnName => $columnData) {
			$jkey = count($this->CI->whereData['join']) + 1;
			$this->CI->whereData['join'][$jkey]['type'] = "LEFT JOIN";
			$this->CI->whereData['join'][$jkey]['table'] = $columnData["table"];
			$this->CI->whereData['join'][$jkey]['alias'] = $columnData["alias"];
			$this->CI->whereData['join'][$jkey]['key1'] = $columnName;
			$this->CI->whereData['join'][$jkey]['key2'] = $columnData["key2"];
			$this->CI->whereData['join'][$jkey]['column'] = $columnData["column"];
            if ($isDefualt == 'no') {
                $columnNameShow = $columnData["column"];
                $selectC .= "," . $columnData["alias"] . "." . $columnNameShow . " as " . $columnName;
                if(isset($columnData["select"]) && $columnData["select"] != ''){
                    $selectC .= ",".$columnData["select"];
                }
            }
            $selectC = ltrim($selectC, ',');   
		}
        $orderBy =  $this->CI->whereData['other']['orderBy'];
        $found = false;
        foreach ($this->CI->whereData['join'] as $key => $value) {
            if (isset($value['is_dynamic'])) {
                // Make sure $value['column'] is treated as an array
                $columns = (array) $value['column'];
                if (in_array($orderBy, $columns)) {
                    $this->CI->whereData['other']['orderBy'] = $value['alias'] . '.' . $orderBy;
                    $found = true;
                    break;
                }
            } else {
                if ($orderBy === $value['key1']) {
                    $this->CI->whereData['other']['orderBy'] = $value['alias'] . '.' . $value['column'];
                    $found = true;
                    break;
                }
            }
        }
        if (isset($orderBy) && !empty($orderBy) && !$found) {
            $this->CI->whereData['other']['orderBy'] = 't.' . $orderBy;
        }
         if (isset($other['orderBy']) && !empty($other['orderBy']) &&  !$found) {
            $other['orderBy'] = 't.' . $other['orderBy'];
        }

        // foreach ($this->CI->whereData['join'] as $key => $value) {
        //     if(isset($value['is_dynamic'])){
        //         if (in_array($this->CI->whereData['other']['orderBy'], $value['column'])){
        //             $this->CI->whereData['other']['orderBy'] = $value['alias'].'.'.$this->CI->whereData['orderBy']; 
        //         }else{
        //             $this->CI->whereData['other']['orderBy'] = 't.'.$this->CI->whereData['other']['orderBy']; 
        //         }
        //     }else{
        //         if ($this->CI->whereData['other']['orderBy'] == $value['key1']) {
        //             $this->CI->whereData['other']['orderBy'] = $value['alias'].'.'.$value['key1']; 
        //         }else{
        //             $this->CI->whereData['other']['orderBy'] = 't.'.$value['key1']; 
        //         }
        //     }
        // }
        $this->CI->whereData['select'] = $selectC ;
    }
    public function prepareFilterData($appFilterData,$isDefualt = 'no'){
        $loadFrom = 'desktop';
        if (isset($appFilterData['loadFrom']) && !empty($appFilterData['loadFrom'])) {
            $loadFrom = $appFilterData['loadFrom'];
        }
        $prevOp = $whereStr = $stdCol = $freeTextSearch = "";
        $whereData = $wherec = $other = $join = $orConditions = $OR = array();
        $today = date("Y-m-d");
        // IF FILTER FILTER IS APPLIED
        if ( isset($appFilterData['filterJson']) && !empty($appFilterData['filterJson'])) {
            $filterJson = $appFilterData['filterJson'];
            if (is_string($filterJson)) {
                $filterJson = json_decode($filterJson, true);
            }
        }
        if (isset($appFilterData['freeTextSearch']) && !empty($appFilterData['freeTextSearch'])) {
            $freeTextSearch = $appFilterData['freeTextSearch'];
        }
        $standardFieldDates = array('created_date', 'modified_date');
        // ORDER BY
        if (isset($appFilterData['orderBy']) && !empty($appFilterData['orderBy'])) {
            $orderBy = $appFilterData['orderBy'];
        } else {
            $orderBy = "";
        }
        // ORDER
        if (isset($appFilterData['order']) && !empty($appFilterData['order'])) {
            $order = $appFilterData['order'];
        } else {
            $order = "";
        }
        $subSql = array();
        if (!isset($orderBy) || empty($orderBy)) {
            $orderBy = "";
            $order = "";
        }
        $this->SadminID = $appFilterData['SadminID'];
        unset($appFilterData['menuId'], $appFilterData['curpage'], $appFilterData['SadminID'], $appFilterData['accessFrom'], $appFilterData['orderBy'], $appFilterData['order'], $appFilterData['filter_id']);
        
        if ($loadFrom == 'mobile') {
            $this->menuDetails->c_metadata = $this->menuDetails->m_metadata;
        }
        // GET TABLE COLUMNS AND COLUMNS EXTRA DETAILS
        $sql = "SHOW COLUMNS FROM " . $this->CI->db->dbprefix . $this->menuDetails->table_name;
        $metaDetails = $this->CI->CommonModel->getdata($sql, array());
        $other = array("orderBy" => $orderBy, "order" => $order);
        if (empty($this->menuDetails->c_metadata)) {
            //print_r($this->CI->defaultColumns);
            // check default data availble or not.
            if(isset($this->CI->defaultColumns) && !empty($this->CI->defaultColumns)){
                $cData = $this->prepareDefaultData($this->CI->defaultColumns);
                
            }else{
                $status['msg'] = $this->CI->systemmsg->getErrorCode(332);
                $status['statusCode'] = 332;
                $status['data'] = array();
                $status['flag'] = 'F';
                $this->CI->response->output($status, 200);
            }
            //print_r($cData);exit;
        }else{
            $cData = json_decode($this->menuDetails->c_metadata, true);
        }
        //print_r($cData);
        $columns = array_column($metaDetails, 'Field');
        if (isset($filterJson) && !empty($filterJson)) {
            foreach ($filterJson as $key => $filter) {
                // GET TYPE OF COLUMN FROM TABLE STUCTURE
                $type = '';
                if ($this->validateCondition($filter, $columns)) {
                    foreach ($metaDetails as $meta) {
                        if ($meta->Field == $filter['columnName']) {
                            $type = $meta->Type;
                            break;
                        }
                    }

                    if (str_contains($type, 'timestamp') || str_contains($type, 'datetime') || str_contains($type, 'date')) {
                        if ($filter['condition'] != 'date_range') {
                            $filter['value'] = date("Y-m-d", strtotime($filter['value']));
                        } else {
                            $date = explode('/', $filter['value']);
                            if (count($date) > 1) {
                                $date[0] = date("Y-m-d", strtotime($date[0]));
                                $date[1] = date("Y-m-d", strtotime($date[1]));
                            }
                        }
                    }

                    // EACH CONDITION THAT SELECTED
                    switch ($filter['condition']) {
                        case 'equal_to':
                            if (str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
                                $wherec["Date(t." . $filter['columnName'] . ")"] = "='" . $filter['value'] . "'";
                            } else {
                                $checkMul = explode(",", $filter['value']);
                                if (count($checkMul) > 1) {
                                    $filter_value_string = str_replace(",", "','", $filter['value']);
                                    $wherec["t." . $filter['columnName']] = " IN('" . $filter_value_string . "')";
                                } else {
                                    $wherec["t." . $filter['columnName']] = "='" . $filter['value'] . "'";
                                }
                            }
                            break;
                        case 'not_equal_to':
                            if (str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
                                $wherec["Date(t." . $filter['columnName'] . ")"] = "!='" . $filter['value'] . "'";
                            } else {
                                $checkMul = explode(",", $filter['value']);
                                if (count($checkMul) > 1) {
                                    $filter_value_string = str_replace(",", "','", $filter['value']);
                                    $wherec["t." . $filter['columnName']] = "NOT IN('" . $filter_value_string . "')";
                                } else {
                                    $wherec["t." . $filter['columnName']] = "!='" . $filter['value'] . "'";
                                }
                            }
                            break;
                        case 'is_empty':
                            // print_r($type);
                            if (str_contains($type, 'timestamp') || str_contains($type, 'datetime') || str_contains($type, 'date')) {
                                $wherec["t." . $filter['columnName']] = "IS NULL ";
                            } else {
                                $wherec["t." . $filter['columnName']] = "IS NULL OR t." . $filter['columnName'] . " = ''";
                            }
                            break;
                        case 'is_not_empty':
                            if (str_contains($type, 'timestamp') || str_contains($type, 'datetime') || str_contains($type, 'date')) {
                                $wherec["t." . $filter['columnName']] = "IS NOT NULL ";
                            }else{
                                $wherec["t." . $filter['columnName']] = "IS NOT NULL AND t." . $filter['columnName'] . " != ''";
                            }
                            break;
                        case 'greater_than':
                            $wherec["t." . $filter['columnName']] = ">'" . $filter['value'] . "'";
                            break;
                        case 'less_than':
                            $wherec["t." . $filter['columnName']] = "<'" . $filter['value'] . "'";
                            break;
                        case 'start_with':
                            if (str_contains($type, 'varchar') || str_contains($type, 'text') || str_contains($type, 'enum')) {
                                $wherec["t." . $filter['columnName']] = "LIKE '" . $filter['value'] . "%'";
                            } else if (str_contains($type, 'int') || str_contains($type, 'float') || str_contains($type, 'decimal')) {
                                $wherec["t." . $filter['columnName']] = ">='" . $filter['value'] . "'";
                            } else if (str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
                                $wherec["t." . $filter['columnName']] = " BETWEEN '" . $filter['value'] . "' AND '" . $today . "'";
                            }
                            break;
                        case 'end_with':
                            if (str_contains($type, 'varchar') || str_contains($type, 'text') || str_contains($type, 'enum')) {
                                $wherec["t." . $filter['columnName']] = "LIKE '%" . $filter['value'] . "'";
                            } else if (str_contains($type, 'int') || str_contains($type, 'float')) {
                                $wherec["t." . $filter['columnName']] = "<='" . $filter['value'] . "'";
                            } else if (str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
                                $wherec["t." . $filter['columnName']] = "<='" . $filter['value'] . " 23:59:59'";
                            }
                            break;
                        case 'is_in':
                            if (str_contains($type, 'varchar') || str_contains($type, 'text') || str_contains($type, 'enum')) {
                                $wherec["t." . $filter['columnName'] . " like "] = "'%" . $filter['value'] . "%'";
                            } else {
                                $filter_value_string = str_replace(",", "','", $filter['value']);
                                $wherec["t." . $filter['columnName']] = " IN('" . $filter_value_string . "')";
                            }
                            break;
                        case 'exact':
                            $wherec["Date(t." . $filter['columnName'] . ")"] = "='" . $filter['value'] . "'";
                            break;
                        case 'range':
                            $value = explode('-', $filter['value']);
                            if (count($value) > 1) {
                                $wherec["t." . $filter['columnName']] = " BETWEEN '" . $value[0] . "' AND '" . $value[1] . "'";
                            }
                            break;
                        case 'date_range':
                            if (count($date) > 1) {
                                $wherec["t." . $filter['columnName']] = " BETWEEN '" . $date[0] . "' AND '" . $date[1] . "'";
                            }
                            break;
                        case 'this_month':
                            $firstDayOfMonth = date('Y-m-01');
                            $lastDayOfMonth = date('Y-m-t');
                            $wherec["t." . $filter['columnName']] = " BETWEEN '" . $firstDayOfMonth . "' AND '" . $lastDayOfMonth . "'";
                            break;
                        case 'exact_date':
                            $wherec["Date(t." . $filter['columnName'] . ")"] = "='" . $filter['value'] . "'";
                            break;
                        case 'this_week':
                            $newDate = new DateTime();
                            $l_monday = $newDate->modify('last monday');
                            $l_monday = $l_monday->format('Y-m-d');
                            $wherec["t." . $filter['columnName']] = " BETWEEN '" . $l_monday . "' AND '" . $today . " 23:59:59'"; // }
                            break;
                        case 'today':
                            $today = date('Y-m-d');
                            $wherec["Date(t." . $filter['columnName'] . ")"] = "='" . $today . "'";
                            break;
                        case 'yesterday':
                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                            $wherec["Date(t." . $filter['columnName'] . ")"] = "='" . $yesterday . "'";
                            break;
                        case 'tomorrow':
                            $tomorrow = date('Y-m-d', strtotime('+1 day'));
                            $wherec["Date(t." . $filter['columnName'] . ")"] = "='" . $tomorrow . "'";
                            break;
                        default:
                            break;
                    }

                    // APPEND LOGICAL OPERATOR HERE
                    // if (isset($filter['logicalOp']) && !empty($filter['logicalOp'])) {
                    //     switch ($prevOp) {
                    //         case 'OR':
                    //                 $OR["t.".$filter['columnName']] = $wherec["t.".$filter['columnName']];
                    //                 unset($wherec["t.".$filter['columnName']]);
                    //             break;
                    //         case 'AND':
                    //                 $wherec["t.".$filter['columnName']] = $wherec["t.".$filter['columnName']];
                    //                 if (strpos($wherec["t.".$filter['columnName']],'OR')) {
                    //                     $wherec["t.".$filter['columnName']] = $wherec["t.".$filter['columnName']];
                    //                 }
                    //             break;
                    //         default:
                    //             break;
                    //     }
                    // }
                    // STORE LOGICAL OPERATOR FOR NEXT CONDITION
                    // $prevOp = $filter['logicalOp'];

                    // FOR NEXT CONDITION THAT MIGHT BE OVER-RIDDEN
                    // if(str_contains($type, 'timestamp') || str_contains($type, 'datetime')){
                    //     $dates["t.".$filter['columnName']]['value'] = $filter['value'];
                    //     $dates["t.".$filter['columnName']]['logical_op'] = $filter['logicalOp'];
                    //     $dates["t.".$filter['columnName']]['condition'] = $filter['condition'];
                    // }
                }
            }
        }
        $whereR = $otherR = $joinR  = $join= array();
        $extraData = array();
        $selectC = "";
        // SELECT FOR DYANAMIC FIELDS
        if (isset($this->menuDetails->c_metadata) && !empty($this->menuDetails->c_metadata)) {            
           // $cData = json_decode($this->menuDetails->c_metadata);
            $sql = "SHOW KEYS FROM " . $this->CI->db->dbprefix . $this->menuDetails->table_name . " WHERE Key_name = 'PRIMARY'";
            $primaryData = $this->CI->CommonModel->getdata($sql, array());
            $ccData = array_column($cData, 'column_name');
            if (isset($this->dyanamicForm_Fields) && !empty($this->dyanamicForm_Fields)) {
                if ($this->menuDetails->custom_module == 'yes') {
                    $columnNamesToRemove = array_column($this->dyanamicForm_Fields, 'column_name');
                    $ccData = array_filter($ccData, function ($field) use ($columnNamesToRemove) {
                        return !in_array($field, $columnNamesToRemove);
                    });
                }
            }
            $fieldIdDetails = array_filter(array_column($cData, 'fieldID'), 'strlen');
            if (isset($ccData) && !empty($ccData)) {
                foreach ($ccData as $key => $value) {
                    if ($value != "") {
                        $ccData[$key] = "t." . $value;
                    }
                }
            }
            // CHECK IF FIELD IS LINKED WITH
            $joinR[0]['type'] = "LEFT JOIN";
            $joinR[0]['table'] = "menu_master";
            $joinR[0]['alias'] = "mm";
            $joinR[0]['key1'] = "linkedWith";
            $joinR[0]['key2'] = "menuID";
            if (isset($fieldIdDetails) && !empty($fieldIdDetails)) {
                $otherR['whereIn'] = "fieldID";
                $otherR['whereData'] = implode(",", $fieldIdDetails);
            }
            $whereR['t.menuID'] = "= " . $this->menuID;
            $whereR['linkedWith'] = "!= ''";
            // GET DYNAMIC COLUMNS
            $dyCol = array_column($cData, 'column_name');
            $this->linkedFields = $this->CI->CommonModel->GetMasterListDetails("t.allowMultiSelect,t.fieldOptions,t.column_name,t.fieldID,t.linkedWith,mm.menuID,mm.table_name", "dynamic_fields", $whereR, '', '', $joinR, $otherR);
            
            if (isset($primaryData) && !empty($primaryData)) {
                if (!in_array($primaryData[0]->Column_name, $ccData)) {
                    array_unshift($ccData, "t." . $primaryData[0]->Column_name);
                }
            }
            
            if ($this->menuDetails->custom_module == 'no') {
                $_dynamicJoin = [] ;
            }else{
                $_dynamicJoin =   $this->CI->datatables->getDynamicFeildJoin($isDefualt);
            }
            if (!empty($_dynamicJoin)) {
				$selectC = ($selectC != "") ? $selectC.','.$_dynamicJoin['d_select'] : $_dynamicJoin['d_select'] ;
				if (!empty($_dynamicJoin['d_join'])) {
                    $jkey = count($join) + 1;
                    $join[$jkey] = $_dynamicJoin['d_join'];
                }
			}
       
            foreach ($this->linkedFields as $key => $value) {
                if (!in_array($value->column_name, $dyCol)) {
                    break;
                }
                $chkcol = "t." . $value->column_name;
                if (in_array($chkcol, $ccData)) {
                    $ek = array_keys($ccData, "t." . $value->column_name);
                    if (!empty($ek)) {
                        unset($ccData[$ek[0]]);
                    }
                }
                $primaryData2 = array();
                if ($this->menuDetails->custom_module == 'no') {
                    $_dAlias = 't';
                }else{
                    $_dAlias = $_dynamicJoin['d_join']['alias'];
                }
                if ($value->allowMultiSelect == "yes") {
                    $sql = "SHOW KEYS FROM " . $this->CI->db->dbprefix . $value->table_name . " WHERE Key_name = 'PRIMARY'";
                    $primaryData2 = $this->CI->CommonModel->getdata($sql, array());
                    $subSql = "( SELECT GROUP_CONCAT(" . $value->fieldOptions . ") FROM " . $this->CI->db->dbprefix . $value->table_name . " WHERE FIND_IN_SET(" . $primaryData2[0]->Column_name . ",".$_dAlias.".". $value->column_name . "))";
                    $extraData[] = $subSql . " AS " . $value->linkedWith . "_" . trim($value->column_name);
                } else {
                    $sql = "SHOW KEYS FROM " . $this->CI->db->dbprefix . $value->table_name . " WHERE Key_name = 'PRIMARY'";
                    $primaryData2 = $this->CI->CommonModel->getdata($sql, array());
                    $last = count($join)+1;
                    $join[$last]['type'] = "LEFT JOIN";
                    $join[$last]['table'] = $value->table_name;
                    $join[$last]['alias'] = uniqid("W") . "_" . substr($value->table_name, 0, 2); //"ws_".substr($value->table_name,0,2);
                    $join[$last]['key1Alias'] = $_dAlias; //"ws_".substr($value->table_name,0,2);
                    $join[$last]['key1'] = $value->column_name;
                    $join[$last]['key2'] = $primaryData2[0]->Column_name;
                    $extraData[] = ($isDefualt == 'yes') ? $join[$last]['alias'] . "." . $value->fieldOptions . " AS " . $value->linkedWith . "_" . trim($value->column_name) : $join[$last]['alias'] . "." . $value->fieldOptions . " AS " .trim($value->column_name); //."_".$value->fieldOptions;//$value->fieldOptions;
                }
            }
            $selectC = (trim($selectC) != "") ? $selectC.','.implode(",", array_merge($ccData, $extraData)) : implode(",", array_merge($ccData, $extraData));
        }else{
             $selectC = $this->defaultSelect;
        }
        // print_r($selectC);exit; 
        // SELECT FOR SQL
        $_primaryColumn = $this->CI->datatables->getPrimaryKey($this->menuDetails->table_name,'y');
        if (!strpos($selectC,'t.'.$_primaryColumn)) {
            $selectC = (trim($selectC) != "") ? 't.'.$_primaryColumn.','.$selectC : 't.'.$_primaryColumn."";
        }
        // SUMMARIZED DATA
        $other['OR'] = $OR;
        $other["freeTextSearch"] = $freeTextSearch;
        $whereData["select"] = $selectC;
        $whereData["join"] = $join;
        $whereData["wherec"] = $wherec;
        $whereData["other"] = $other;
        return $whereData;
    }
    public function validateCondition($filter, $columns){
        if (!in_array($filter['columnName'], $columns)) {
            return false;
        }
        if (isset($filter['condition']) && !empty($filter['condition'])) {
            if (($filter['condition'] == 'is_empty' || $filter['condition'] == 'is_not_empty' || $filter['condition'] == 'this_month' || $filter['condition'] == 'this_week' || $filter['condition'] == 'today'|| $filter['condition'] == 'tomorrow'|| $filter['condition'] == 'yesterday') && $filter['value'] == '') {
                return true;
            } else if ($filter['value'] == '') {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
    public function getMenuData(){
        if (!isset($this->menuID) && !isset($this->menuID)) {
            $status['msg'] = $this->CI->systemmsg->getErrorCode(338);
            $status['statusCode'] = 338;
            $status['data'] = array();
            $status['flag'] = 'F';
            $this->CI->response->output($status, 200);
        }
        $join = $other = array();
        $join[0]['type'] = "LEFT JOIN";
        $join[0]['table'] = "menu_master";
        $join[0]['alias'] = "m";
        $join[0]['key1'] = "menuID";
        $join[0]['key2'] = "menuID";
        $dynamicFieldHtml = "";
        $wherec["m.menuID="] = "'" . $this->menuID . "'";
        $wherec["t.status"] = "IN ('active')";
        $other = array("orderBy" => "fieldIndex", "order" => "ASC");
        $dynamicFields = $this->CI->CommonModel->GetMasterListDetails($selectC = 't.*,m.menuLink', 'dynamic_fields', $wherec, '', '', $join, $other);
        $wheredata["menuID"] = $this->menuID;
        $dynamicFieldsMeta = $this->CI->CommonModel->getMasterDetails("menu_master", "*", $wheredata);
        // need to reassign c_metadata as sote for users.
        $whereColData["menu_id"] = $this->menuID;
        $whereColData["user_id"] = $this->CI->input->post('SadminID');
        $dynamicColumnArrangement = $this->CI->CommonModel->getMasterDetails("user_column_data", "c_metadata,m_metadata", $whereColData);
        
        $this->dyanamicForm_Fields = $dynamicFields;
        if (isset($dynamicFieldsMeta) && !empty($dynamicFieldsMeta)) {
            if (!empty($dynamicColumnArrangement[0]) && isset($dynamicColumnArrangement[0]->c_metadata)) {
                $dynamicFieldsMeta[0]->c_metadata = $dynamicColumnArrangement[0]->c_metadata;
            }else{
                $dynamicFieldsMeta[0]->c_metadata = [];
            }
            if (!empty($dynamicColumnArrangement[0]) && isset($dynamicColumnArrangement[0]->m_metadata) && !empty($dynamicColumnArrangement->m_metadata)) {
                $dynamicFieldsMeta[0]->m_metadata = $dynamicColumnArrangement[0]->m_metadata;
            }else{
                $dynamicFieldsMeta[0]->m_metadata = [];
            }
            $this->menuDetails = $dynamicFieldsMeta[0];
        } else {
            $this->menuDetails = array();
        }
    }
    // public function upsertDynamicData($updated_id = ""){
    //     $insert_id = $this->CI->db->insert_id();
    //     $menuDetails = $this->CI->datatables->getMenuDetails($this->CI->menuID);
    //     $_primaryKey = $this->CI->datatables->getPrimaryKey($menuDetails->table_name,'y');
    // 	$fieldData =  $this->CI->datatables->mapDynamicFeilds($menuDetails->menuLink, $this->CI->input->post());
        
    //     if (!empty($fieldData)) {
    //         if (empty($updated_id)) {
    //             if (!empty($fieldData)) {
    //                 $fieldData[$_primaryKey] = $insert_id;
    //                 $iscreated2 = $this->CI->CommonModel->saveMasterDetails('dynamic_' . $menuDetails->menuLink, $fieldData);
    //                 if (!$iscreated2) {
    //                     $this->CI->db->trans_rollback();
    //                     $status['msg'] = $this->CI->systemmsg->getErrorCode(998);
    //                     $status['statusCode'] = 998;
    //                     $status['data'] = array();
    //                     $status['flag'] = 'F';
    //                     $this->CI->response->output($status, 200);
    //                 }
    //             }
    //         }else{
    //             //POST
    //             $whereDy[$_primaryKey] = $updated_id;
    //             $dyDataExist = $this->CI->CommonModel->getMasterDetails('dynamic_' . $menuDetails->menuLink,'', $whereDy);
    //             if (empty($dyDataExist)) {
    //                 $fieldData[$_primaryKey] = $updated_id;
    //                 $iscreated2 = $this->CI->CommonModel->saveMasterDetails('dynamic_' . $menuDetails->menuLink, $fieldData);
    //             }else{
    //                 $iscreated2 = $this->CI->CommonModel->updateMasterDetails('dynamic_' . $menuDetails->menuLink, $fieldData, $whereDy);   
    //             }
    //             if (!$iscreated2) {
    //                 $this->CI->db->trans_rollback();
    //                 $status['msg'] = $this->CI->systemmsg->getErrorCode(998);
    //                 $status['statusCode'] = 998;
    //                 $status['data'] = array();
    //                 $status['flag'] = 'F';
    //                 $this->CI->response->output($status, 200);
    //             }
    //         }
    //     }
    // }
    public function upsertDynamicData($updated_id = "")
{
    $CI = $this->CI;

    // record id of the main row (for create, taken from last insert)
    $insert_id  = $CI->db->insert_id();

    $menuDetails = $CI->datatables->getMenuDetails($CI->menuID);
    $_primaryKey = $CI->datatables->getPrimaryKey($menuDetails->table_name, 'y');
    //$module      = $menuDetails->menuLink;  // e.g. 'customer'
    // change this to menu id as we can edit the name of the link and it will affect the exiting created dynamic table
    $module      = $menuDetails->menuID;  // e.g. 'customer'
    $moduleId    = (int)$CI->menuID;        // store menuID as module_id

    // Map request → dynamic columns
    $fieldData = $CI->datatables->mapDynamicFeilds($module, $CI->input->post());

    // Load field defs (identify select-types)
    $defs = $CI->CommonModel->GetMasterListDetails(
        $selectC = 'fieldID, column_name, fieldType, source_type, allowMultiSelect, linkedWith',
        'dynamic_fields',
        ['menuId' => "='" . $CI->menuID . "'"],
        '', '', [], ['resultType' => 'array']
    );

    $byCol = [];
    foreach ($defs as $d) {
        $col = $d['column_name'] ?? null;
        if (!$col) continue;
        $t = strtolower($d['fieldType']);
        $byCol[$col] = [
            'fieldID'   => (int)$d['fieldID'],
            '_is_select'=> in_array($t, ['radiolist','dropdown','checkboxlist'], true),
            '_is_multi' => ($d['allowMultiSelect'] === "yes") ? 1 : 0,
            '_source'   => strtolower($d['source_type'] ?? 'local_list'), // 'local_list' | 'linked_table'
        ];
    }
    // Split: non-select stay in dynamic_{module}; select via dynamic_values_option
    $fieldDataClean = [];
    $selectPayload  = []; // each: [field_id, is_multi, source, value(csv|array|scalar)]

    foreach (($fieldData ?? []) as $col => $val) {
        $def = $byCol[$col] ?? null;
        if (!$def || !$def['_is_select']) {
            $fieldDataClean[$col] = $val;
            continue;
        }
        $selectPayload[] = [
            'field_id' => $def['fieldID'],
            'is_multi' => $def['_is_multi'] ? 1 : 0,
            'source'   => $def['_source'],
            'value'    => $val,
        ];
        // Do NOT keep this select column in dynamic_{module}
    }

    // Save non-select dynamic fields to dynamic_{module}
    if (!empty($fieldDataClean)) {
        if (empty($updated_id)) {
            $fieldDataClean[$_primaryKey] = $insert_id;
            $ok = $CI->CommonModel->saveMasterDetails('dynamic_' . $module, $fieldDataClean);
        } else {
            $whereDy[$_primaryKey] = $updated_id;
            $exists = $CI->CommonModel->getMasterDetails('dynamic_' . $module, '', $whereDy);
            if (empty($exists)) {
                $fieldDataClean[$_primaryKey] = $updated_id;
                $ok = $CI->CommonModel->saveMasterDetails('dynamic_' . $module, $fieldDataClean);
            } else {
                $ok = $CI->CommonModel->updateMasterDetails('dynamic_' . $module, $fieldDataClean, $whereDy);
            }
        }
        if (empty($ok)) {
            $CI->db->trans_rollback();
            $status = [
                'msg' => $CI->systemmsg->getErrorCode(998),
                'statusCode' => 998,
                'data' => [],
                'flag' => 'F'
            ];
            $CI->response->output($status, 200);
        }
    }

    // Final record id for options
    $recordId = !empty($updated_id) ? (int)$updated_id : (int)$insert_id;

    if (empty($selectPayload)) return;
    // Diff-based upsert per select field
    foreach ($selectPayload as $r) {
        $fid     = (int)$r['field_id'];
        $isMulti = $r['is_multi'] ? 1 : 0;
        $source  = $r['source'];   // 'local_list' | 'linked_table'
        $val     = $r['value'];

        // Desired IDs (strings) from CSV/array/scalar; for single, keep first only
        $desired = $this->csvToArray($val);
        if (!$isMulti && count($desired) > 1) $desired = array_slice($desired, 0, 1);

        // Normalize to rows we want (IDs only, no label lookups)
        $want = []; // key => row
        $pos  = 0;
        foreach ($desired as $v) {
            $v = trim((string)$v);
            if ($v === '' || !ctype_digit($v)) continue; // only numeric IDs are valid

            $row = [
                'module_id' => $moduleId,
                'record_id' => $recordId,
                'field_id'  => $fid,
                'option_id' => null,
                'linked_id' => null,
                'position'  => $pos++,
                'is_multi'  => $isMulti,
            ];

            if ($source === 'linked_table') {
                $row['linked_id'] = (int)$v;
                $key = 'L:' . $row['linked_id'];
            } else {
                $row['option_id'] = (int)$v;
                $key = 'O:' . $row['option_id'];
            }

            $want[$key] = $row;
        }

        // Existing rows for this field
        $existingRows = $CI->CommonModel->GetMasterListDetails(
            $selectC='id, option_id, linked_id, position, is_multi',
            'dynamic_values_option',
            [
                'module_id' => '=' . $moduleId,
                'record_id' => '=' . $recordId,
                'field_id'  => '=' . $fid,
            ],
            '', '', [], ['orderBy' => 'position', 'order' => 'ASC', 'resultType' => 'array']
        );

        $have = [];
        foreach ($existingRows as $er) {
            $k = !empty($er['option_id']) ? ('O:' . (int)$er['option_id'])
                 : (!empty($er['linked_id']) ? ('L:' . (int)$er['linked_id']) : null);
            if ($k !== null) $have[$k] = $er;
        }

        // Delete missing
        $toDeleteIds = [];
        foreach ($have as $k => $er) {
            if (!isset($want[$k])) $toDeleteIds[] = (int)$er['id'];
        }
        if (!empty($toDeleteIds)) {
            $CI->db->where_in('id', $toDeleteIds)->delete('dynamic_values_option');
        }

        // Insert new
        $toInsert = [];
        foreach ($want as $k => $wr) {
            if (!isset($have[$k])) $toInsert[] = $wr;
        }
        if (!empty($toInsert)) {
            if (method_exists($CI->db, 'insert_batch')) {
                $CI->db->insert_batch('dynamic_values_option', $toInsert);
            } else {
                foreach ($toInsert as $row) $CI->db->insert('dynamic_values_option', $row);
            }
        }

        // Update position / is_multi if changed
        foreach ($want as $k => $wr) {
            if (!isset($have[$k])) continue;
            $er = $have[$k];
            if ((int)$er['position'] !== (int)$wr['position'] ||
                (int)$er['is_multi'] !== (int)$wr['is_multi']) {
                $CI->db->where('id', (int)$er['id'])
                       ->update('dynamic_values_option', [
                           'position' => (int)$wr['position'],
                           'is_multi' => (int)$wr['is_multi'],
                       ]);
            }
        }
    }
}

    /* ===== helpers ===== */

    // Accepts string (CSV) or array; returns array of scalar ids/labels in order
    private function normalizeToArray($val)
    {
        if (is_array($val)) {
            return array_values(array_filter(array_map(function($x){
                if (is_array($x)) return $x['id'] ?? $x['value'] ?? $x['label'] ?? null;
                if (is_scalar($x)) return trim((string)$x);
                return null;
            }, $val), fn($x) => $x !== null && $x !== ''));
        }
        $s = trim((string)$val);
        if ($s === '') return [];
        return array_values(array_filter(preg_split('/\s*,\s*/', $s), fn($x) => $x !== ''));
    }

    // Map label/id → option_id in ab_dynamic_feilds_option (upsert on label)
    private function ensureOptionId($fieldId, $valueOrId)
    {
        $CI = $this->CI;
        if ($valueOrId === null || $valueOrId === '') return null;

        // numeric? assume it's an existing option id
        if (ctype_digit((string)$valueOrId)) {
            return (int)$valueOrId;
        }

        // Label → normalize to value_key, upsert
        $label = trim((string)$valueOrId);
        if ($label === '') return null;

        $key = strtolower(preg_replace('/\s+/', '_', $label));

        $exists = $CI->CommonModel->GetMasterListDetails(
            $selectC = 'id',
            'ab_dynamic_feilds_option',  // keep your current table name/spelling
            ['field_id' => '='.(int)$fieldId, 'value_key' => "='".$CI->db->escape_str($key)."'"],
            1, 0, [], []
        );
        if (!empty($exists)) return (int)$exists[0]['id'];

        $CI->db->insert('ab_dynamic_feilds_option', [
            'field_id'   => (int)$fieldId,
            'value_key'  => $key,
            'label'      => $label,
            'sort_order' => 1000,
            'is_active'  => 1
        ]);
        return (int)$CI->db->insert_id();
    }

    public function loadDynamicDataOptions($moduleId, $recordId)
{
    $CI = $this->CI;

    // 1) Get rows for this record
    $rows = $CI->CommonModel->GetMasterListDetails(
        $selectC = 'field_id, option_id, linked_id, position, is_multi',
        'dynamic_values_option',
        ['module_id' => '='.(int)$moduleId, 'record_id' => '='.(int)$recordId],
        '', '', [], ['orderBy' => 'field_id, position', 'order' => 'ASC']
    );
    if (empty($rows)) return [];

    // 2) group ids per field
    $byField = [];
    $optionIds = [];
    $linkedIds = [];

    foreach ($rows as $r) {
        $fid = (int)$r['field_id'];
        $byField[$fid][] = $r;
        if (!empty($r['option_id'])) $optionIds[] = (int)$r['option_id'];
        if (!empty($r['linked_id'])) $linkedIds[] = (int)$r['linked_id'];
    }
    $optionIds = array_values(array_unique($optionIds));
    $linkedIds = array_values(array_unique($linkedIds));

    // 3) resolve option labels
    $optLabels = [];
    if ($optionIds) {
        $opts = $CI->CommonModel->GetMasterListDetails(
            $selectC = 'id, label',
            'ab_dynamic_feilds_option',
            ['id' => 'IN ('.implode(',', $optionIds).')'],
            '', '', [], []
        );
        foreach ($opts as $o) $optLabels[(int)$o['id']] = $o['label'];
    }

    // 4) (optional) resolve linked labels if you store metadata per field (linked_table, key, label)
    // For now, return just ids; you can join to the domain table in your UI when needed.

    // 5) build output
    $out = [];
    foreach ($byField as $fid => $items) {
        $isMulti = (int)$items[0]['is_multi'] === 1;
        if ($isMulti) {
            $vals = [];
            foreach ($items as $it) {
                if (!empty($it['option_id'])) {
                    $id = (int)$it['option_id'];
                    $vals[] = ['id' => $id, 'label' => (string)($optLabels[$id] ?? $id)];
                } elseif (!empty($it['linked_id'])) {
                    $id = (int)$it['linked_id'];
                    $vals[] = ['id' => $id, 'label' => (string)$id]; // resolve label if needed
                }
            }
            $out[] = ['fieldID' => (int)$fid, 'multi' => $vals];
        } else {
            $it = $items[0];
            if (!empty($it['option_id'])) {
                $id = (int)$it['option_id'];
                $out[] = ['fieldID' => (int)$fid, 'single' => ['id' => $id, 'label' => (string)($optLabels[$id] ?? $id)]];
            } elseif (!empty($it['linked_id'])) {
                $id = (int)$it['linked_id'];
                $out[] = ['fieldID' => (int)$fid, 'single' => ['id' => $id, 'label' => (string)$id]];
            } else {
                $out[] = ['fieldID' => (int)$fid, 'single' => null];
            }
        }
    }
    return $out;
}


     private function prepareDefaultData($defaultColumns){
        $defaultColumnsData = array();
        $field =  ["fieldID" => "","fieldLabel" => "","fieldType" => "","column_name" => "","linkedWith" => "","fieldOptions" => "","dateFormat" => "","displayFormat" => "","parentCategory" => ""];
        foreach ($defaultColumns as $key => $value) {
             $field['column_name'] = $value;
            $defaultColumnsData[]= $field;
        }
        $this->defaultSelect = implode(",",$defaultColumns);
        return $defaultColumnsData;
    }
}