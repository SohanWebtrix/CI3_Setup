<?php

use Mpdf\Tag\Pre;
use PSpell\Config;

defined('BASEPATH') or exit('No direct script access allowed');

class CommonModel extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	public function getLastInsertedID()
	{
		return $this->db->insert_id();
	}

	public function getCountByParameter($select = '', $table = '', $where = array(), $other = array(), $join = array()){
		$whereStr = "";
		$limitstr = "";
		$freeTextSearch = '';
		if (isset($other['freeTextSearch']) && !empty($other['freeTextSearch'])) {
			$freeTextSearch = $other['freeTextSearch'];
		}
		foreach ($where as $key => $value) {
			if ($whereStr == "")
				$whereStr .= $key . " " . $value . " ";
			else
				$whereStr .= " AND " . $key . " " . $value . " ";
		}
		if (isset($other['whereOR']) && !empty($other['whereOR'])) {
			foreach ($other['whereOR'] as $key => $value) {
				if ($whereStr == "")
					$whereStr .= $key . " " . $value . " ";
				else
					$whereStr .= " OR " . $key . " " . $value . " ";
			}
		}
		if (isset($other['find_in_set']) && !empty($other['find_in_set'])) {
			for ($i=0; $i < count($other['find_in_set']); $i++) { 
				$whereStrFindSet="";
				foreach ($other['find_in_set'][$i] as $key => $value) {
					if ($whereStrFindSet == "")
						$whereStrFindSet .= "FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0";
					else
						$whereStrFindSet .= " ".$other['find_in_set_type'][$i]." FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0 ";
				}
				if ($whereStr != ""){
					$whereStr .= "AND ( ";
				}else{
					$whereStr .= " ( ";
				}
				$whereStr .= $whereStrFindSet.")";
			}
		}
		if (trim($whereStr) != '') {
			$whereStr = " WHERE " . $whereStr;
		} else {
			$whereStr = "";
		}
		if (isset($other['whereIn']) && !empty($other['whereIn'])) {
			if (trim($whereStr) == "")
				$whereStr .= " WHERE " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
			else
				$whereStr .= " AND " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
		}
		$joinsql = '';
		if (isset($join) && !empty($join)) {
			foreach ($join as $key => $value) {

				if (isset($value['key1Alias']) && !empty($value['key1Alias'])) {

					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON " . $value['key1Alias'] . "." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				} else {
					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON t." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				}
			}
		} else {
			$joinsql = "";
		}
		
		if ($freeTextSearch != '') {
			$tableName = $this->db->dbprefix . $table;
			$fields = $this->db->field_data($tableName);
			$this->db->from($tableName);
			$searchStr = '';
			// FOR SELF TABLE FIELDS
			foreach ($fields as $field) {
				$searchTerm = '%' . $this->db->escape_like_str($freeTextSearch) . '%' ;
				if ($searchStr == "") {
					if ($whereStr == ''){
						$searchStr .= ' WHERE t.'.$this->db->protect_identifiers($field->name) . " LIKE " . $this->db->escape($searchTerm);
					}else{
						$searchStr .= ' t.'.$this->db->protect_identifiers($field->name) . " LIKE " . $this->db->escape($searchTerm);
					}
				} else {
					$searchStr .= " OR t." . $this->db->protect_identifiers($field->name) . " LIKE " . $this->db->escape($searchTerm);
				}
			}
			// FOR JOINED TABLE FIELDS
			if (isset($join) && !empty($join)) {
				foreach ($join as $joinTable) {
					$cols = isset($joinTable['is_dynamic']) && $joinTable['is_dynamic'] ? $joinTable['column'] : [];
					foreach ($cols as $key => $d_col) {
						if ($searchStr != "") {
							$searchStr .= " OR ";
						}
						$searchStr .= $joinTable['alias']. '.' . $d_col . " LIKE " . $this->db->escape($searchTerm);
					}
				}
			}
			($whereStr == '') ? $whereStr .= $searchStr : $whereStr .= ' AND ('.$searchStr.') ';
			$sql = "SELECT " . $select . " FROM " . $this->db->dbprefix . "{$table} as t " . $joinsql . $whereStr . "";
			$query = $this->db->query($sql);
		}else{
			$sql = "SELECT " . $select . " FROM " . $this->db->dbprefix . $table . " as t " . $joinsql . $whereStr . "";
			$query = $this->db->query($sql);
		}
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		$rowcount = $query->num_rows();
		return $rowcount;
	}
	public function GetMasterListDetails($select = '', $table = '', $where = array(), $limit = '', $start = '', $join = array(), $other = array())	{
		if ($select == '') {
			$select = "*";
		}
		$whereStr = "";
		$limitstr = "";
		$groupBy = ''; 
		$freeTextSearch = '';
		if (isset($other['freeTextSearch']) && !empty($other['freeTextSearch'])) {
			$freeTextSearch = $other['freeTextSearch'];
		}
		
		foreach ($where as $key => $value) {
			if ($whereStr == "")
				$whereStr .= $key . " " . $value . " ";
			else
				$whereStr .= " AND " . $key . " " . $value . " ";
		}
		if (isset($other['whereOR']) && !empty($other['whereOR'])) {

			foreach ($other['whereOR'] as $key => $value) {
				if ($whereStr == "")
				{
					$whereStr .= $key . " " . $value . " ";
				}
				else
				{		
					if (!strpos($whereStr,'AND')) {
						$whereStr .= " AND (" . $key . " " . $value . " ";
					}else
					{
						$whereStr .= " OR " . $key . " " . $value . " ";
					}
					
				}		
			}
			if (strpos($whereStr,'AND')) {
						$whereStr .=")";
					}
		}
		// print_r($whereStr);exit;
		if (isset($other['find_in_set']) && !empty($other['find_in_set'])) {
			for ($i=0; $i < count($other['find_in_set']); $i++) { 
				$whereStrFindSet="";
				foreach ($other['find_in_set'][$i] as $key => $value) {
					if ($whereStrFindSet == "")
						$whereStrFindSet .= "FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0";
					else
						$whereStrFindSet .= " ".$other['find_in_set_type'][$i]." FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0 ";
				}
				if ($whereStr != ""){
					$whereStr .= "AND ( ";
				}else{
					$whereStr .= " ( ";
				}
				$whereStr .= $whereStrFindSet.")";
			}
		}
		// change for all record. For linking to other form need all records. so skip pagination.
		if ($start != '' && $limit != '') {
			$limitstr = "LIMIT " . $start . "," . $limit;
		} else {
			if (isset($limit) && !empty($limit)) {
				$limitstr = "LIMIT 0," . $limit;
			} else {
				$limitstr = "";
			}
		}

		if (trim($whereStr) != '') {
			$whereStr = " WHERE " . $whereStr;
		} else {
			$whereStr = "";
		}

		if (isset($other['whereIn']) && !empty($other['whereIn'])) {

			if (trim($whereStr) == "")
				$whereStr .= " WHERE " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
			else
				$whereStr .= " AND " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
		}
		if (isset($other['orderBy']) && !empty($other['orderBy'])) {
			$orderBy = "ORDER BY " . $other['orderBy'] . " " . $other['order'];
		} else {
			$orderBy = "";
		}
		if (isset($other['groupBy']) && !empty($other['groupBy'])) {
			$groupBy = "GROUP BY " . $other['groupBy'];
		} else {
			$groupBy = "";
		}
		$joinsql = '';
		// if (isset($join) && !empty($join)) {
		// 	foreach ($join as $key => $value) {
		// 		if (isset($value['key1Alias']) && !empty($value['key1Alias'])) {

		// 			$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON " . $value['key1Alias'] . "." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
		// 		} else {
		// 			$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON t." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
		// 		}
		// 	}
		// } else {
		// 	$joinsql = "";
		// }

		if (isset($join) && !empty($join)) {
			foreach ($join as $key => $value) {
				if (isset($value['key1Alias']) && !empty($value['key1Alias'])) {
					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON " . $value['key1Alias'] . "." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				} else {
					$joinsql .= (isset($value['super_alias']) && !empty($value['super_alias'])) ?
						" " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON " . $value['super_alias'] . "." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'] :
						" " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON t." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'] ;
				}
			}
		} else {
			$joinsql = "";
		}

		if ($freeTextSearch != '') {
			$tableName = $this->db->dbprefix . $table;
			$fields = $this->db->field_data($tableName);
			$this->db->from($tableName);
			$searchStr = '';
			// FOR SELF TABLE FIELDS
			foreach ($fields as $field) {	
				$searchTerm = '%' . $this->db->escape_like_str($freeTextSearch) . '%' ;
				if ($searchStr == "") {
					if ($whereStr == '') {
						$searchStr .= ' WHERE t.'.$this->db->protect_identifiers($field->name) . " LIKE " . $this->db->escape($searchTerm);
					}else{
						$searchStr .= ' t.'.$this->db->protect_identifiers($field->name) . " LIKE " . $this->db->escape($searchTerm);
					}
				} else {
					$searchStr .= " OR t." . $this->db->protect_identifiers($field->name) . " LIKE " . $this->db->escape($searchTerm);
				}
			}
			// FOR JOINED TABLE FIELDS
			if (isset($join) && !empty($join)) {
				foreach ($join as $joinTable) {
					$cols = isset($joinTable['is_dynamic']) && $joinTable['is_dynamic'] ? $joinTable['column'] : [];
					foreach ($cols as $key => $d_col) {
						if ($searchStr != "") {
							$searchStr .= " OR ";
						}
						$searchStr .= $joinTable['alias']. '.' . $d_col . " LIKE " . $this->db->escape($searchTerm);
					}
				}
			}
			($whereStr == '') ? $whereStr .= $searchStr : $whereStr .= ' AND ('.$searchStr.') ';
			$sql = "SELECT " . $select . " FROM " . $this->db->dbprefix . "{$table} as t " . $joinsql . $whereStr . " " . $orderBy . " " . $limitstr;
			$query = $this->db->query($sql);
		}else{
			$sql = "SELECT " . $select . " FROM " . $this->db->dbprefix . "{$table} as t " . $joinsql . $whereStr . " " . $groupBy . " " . $orderBy ." ". $limitstr;
			$query = $this->db->query($sql);
		}
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);

		if (isset($other["resultType"]) && !empty($other["resultType"])) {
			$result = $query->result_array();
		} else {
			$result = $query->result();
		}
		return $result;
	}

	public function getFilteredCount($select = '', $table = '', $where = array(), $other = array(), $join = array()){
		$whereStr = "";
		$limitstr = "";
		$ORstr = "";
		foreach ($where as $key => $value) {
			$st = (strpos($value,'OR')) ? "( ".$key . " " . $value . " ) " : $key . " " . $value . " ";
			if ($whereStr == ""){
				$whereStr .= $st;
			}else{
				$whereStr .= "AND " . $st;
			}
		}
		if (isset($other['OR']) && !empty($other['OR'])) {
			foreach ($other['OR'] as $key => $value) {
				$st = (strpos($value,'OR')) ? "( ".$key . " " . $value . " ) " : $key . " " . $value . " ";
				if (count($other['OR']) == 1) {
					$ORstr .= "OR " . $st;
				}else{
					if ($ORstr == "") {
						$ORstr .= "(". $st . "";
					}else{
						$ORstr .= " OR " . $st;
					}
				}
			}
		}
		if ($ORstr != "") {
			if (count($other['OR']) > 1) {
				$ORstr .= ')';
				$whereStr .= 'AND '.$ORstr;
			}else {
				$whereStr .= $ORstr;
			}
		}
		if (isset($other['find_in_set']) && !empty($other['find_in_set'])) {
			for ($i=0; $i < count($other['find_in_set']); $i++) { 
				$whereStrFindSet="";
				foreach ($other['find_in_set'][$i] as $key => $value) {
					if ($whereStrFindSet == "")
						$whereStrFindSet .= "FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0";
					else
						$whereStrFindSet .= " ".$other['find_in_set_type'][$i]." FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0 ";
				}
				if ($whereStr != ""){
					$whereStr .= "AND ( ";
				}else{
					$whereStr .= " ( ";
				}
				$whereStr .= $whereStrFindSet.")";
			}
			
		}
		
		if (trim($whereStr) != '') {
			$whereStr = " WHERE " . $whereStr;
		} else {
			$whereStr = "";
		}

		if (isset($other['whereIn']) && !empty($other['whereIn'])) {

			if (trim($whereStr) == "")
				$whereStr .= " WHERE " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
			else
				$whereStr .= " AND " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
		}

		$joinsql = '';
		if (isset($join) && !empty($join)) {
			foreach ($join as $key => $value) {

				if (isset($value['key1Alias']) && !empty($value['key1Alias'])) {

					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON " . $value['key1Alias'] . "." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				} else {
					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON t." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				}
			}
		} else {
			$joinsql = "";
		}
		$sql = "SELECT " . $select . " FROM " . $this->db->dbprefix . $table . " as t " . $joinsql . $whereStr . "";
		$query = $this->db->query($sql);
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		$rowcount = $query->num_rows();
		return $rowcount;
	}
	public function GetFilteredMasterList($select = '', $table = '', $where = array(), $limit = '', $start = '', $join = array(), $other = array()){	
		if ($select == '') {
			$select = "*";
		}
		$whereStr = "";
		$limitstr = "";
		$ORstr = "";
		foreach ($where as $key => $value) {
			$st = (strpos($value,'OR')) ? "( ".$key . " " . $value . " ) " : $key . " " . $value . " ";
			if ($whereStr == ""){
				$whereStr .= $st;
			}else{
				$whereStr .= "AND " . $st;
			}
		}
		if (isset($other['OR']) && !empty($other['OR'])) {
			foreach ($other['OR'] as $key => $value) {
				$st = (strpos($value,'OR')) ? "( ".$key . " " . $value . " ) " : $key . " " . $value . " ";
				if (count($other['OR']) == 1) {
					$ORstr .= "OR " . $st;
				}else{
					if ($ORstr == "") {
						$ORstr .= "(". $st . "";
					}else{
						$ORstr .= " OR " . $st;
					}
				}
			}
		}
		if ($ORstr != "") {
			if (count($other['OR']) > 1) {
				$ORstr .= ')';
				$whereStr .= 'AND '.$ORstr;
			}else {
				$whereStr .= $ORstr;
			}
		}
		if (isset($other['whereOR']) && !empty($other['whereOR'])) {
			foreach ($other['whereOR'] as $key => $value) {
				if ($whereStr == ""){
					$whereStr .= $key . " " . $value . " ";
				}else{		
					if (!strpos($whereStr,'AND')) {
						$whereStr .= " AND " . $key . " " . $value . " ";
					}else{
						$whereStr .= " OR " . $key . " " . $value . " ";
					}
				}		
			}
		}		
		if (isset($other['find_in_set']) && !empty($other['find_in_set'])) {
			for ($i=0; $i < count($other['find_in_set']); $i++) { 
				$whereStrFindSet="";
				foreach ($other['find_in_set'][$i] as $key => $value) {
					if ($whereStrFindSet == "")
						$whereStrFindSet .= "FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0";
					else
						$whereStrFindSet .= " ".$other['find_in_set_type'][$i]." FIND_IN_SET('".$value."',REPLACE(".$other['find_in_set_key'][$i].",' ','')) > 0 ";
				}
				if ($whereStr != ""){
					$whereStr .= "AND ( ";
				}else{
					$whereStr .= " ( ";
				}
				$whereStr .= $whereStrFindSet.")";
			}
		}
		// change for all record. For linking to other form need all records. so skip pagination.
		if ($start != '' && $limit != '') {
			$limitstr = "LIMIT " . $start . "," . $limit;
		} else {
			if (isset($limit) && !empty($limit)) {
				$limitstr = "LIMIT 0," . $limit;
			} else {
				$limitstr = "";
			}
		}
		if (trim($whereStr) != '') {
			$whereStr = " WHERE " . $whereStr;
		} else {
			$whereStr = "";
		}
		if (isset($other['whereIn']) && !empty($other['whereIn'])) {
			if (trim($whereStr) == "")
				$whereStr .= " WHERE " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
			else
				$whereStr .= " AND " . $other['whereIn'] . " IN (" . $other['whereData'] . ") ";
		}
		if (isset($other['orderBy']) && !empty($other['orderBy'])) {
			$orderBy = "ORDER BY " . $other['orderBy'] . " " . $other['order'];
		} else {
			$orderBy = "";
		}
		$joinsql = '';
		if (isset($join) && !empty($join)) {
			foreach ($join as $key => $value) {
				if (isset($value['key1Alias']) && !empty($value['key1Alias'])) {
					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON " . $value['key1Alias'] . "." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				} else {
					$joinsql .= " " . $value['type'] . " " . $this->db->dbprefix . $value['table'] . " as " . $value['alias'] . " ON t." . $value['key1'] . " = " . $value['alias'] . "." . $value['key2'];
				}
			}
		} else {
			$joinsql = "";
		}
		// print_r($whereStr);exit;
		$sql = "SELECT " . $select . " FROM " . $this->db->dbprefix . "{$table} as t " . $joinsql . $whereStr . " " . $orderBy . " " . $limitstr;
		// print_r($sql);exit;
		$query = $this->db->query($sql);
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		if (isset($other["resultType"]) && !empty($other["resultType"])) {
			$result = $query->result_array();
		} else {
			$result = $query->result();
		}
		return $result;
	}
	public function saveContactDetails($data = '')
	{

		$res = $this->db->insert("contactus", $data);
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}
	public function isSubscribed($email = '')
	{
		$this->db->select("*");
		$this->db->from("subscribe");
		$this->db->where('email', $email);
		$query = $this->db->get();
		$sqlerror = $this->db->error();
		$result = $query->result();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $result;
	}

	public function countFiltered($table = '')
	{
		$this->db->select("*");
		$this->db->from($table);
		$query = $this->db->get();
		$sqlerror = $this->db->error();
		$result = $query->num_rows();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $result;
	}

	public function getUniqueCode($length = 6)
	{
		$token = "";
		$codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
		$codeAlphabet .= "0123456789";
		$max = strlen($codeAlphabet); // edited

		for ($i = 0; $i < $length; $i++) {
			$randomNumber = rand(0, $max - 1);
			$token .= substr($codeAlphabet, $randomNumber, 1);
		}
		return $token;
	}
	public function getMasterDetails($master = '', $select = "*", $where = array())
	{

		if (!isset($select) || empty($select)) {
			$select = "*";
		}
		if (!isset($master) || empty($master)) {
			return false;
		}

		$this->db->select($select);
		$this->db->from($master);
		if (isset($where) && !empty($where)) {
			$this->db->where($where);
		}

		$query = $this->db->get();

		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		$result = $query->result();
		// print $this->db->last_query();
		return $result;
	}
	public function getMobileDetails($where = '')
	{
		$this->db->select('*');
		$this->db->from('traineeMaster');
		if (isset($where) && !empty($where)) {
			$this->db->where('mobile', $where);
		}
		$query = $this->db->get();

		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		$result = $query->result();
		return $result;
	}
	public function getAadhaarDetails($where = '')
	{
		$this->db->select('*');
		$this->db->from('traineeMaster');
		if (isset($where) && !empty($where)) {
			$this->db->where('aadhaarNo', $where);
		}
		$query = $this->db->get();

		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		$result = $query->result();
		return $result;
	}

	public function saveMasterDetails($tableName = '', $data = '')
	{

		if (!isset($tableName) || empty($tableName)) {
			return false;
		}

		if (!isset($data) || empty($data)) {
			return false;
		}
		$res = $this->db->insert($tableName, $data);
		$sqlerror = $this->db->error();
		
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}

	public function updateMasterDetails($tableName = '', $data = '', $where = '')
	{

		if (!isset($tableName) || empty($tableName)) {
			return false;
		}
		if (!isset($data) || empty($data)) {
			return false;
		}
		if (!isset($where) || empty($where)) {
			return false;
		}
		$this->db->where($where);
		$res = $this->db->update($tableName, $data);
		$sqlerror = $this->db->error();
		// print $this->db->last_query();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}

	public function deleteMasterDetails($tableName = '', $where = '', $whereIn = array())
	{
		
		if (!isset($tableName) || empty($tableName)) {
			return false;
		}
		
		if (!isset($where) && empty($where) || !isset($whereIn) && empty($whereIn)) {
			return false;
		}
		if (isset($where) && !empty($where)) {
			
			$this->db->where($where);
		}
		
		if (isset($whereIn) && !empty($whereIn)) {
			foreach ($whereIn as $key => $value) {
				$idlist = explode(",", $value);
				$this->db->where_in($key, $idlist);
			}
		}
		$res = $this->db->delete($tableName);
		$sqlerror = $this->db->error();
		// print $this->db->last_query();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}

	

	public function multipleDeleteMasterDetails($tableName = '', $where = '', $whereIn = array())
	{
		
		if (!isset($tableName) || empty($tableName)) {
			return false;
		}
		
		if (!isset($where) && empty($where) || !isset($whereIn) && empty($whereIn)) {
			return false;
		}
		if (isset($where) && !empty($where)) {
			
			$this->db->where($where);
		}
		
		if (isset($whereIn) && !empty($whereIn)) {
			foreach ($whereIn as $key => $value) {
				$idlist = explode(",", $value);
				$this->db->where_in($key, $idlist);
			}
		}
		
		$res = $this->db->delete($tableName);
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}

	public function dynamicFormDeleteMasterDetails($tableName = '', $where = '', $whereIn = array(), $primaryID = '')
	{
		
		if (!isset($tableName) || empty($tableName)) {
			return false;
		}
	
		if ((!isset($where) || empty($where))&&(!isset($whereIn) || empty($whereIn))) {
			return false;
		}
		

		$idlist = explode(",", $whereIn);
		// print_r($idlist);exit;
		// if (isset($where) && !empty($where)) {
		// 	$this->db->where($where);
		// }
		if (isset($whereIn) && !empty($whereIn)) {
				$this->db->where_in($primaryID, $idlist);
		}
		
		$res = $this->db->delete($tableName);
		// print $this->db->last_query();exit;
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}

	public function changeMasterStatus($tableName = '', $statusCode = '', $ids = '', $primaryID = '')
	{

		if (!isset($tableName) || empty($tableName)) {
			return false;
		}
		if (!isset($ids) || empty($ids)) {
			return false;
		}

		if (!isset($primaryID) || empty($primaryID)) {
			return false;
		}

		$idlist = explode(",", $ids);
		$modifyBy = $this->input->post("SadminID");
		$data = array("status" => $statusCode, "modified_date" => date("Y/m/d H:i:s"), "modified_by" => $modifyBy);
		$this->db->where_in($primaryID, $idlist);
		$res = $this->db->update($tableName, $data);
		// print($this->db->last_query());exit;
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}

	public function changeMasterRoleStatus($tableName = '', $ids = '',$updatedRoleId = '', $primaryID = ''){

        if (!isset($tableName) || empty($tableName)) {
            return false;
        }
        if (!isset($ids) || empty($ids)) {
            return false;
        }

        if (!isset($primaryID) || empty($primaryID)) {
            return false;
        }

        $idlist = explode(",", $ids);
        $modifyBy = $this->input->post("SadminID");
        $data = array("roleID" => $updatedRoleId, "modified_date" => date("Y/m/d H:i:s"), "modified_by" => $modifyBy);
        $this->db->where_in($primaryID, $idlist);
        $res = $this->db->update($tableName, $data);
        $sqlerror = $this->db->error();
        //$this->db->last_query();
        $this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
        return $res;
    }

	public function getMonthByID($id = '')
	{
		$months = array("1" => "january", "2" => "february", "3" => "march", "4" => "april", "5" => "may", "6" => "june", "7" => "july", "8" => "august", "9" => "september", "10" => "october", "11" => "november", "12" => "december");
		return $months[$id];
	}
	public function num2words($num = '', $currency = '')
	{

		$ZERO = "zero";
		$MINUS = "minus";
		/* zero is shown as "" since it is never used in combined forms */ 		 /* 0 .. 19 */
		$lowName = array("", "One", "Two", "Three", "Four", "Five", 		 "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", 		 "Sixteen", "Seventeen", "Eighteen", "Nineteen");
		$tys = array("", "", "Twenty", "Thirty", "Forty", "Fifty", 		 "Sixty", "Seventy", "Eighty", "Ninety");
		/* 0, 10, 20, 30 ... 90 */

		switch ($currency) {

			case 'INR': 	//$groupName = array( "", "Hundred", "Thousand", "Lakh", "Crore","Arab", "Kharab"); 
				$groupName = array("", "Hundred", "Thousand", "Lakh", "Crore", "Hundred", "Thousand", "Lakh", "");

				// How many of this group is needed to form one of the succeeding group. 					
				// Indian: unit, hundred, thousand, lakh, crore 				

				//	$divisor = array( 100, 10, 100, 100,100000,100000000000) ;

				$divisor = array(100, 10, 100, 100, 100, 10, 100, 100, 10);
				break;
			case 'USD': 	//$groupName = array( "", "Hundred", "Thousand", "Lakh", "Crore","Arab", "Kharab"); 
				$groupName = array("", "Hundred", "Thousand", "Million", "Billion", "Trillion", "");

				// How many of this group is needed to form one of the succeeding group. 					
				// Indian: unit, hundred, thousand, lakh, crore 				

				//	$divisor = array( 100, 10, 100, 100,100000,100000000000) ;

				$divisor = array(100, 10, 1000, 100000, 1000000000);
				break;

			case 'Paise':
				$groupName = array();
				$divisor = array(100);
				break;
		}
		$num = str_replace(",", "", $num);
		$num = number_format($num, 2, '.', '');
		$cents = substr($num, strlen($num) - 2, strlen($num) - 1);
		$num = (int)$num;

		$s = "";

		if ($num == 0) $s = $ZERO;
		$negative = ($num < 0);
		if ($negative) $num = -$num;

		// Work least significant digit to most, right to left.
		// until high order part is all 0s.
		for ($i = 0; $num > 0; $i++) {
			$remdr = (int)($num % $divisor[$i]);
			$num = $num / $divisor[$i];
			if ($remdr == 0)
				continue;

			$t = "";
			if ($remdr < 20)
				$t = $lowName[$remdr];
			else if ($remdr < 100) {
				$units = (int)$remdr % 10;
				$tens = (int)$remdr / 10;
				$tens = floor($tens);
				$t = $tys[$tens];

				if ($units != 0)
					$t .= " " . $lowName[$units];
				} else
					$t = $inWords[$remdr];		
			if (isset($groupName[$i])) {
				$s = $t . " " . $groupName[$i] . " "  . $s;
			}else{
				$s = $t . " "  . $s;
			}
			$num = (int)$num;
		}

		$s = trim($s);
		if ($negative)
			$s = $MINUS . " " . $s;


		if (($cents != '00') && ($s == 'zero')) {
			$s = $cents . " Paise only";
			return $s;
		}


		switch ($currency) {

			case 'INR':
				$s .= " Rupees";
				if ($cents != '00')
					$s .= " and " . $this->num2words($cents, 'Paise');

				$s .= " Only";
				break;
			case 'USD':
				$s .= " Dollar";
				if ($cents != '00')
					$s .= " and " . $this->num2words($cents, 'Cents');

				$s .= " Only";
				break;
			case 'Paise':
				$s .= " Paise";
		}
		return $s;
	}
	// public function saveFile($table = '', $fileColumn = '', $filename = '', $forignValue = '', $fileTypeColumn = '', $fileType = '', $forignKey = '', $extraData = array(), $opFile = '',$isUpdate = '',$where=array())
	// {
	// 	$adminID = $this->input->post("SadminID");
	// 	$data = array();
	// 	$data["created_by"] = $adminID;

	// 	if (!empty($fileTypeColumn))
	// 		$data["" . $fileTypeColumn] = $fileType;

	// 	if (!empty($forignKey))
	// 		$data["" . $forignKey] = $forignValue;

	// 	if (!empty($fileColumn))
	// 		$data["" . $fileColumn] = $filename;

	// 	if (isset($extraData) && !empty($extraData)) {
	// 		foreach ($extraData as $key => $value) {
	// 			$data[$key] = $value;
	// 		}
	// 	}
	// 	//below call by Sanjay
	// 	if (!extension_loaded('imagick') && in_array($fileType,array("jpeg","jpg","png","gif"))) {
	// 		$this->createThumbnail($opFile);
	// 	}
	// 	if ($isUpdate == 'Y') {
	// 		if (isset($where) && !empty($where)) {
	// 			$this->db->where($where);
	// 			$res = $this->db->update($table, $data);
	// 		}
			
	// 	}else{
	// 		$res = $this->db->insert($table, $data);
	// 	}
		
	// 	$sqlerror = $this->db->error();
	// 	// print $this->db->last_query();exit;
	// 	$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
	// 	return $res;
	// }
	public function saveFile(
    $table = '',
    $fileColumn = '',
    $filename = '',
    $forignValue = '',
    $fileTypeColumn = '',
    $fileType = '',
    $forignKey = '',
    $extraData = array(),
    $opFile = '',
    $isUpdate = '',
    $where = array()
) {
    $adminID = $this->input->post("SadminID");

    $data = array();

    // --- audit fields ---
    if ($adminID) {
        $data["created_by"] = $adminID;
    }

    // --- logical file type (image/video/audio/file) ---
    if (!empty($fileTypeColumn)) {
        $data[$fileTypeColumn] = $fileType;
    }

    // --- foreign mapping (legacy) ---
    if (!empty($forignKey)) {
        $data[$forignKey] = $forignValue;
    }

    // --- stored filename (media_key) ---
    if (!empty($fileColumn)) {
        $data[$fileColumn] = $filename;
    }

    // -------------------------------------------------
    // 🔥 NEW: extended metadata (safe via extraData)
    // -------------------------------------------------
    if (is_array($extraData) && !empty($extraData)) {
        foreach ($extraData as $key => $value) {
            $data[$key] = $value;
        }
    }

    // -------------------------------------------------
    // Thumbnail fallback (legacy behavior preserved)
    // -------------------------------------------------
    if (
        !extension_loaded('imagick') &&
        in_array(strtolower($fileType), ["jpeg","jpg","png","gif"])
    ) {
        $this->createThumbnail($opFile);
    }

    // -------------------------------------------------
    // INSERT / UPDATE
    // -------------------------------------------------
    if ($isUpdate === 'Y') {
        if (!empty($where)) {
            $this->db->where($where);
            $res = $this->db->update($table, $data);
        } else {
            return false;
        }
    } else {
        $res = $this->db->insert($table, $data);
    }

    // -------------------------------------------------
    // Error handling
    // -------------------------------------------------
    $sqlerror = $this->db->error();
    $this->errorlogs->checkDBError(
        $sqlerror,
        'SQL Error',
        dirname(__FILE__),
        __LINE__,
        __METHOD__
    );

    // 🔥 Return insert id for new uploads
    if ($res && $isUpdate !== 'Y') {
		return $this->db->insert_id();
    }

    return $res;
}

	function compress_image($src, $dest , $quality) 
	{
		$info = getimagesize($src);
	
		if ($info['mime'] == 'image/jpeg') 
		{
			$image = imagecreatefromjpeg($src);
		}
		elseif ($info['mime'] == 'image/gif') 
		{
			$image = imagecreatefromgif($src);
		}
		elseif ($info['mime'] == 'image/png') 
		{
			$image = imagecreatefrompng($src);
		}
		else
		{
			die('Unknown image file format');
		}
	
		//compress and save file to jpg
		imagejpeg($image, $dest, $quality);
	
		//return destination file
		return $dest;
	}
	private function parse_argv(array $argv): array
    {
        $request = [];
        foreach ($argv as $i => $a) {
            if (!$i) {
                continue;
            }
            if (preg_match('/^-*(.+?)=(.+)$/', $a, $matches)) {
                $request[$matches[1]] = $matches[2];
            } else {
                $request[$i] = $a;
            }
        }

        return array_values($request);
    }
	//Function added by Sanjay
	public function createThumbnail($imgUrl)
	{
		// $arrayimg = array('jpg', 'jpeg', 'png', 'gif');
		// $arrayVideo = array('mp4', 'mov', 'avi', '3gp');
		// $arraytxt = array('docx', 'doc', 'ppt', 'txt', 'pdf');
		$this->compress_image($imgUrl, $imgUrl, 90);
		//resize original image 
		// $img = new Image($file);
		// $size = $img->getSize();
		// Image::resize() takes care to maintain the proper aspect ratio, so this is easy
		// (default quality is 100% for JPEG so we get the cleanest resized images here)
		// $img->resize($this->options['maxImageDimension']['width'], $this->options['maxImageDimension']['height'])->save();
		// unset($img);

		//ffmpeg -i 1692702012.7795.mp4 -ss 00:00:00.000 -pix_fmt rgb24 -r 10 -s 320x240 -t 00:00:10.000 output.gif
		//perfect ffmpeg -ss 1.0 -t 2.5 -i 1692702012.7795.mp4 -filter_complex "[0:v] fps=12,scale=w=320:h=-1,split [a][b];[a] palettegen=stats_mode=single [p];[b][p] paletteuse=new=1" StickAroundPerFrame.gif
		//convert -quiet C:\xampp\htdocs\LMS\website\uploads\1692702012.7795.mp4[10] 1692702012.7795_tn.gif
	}

	public function getMonth($key = '', $type = 'string')
	{

		if ($type == "string" && is_string($key)) {
			$d = date_parse($key);
			return $d['month'];
		}
		if ($type == "number" && is_numeric($key)) {
			$dateObj   = DateTime::createFromFormat('!m', $key);
			return $dateObj->format('F');
		}
		return false;
	}

	public function unlinkFile($filePath = '')
	{
		// echo  $filePath;exit;
		if (file_exists($filePath)) {
			// echo $filePath;exit;
			return unlink($filePath);
		} else {
			return false;
		}
	}

	public function getDynamicFieldHtml($menuId = '')
	{
		$dynamicFieldHtml = "";
		$wherec["menuID="] = $menuId;
		$other = array("orderBy" => "fieldIndex");
		$dynamicFields = $this->GetMasterListDetails($selectC = '', 'dynamic_fields', $wherec, '', '', '', $other);

		if (!empty($dynamicFields)) {
			return $dynamicFields;
			/*foreach($dynamicFields as $dynamicField){
				print_r($dynamicField->fieldType);
			}*/
		}
		return '';
	}
	public function updateAllRows($tableName, $data)
	{

		if (!isset($tableName) || empty($tableName)) {
			return false;
		}
		if (!isset($data) || empty($data)) {
			return false;
		}

		$res = $this->db->update($tableName, $data);
		//print $this->db->last_query();exit;
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror,'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		return $res;
	}
	public function getdata($sql,$other){
		$query = $this->db->query($sql);
		if (isset($other["resultType"]) && !empty($other["resultType"])) {
			$result = $query->result_array();
		} else {
			$result = $query->result();
		}
		return $result;
	}

	// NOTIFICATION 
	public function getDetailForNotification($sql=''){
		$query = $this->db->query($sql);
		$result = $query->result();
		return $result;	
	}

	public function getNotificationList($menuID = '',$action='',$company_id=""){
    	// get menu table name uisng menu link
		if (isset($menuID) && !empty($menuID)) {
            $menuDetails = $this->CommonModel->getMasterDetails("menu_master","menuLink",array('menuID'=> $menuID));
            if (isset($menuDetails) && !empty($menuDetails)) {
				$where=array();
				$where['module_name'] = $menuDetails[0]->menuLink;
				$where['action_on'] =$action;
				$where['status'] ='active';
				// $where['company_id'] =$company_id;
				// GET NOTIFICATION DETAILS
				$notificationDetails = $this->CommonModel->getMasterDetails('notification_schema', '', $where);
				if (isset($notificationDetails) && !empty($notificationDetails)) {
					return $notificationDetails;
				}
            }
        }
    }
	public function getLastDocPrefix(){
		$sql ="select * from ".$this->db->dbprefix."doc_prefix where docTypeID IN ( select MAX(docTypeID) from ".$this->db->dbprefix."doc_prefix)";
		$query = $this->db->query($sql);
		$result = $query->result();
		return $result;
	}
	public function isPrefixExist($company_id){
		$where =array('company_id'=>$company_id);
		$PreDetails = $this->getMasterDetails('doc_prefix','docPrefixID',$where);
		if (count($PreDetails) <= 6) {
			return true;
		}else{
			return false;
		}
	}
	// USED FOR COPYING ROWS 
	public function insertUsingSQL($sql){
		$query = $this->db->query($sql);
		return $query;
	}
	public function checkmessageIndexUser($customer_id,){
		$sql = 'SELECT * from '.$this->db->dbprefix.'messages_index where ';
	}
	public function getCategoryBySlug($slug){
        $sql = 'SELECT category_id, slug, categoryName,cat_color FROM '.$this->db->dbprefix.'categories WHERE slug = "'.$slug.'" AND status = "active" ';

        $query = $this->db->query($sql, array($slug));
        $sqlerror = $this->db->error();
        $this->errorlogs->checkDBError($sqlerror, 'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
        $result = $query->result();
        return $result;
    }

	 //editing multiple Details
	 public function editMasterDetails($tableName, $detail, $where_in ,$primary_key) {        
		if (empty($tableName) || empty($detail) || empty($primary_key)) {
			return false;
		}

		$this->db->where_in($primary_key, $where_in);
		
		$res = $this->db->update($tableName, $detail);
		
		$sqlerror = $this->db->error();
		$this->errorlogs->checkDBError($sqlerror, 'SQL Error', dirname(__FILE__), __LINE__, __METHOD__);
		
		return $res;
	}
	function getWhatAPPUserList($where = array(),$orarray = array(), $limit = '', $start = ''){
		//$account="+14155238886";
		if ($start != '' && $limit != '') {
			$limitstr = "LIMIT " . $start . "," . $limit;
		} else {
			if (isset($limit) && !empty($limit)) {
				$limitstr = "LIMIT 0," . $limit;
			} else {
				$limitstr = "";
			}
		}
		$whereStr = $whereStr2 = $whereStr3 = "";
		if(isset($where) && !empty($where)){
			foreach ($where as $key => $value) {
				if ($whereStr == "")
					$whereStr .= $key . " " . $value . " ";
				else
					$whereStr .= " AND " . $key . " " . $value . " ";
			}	
		}
		if(isset($orarray) && !empty($orarray)){
			$whereStrOr ="";
			if(isset($whereStr) && !empty($whereStr)){
				$whereStr .="("; 
			}
			foreach ($orarray as $key => $value) {
				if ($whereStrOr == "")
					$whereStrOr .= $key . " " . $value . " ";
				else
					$whereStrOr .= " OR " . $key . " " . $value . " ";
			}
			if(isset($whereStr) && !empty($whereStr)){
				$whereStr .= " ".$whereStrOr.")"; 
			}
			else{
				$whereStr .= "(".$whereStrOr.")";
			}
		}
		if(isset($whereStr) && !empty($whereStr)){
			$whereStr2 = "WHERE ".$whereStr;
		}
		if(isset($whereStr) && !empty($whereStr)){
			$whereStr3 = "AND ".$whereStr;
			$whereStr3 = str_replace("t.wa_number","m.wa_number",$whereStr3);
		}
		///print "asd".$limit; 
		
		// $sql = "SELECT t.mobile_no as wa_number
		// ,m.unread_count,
		// css.category_id AS stageID,t.customer_image,t.type,t.salutation,t.created_date,t.last_activity_date,t.last_activity_type,t.record_type,cat.categoryName AS leadPriorityName,cat.cat_color AS priorityColor,t.email,t.mobile_no,t.name,t.status,st.state_name,t.customer_id,ai.photo AS assigneePhoto,ai.adminID AS assigneeID,ai.name AS assignee,cl.cat_color AS source_color,css.cat_color AS stage_color,
		// css.categoryName AS stages,st.state_name AS gst_state,cat.categoryName AS lead_priority,cl.categoryName AS lead_source,m.last_msg,last_msg_time AS message_timestamp
		// FROM ".$this->db->dbprefix."customer AS t LEFT JOIN ".$this->db->dbprefix."admin AS ai ON t.assignee = ai.adminID
		// LEFT JOIN  ".$this->db->dbprefix."categories AS css ON t.stages = css.category_id
		// LEFT JOIN  ".$this->db->dbprefix."states AS st ON t.gst_state = st.state_id
		// LEFT JOIN  ".$this->db->dbprefix."categories AS cat ON t.lead_priority = cat.category_id
		// LEFT JOIN  ".$this->db->dbprefix."categories AS cl ON t.lead_source = cl.category_id
		// LEFT JOIN  ".$this->db->dbprefix."messages_index AS m ON t.customer_id = m.customer_id ".$whereStr2."
		// UNION
		// SELECT m.wa_number,m.unread_count,NULL  AS stageID,NULL AS customer_image,NULL AS type,NULL AS salutation,NULL AS created_date,NULL AS last_activity_date,NULL AS last_activity_type,NULL AS record_type,NULL AS leadPriorityName,NULL AS priorityColor,NULL AS email,NULL AS mobile_no,NULL AS name,NULL AS status,NULL AS state_name,NULL AS customer_id,NULL AS assigneePhoto,NULL AS assigneeID,NULL AS assignee,NULL AS source_color,NULL AS stage_color,NULL AS stages,NULL AS gst_state,NULL AS lead_priority,NULL AS lead_source,
		// 	m.last_msg,m.last_msg_time AS message_timestamp
		// FROM ".$this->db->dbprefix."messages_index AS m LEFT JOIN  ".$this->db->dbprefix."customer AS t ON m.wa_number = t.wa_number WHERE m.customer_id IS NULL
    
		// ".$whereStr3." GROUP BY m.wa_number ORDER BY COALESCE(message_timestamp, created_date) DESC ".$limitstr;
		$sql = "
SELECT 
    t.mobile_no AS wa_number,
    m.unread_count,
    css.category_id AS stageID,
    t.customer_image,
    t.type,
    t.salutation,
    t.created_date,
    t.last_activity_date,
    t.last_activity_type,
    t.record_type,
    cat.categoryName AS leadPriorityName,
    cat.cat_color AS priorityColor,
    t.email,
    t.mobile_no,
    t.name,
    t.status,
    st.state_name,
    t.customer_id,
    ai.photo AS assigneePhoto,
    ai.adminID AS assigneeID,
    ai.name AS assignee,
    cl.cat_color AS source_color,
    css.cat_color AS stage_color,
    css.categoryName AS stages,
    st.state_name AS gst_state,
    cat.categoryName AS lead_priority,
    cl.categoryName AS lead_source,
    m.last_msg,
    m.last_msg_time AS message_timestamp
FROM {$this->db->dbprefix}customer AS t 
LEFT JOIN {$this->db->dbprefix}admin AS ai ON t.assignee = ai.adminID
LEFT JOIN {$this->db->dbprefix}categories AS css ON t.stages = css.category_id
LEFT JOIN {$this->db->dbprefix}states AS st ON t.gst_state = st.state_id
LEFT JOIN {$this->db->dbprefix}categories AS cat ON t.lead_priority = cat.category_id
LEFT JOIN {$this->db->dbprefix}categories AS cl ON t.lead_source = cl.category_id
LEFT JOIN {$this->db->dbprefix}messages_index AS m ON t.customer_id = m.customer_id 
{$whereStr2}

UNION

SELECT 
    m.wa_number,
    ANY_VALUE(m.unread_count) AS unread_count,
    NULL AS stageID,
    NULL AS customer_image,
    NULL AS type,
    NULL AS salutation,
    NULL AS created_date,
    NULL AS last_activity_date,
    NULL AS last_activity_type,
    NULL AS record_type,
    NULL AS leadPriorityName,
    NULL AS priorityColor,
    NULL AS email,
    NULL AS mobile_no,
    NULL AS name,
    NULL AS status,
    NULL AS state_name,
    NULL AS customer_id,
    NULL AS assigneePhoto,
    NULL AS assigneeID,
    NULL AS assignee,
    NULL AS source_color,
    NULL AS stage_color,
    NULL AS stages,
    NULL AS gst_state,
    NULL AS lead_priority,
    NULL AS lead_source,
    ANY_VALUE(m.last_msg) AS last_msg,
    MAX(m.last_msg_time) AS message_timestamp
FROM {$this->db->dbprefix}messages_index AS m 
LEFT JOIN {$this->db->dbprefix}customer AS t ON m.wa_number = t.wa_number 
WHERE m.customer_id IS NULL 
{$whereStr3}
GROUP BY m.wa_number

ORDER BY COALESCE(message_timestamp, created_date) DESC 
{$limitstr}
";

		//print $sql;
		$query = $this->db->query($sql);
		if (isset($other["resultType"]) && !empty($other["resultType"])) {
			$result = $query->result_array();
		} else {
			$result = $query->result();
		}
		return $result;
		/*
		CREATE INDEX idx_to_id ON messages(to_id);
		CREATE INDEX idx_mobile_no ON messages(`from`);
		CREATE INDEX idx_message_status ON messages(message_status);
		CREATE INDEX idx_timestamp ON messages(timestamp);
		ALTER TABLE `ab_messages` ADD `from_id` INT NOT NULL DEFAULT '0' AFTER `profile_name`, ADD `to_id` INT NOT NULL AFTER `from_id`; 
		ALTER TABLE `ab_info_settings` CHANGE `wa_from` `wa_from` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL; 
		ALTER TABLE `ab_messages` CHANGE `from` `from` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL; 
		ALTER TABLE `ab_customer` ADD `wa_number` VARCHAR(20) NULL DEFAULT NULL AFTER `mobile_no`; 
		ALTER TABLE `ab_info_settings` ADD `wa_message_sid` VARCHAR(100) NOT NULL AFTER `wa_ids`; 
		*/

	}
	

	public function getCustomerInvoiceData($filters = [])
	{
		$customer_id = $filters['customer_id'] ?? null;
		$action_type = $filters['action'] ?? null;
		$assetType = $filters['assetType'] ?? null;
		$dateFrom    = $filters['from_date'] ?? null;
		$dateTo      = $filters['to_date'] ?? null;
		$product_id  = $filters['product_id'] ?? null;
		$search_text  = $filters['search_text'] ?? null;

		$billing_type  = $filters['billing_type'] ?? null;
		$contract_end_from = $filters['contract_end_from'] ?? null;
		$contract_end_to = $filters['contract_end_to'] ?? null;

		if (!$customer_id) return [];

		// Subquery: get last return/replace per itemID
		$subReturn = "(SELECT r1.*
					FROM ab_invoice_line_return r1
					INNER JOIN (
							SELECT invoice_line_id, MAX(id) as last_id
							FROM ab_invoice_line_return
							GROUP BY invoice_line_id
					) r2 ON r1.invoice_line_id = r2.invoice_line_id AND r1.id = r2.last_id
						WHERE r1.action != 'upgrade'
					) as ilr";

		$this->db->select("
			il.*, 
			ih.*, 
			p.*, 
			DATE_FORMAT(ih.invoiceDate,'%d-%m-%Y') as invoiceDate,
			DATE_FORMAT(ih.invoiceDate,'%Y-%m-%d') as delivery_date,
			JSON_OBJECT(
				'invoiceID', ih.invoiceID,
				'record_type', ih.record_type,
				'invoiceNumber', ih.invoiceNumber,
				'invoiceDate', DATE_FORMAT(ih.invoiceDate,'%d-%m-%Y'),
				'delivery_date', DATE_FORMAT(ih.invoiceDate,'%Y-%m-%d'),
				'customer_id', ih.customer_id,
				'customer_name', ih.customer_name,
				'invoiceTotal', ih.invoiceTotal,
				'status', ih.status
			) AS invoiceDetails,
			JSON_OBJECT(
				'product_id', p.product_id,
				'product_name', p.product_name,
				'product_serial_no', p.product_serial_no,
				'model_name', p.model_name,
				'product_type', p.product_type,
				'price', p.price,
				'purchase_price', p.purchase_price,
				'asset_condition', p.asset_condition,
				'unit', p.unit,
				'barcode', p.barcode,
				'status', p.status
			) AS productDetails,
			,p.product_serial_no,
				JSON_OBJECT(
					'product_id', p.product_id,
					'product_name', p.product_name,
					'product_serial_no', p.product_serial_no,
					'sr_number_req', p.sr_number_req,
					'price', p.price,
					'purchase_price', p.purchase_price,
					'asset_condition', p.asset_condition,
					'unit', p.unit,
					'warranty_up_to', p.warranty_up_to,
					'processor', p.processor,
					'generation', p.generation,
					'memory', p.memory,
					'hdd_capacity', p.hdd_capacity,
					'screensize', p.screensize,
					'operating_system', p.operating_system,
					'purchase_date', p.purchase_date,
					'model_number', p.model_number,
					'model_name', p.model_name,
					'product_description', p.product_description,
					'productObject', p.productObject,
					'product_type', p.product_type,
					'barcode', p.barcode,
					'is_amc', p.is_amc,
					'free_servicing', p.free_servicing,
					'amc_duration', p.amc_duration,
					'quantity', p.quantity,
					'with_tax', p.with_tax,
					'tax', p.tax,
					'discount', p.discount,
					'discount_type', p.discount_type,
					'created_date', p.created_date,
					'created_by', p.created_by,
					'modified_by', p.modified_by,
					'modified_date', p.modified_date,
					'current_status', p.current_status,
					'status', p.status
				) AS productDetailsObject,
			ilr.*,
			ilr.Remark as note,
			CASE 
				WHEN ilr.id IS NULL THEN 'Delivered'
				ELSE ilr.action
			END AS action,
			il.row_close
		");

		$this->db->from('invoice_line il');
		$this->db->join('invoice_header ih', 'il.invoiceID = ih.invoiceID', 'left');
		$this->db->join('products p', 'il.invoiceLineChrgID = p.product_id', 'left');
		$this->db->join($subReturn, 'il.itemID = ilr.invoice_line_id', 'left'); // join last return/replace
		// $this->db->join('invoice_line rprod', 'il.replace_row_id = rprod.itemID', 'left');

		$this->db->where('ih.customer_id', $customer_id);
		$this->db->where('ih.record_type', 'delivery');
		$this->db->where('ih.status', 'approved');
	
		if ($product_id) $this->db->where('p.product_id', $product_id);
		if ($billing_type) $this->db->where('il.billing_type', $billing_type);
		if ($search_text) $this->db->like('p.product_serial_no', $search_text);
		if ($assetType == "REPL") $this->db->like('ih.is_replacement', 'y');
		if ($assetType == "NEW") $this->db->like('ih.is_replacement', 'n');
		
		if ((isset($contract_end_from) && !empty($contract_end_from)) && (isset($contract_end_to) && !empty($contract_end_to) )) {
			$this->db->where("il.contract_end BETWEEN '$contract_end_from' AND '$contract_end_to'");
		} elseif (isset($contract_end_from) && !empty($contract_end_from)) {
			$this->db->where("il.contract_end >=", $contract_end_from);
		} elseif (isset($contract_end_to) && !empty($contract_end_to)) {
			$this->db->where("il.contract_end <=", $contract_end_to);
		}

		if ($action_type) {
			switch ($action_type) {
				case 'Replaced':
					$this->db->where('ilr.action', 'replace');
					if ($dateFrom) $this->db->where('DATE(ilr.return_date) >=', $dateFrom);
					if ($dateTo) $this->db->where('DATE(ilr.return_date) <=', $dateTo);
					break;
				case 'Returned':
					$this->db->where('ilr.action', 'return');
					if ($dateFrom) $this->db->where('DATE(ilr.return_date) >=', $dateFrom);
					if ($dateTo) $this->db->where('DATE(ilr.return_date) <=', $dateTo);
					break;
				case 'Ongoing':
					// $this->db->where('ilr.action IS NULL'); // Delivered/ongoing
					$this->db->where("(ilr.action IS NULL OR ilr.action = '')");
					if ($dateFrom) $this->db->where('DATE(ih.invoiceDate) >=', $dateFrom);
					if ($dateTo) $this->db->where('DATE(ih.invoiceDate) <=', $dateTo);
					break;
			}
		}else{
			if ($dateFrom) $this->db->where('DATE(ih.invoiceDate) >=', $dateFrom);
			if ($dateTo) $this->db->where('DATE(ih.invoiceDate) <=', $dateTo);
		}

		$this->db->order_by('ih.invoiceDate', 'DESC');

		$query = $this->db->get();
		return $query->result();
	}

}
