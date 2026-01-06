<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class FilterBuilder {

  /** @var CI_Controller */
  protected $CI;

  public function __construct()
  {
    $this->CI =& get_instance();
    $this->CI->load->database();
  }
  /**
 * Fetch one base record + resolved dynamic fields (single + multi).
 * Returns:
 *  [
 *    'base' => <base row as array>,
 *    'dynamic' => [
 *       { fieldID, column_name, type, single: {id,label} | null, multi: [{id,label},...] }
 *    ],
 *    'dynamicByKey' => [ column_name => {id,label} | [{id,label}, ...] ]
 *  ]
 */
public function fetchRecordWithDynamic(int $menuId, int $recordId): array
{
    $CI = $this->CI;

    // --- menu meta (base table + pk)
    if (!isset($CI->CommonModelNew)) $CI->load->model('core/CommonModelNew');
    $meta = $CI->CommonModelNew->getMenuMeta($menuId);
    if (!$meta) return ['base'=>null, 'dynamic'=>[], 'dynamicByKey'=>[]];

    $baseTbl = $meta['base_table'];
    $baseAli = $meta['base_alias'];
    $pk      = $meta['pk'];

    // --- base row
    $q = $CI->db->select("$baseAli.*")
                ->from($baseTbl.' '.$baseAli)
                ->where("$baseAli.$pk", $recordId)
                ->limit(1)
                ->get();
    $base = $q && $q->num_rows() ? (array)$q->row_array() : null;
    //print_r($base);
    // --- dynamic defs for this module
    $defs = $CI->CommonModel->GetMasterListDetails(
        $selectC = 'fieldID, column_name, fieldType, source_type, allowMultiSelect, linkedWith',
        'dynamic_fields',
        ['menuId' => "='".(int)$menuId."'"],
        '', '', [], ['orderBy'=>'fieldID', 'order'=>'ASC','resultType'=>"array"]
    );
 
    if (!$defs) return ['base'=>$base, 'dynamic'=>[], 'dynamicByKey'=>[]];

    // --- read option rows for this record
    $rows = $CI->CommonModel->GetMasterListDetails(
        $selectC = 'field_id, option_id, linked_id, position, is_multi',
        'dynamic_values_option',
        ['module_id' => '='.(int)$menuId, 'record_id' => '='.(int)$recordId],
        '', '', [], ['orderBy' => 'field_id, position', 'order' => 'ASC','resultType'=>"array"]
    );

    $byField = [];
    $optIds = []; $lkByField = [];
    foreach ($rows as $r) {
        $fid = (int)$r['field_id'];
        $byField[$fid][] = $r;
        if (!empty($r['option_id'])) $optIds[] = (int)$r['option_id'];
        if (!empty($r['linked_id'])) $lkByField[$fid][] = (int)$r['linked_id'];
    }
    $optIds = array_values(array_unique($optIds));

    // option labels
    $optLabels = [];
    if ($optIds) {
        $opts = $CI->CommonModel->GetMasterListDetails(
            'id,label',
            'dynamic_field_options',
            ['id' => 'IN ('.implode(',', $optIds).')'],
            '', '', [], ['resultType'=>"array"]
        );
        foreach ($opts as $o) $optLabels[(int)$o['id']] = $o['label'];
    }

    // linked labels (per def, optional)
    $linkedLabelsCache = []; // [linkedWith => [id=>label]]
    $out = [];
    $byKey = [];

    foreach ($defs as $d) {
        $fid   = (int)$d['fieldID'];
        $key   = $d['column_name'] ?: ('dyn_'.$fid);
        $type  = strtolower($d['fieldType']);
        $multi = $d['allowMultiSelect'] == "yes" ? 1:0;
        $rowsF = $byField[$fid] ?? [];

        if (empty($rowsF)) {
            $out[] = ['fieldID'=>$fid,'column_name'=>$key,'type'=>$type,'single'=>null,'multi'=>[]];
            $byKey[$key] = $multi ? [] : null;
            continue;
        }

        if ($multi) {
            $vals = [];
            foreach ($rowsF as $it) {
                if (!empty($it['option_id'])) {
                    $id = (int)$it['option_id'];
                    $vals[] = ['id'=>$id, 'label'=>(string)($optLabels[$id] ?? $id)];
                } elseif (!empty($it['linked_id'])) {
                    $id = (int)$it['linked_id'];
                    $label = (string)$id;
                    // try to resolve linked label if metadata is present
                    if (!empty($d['linkedWith'])) {
                        $lkMenu = (int)$d['linkedWith'];
                        if (!isset($linkedLabelsCache[$lkMenu])) {
                            $lkMeta = $CI->CommonModelNew->getLinkedMenuMeta($lkMenu);
                            if ($lkMeta && !empty($lkByField[$fid])) {
                                $lkRows = $CI->db->select($lkMeta['pk_name'].' as id, '.$lkMeta['display_col'].' as label')
                                                 ->from($lkMeta['table_name'])
                                                 ->where_in($lkMeta['pk_name'], array_unique($lkByField[$fid]))
                                                 ->get()->result_array();
                                $map=[]; foreach ($lkRows as $lr) $map[(int)$lr['id']] = $lr['label'];
                                $linkedLabelsCache[$lkMenu] = $map;
                            } else {
                                $linkedLabelsCache[$lkMenu] = [];
                            }
                        }
                        $label = $linkedLabelsCache[$lkMenu][$id] ?? $label;
                    }
                    $vals[] = ['id'=>$id,'label'=>$label];
                }
            }
            $out[] = ['fieldID'=>$fid,'column_name'=>$key,'type'=>$type,'multi'=>$vals];
            $byKey[$key] = $vals;
        } else {
            $it = $rowsF[0];
            if (!empty($it['option_id'])) {
                $id = (int)$it['option_id'];
                $val = ['id'=>$id, 'label'=>(string)($optLabels[$id] ?? $id)];
            } elseif (!empty($it['linked_id'])) {
                $id = (int)$it['linked_id'];
                $lbl = (string)$id;
                if (!empty($d['linkedWith'])) {
                    $lkMenu = (int)$d['linkedWith'];
                    if (!isset($linkedLabelsCache[$lkMenu])) {
                        $lkMeta = $CI->CommonModelNew->getLinkedMenuMeta($lkMenu);
                        if ($lkMeta) {
                            $lr = $CI->db->select($lkMeta['pk_name'].' as id, '.$lkMeta['display_col'].' as label')
                                         ->from($lkMeta['table_name'])
                                         ->where($lkMeta['pk_name'], $id)
                                         ->limit(1)->get()->row_array();
                            $linkedLabelsCache[$lkMenu] = $lr ? [(int)$lr['id'] => $lr['label']] : [];
                        } else $linkedLabelsCache[$lkMenu] = [];
                    }
                    $lbl = $linkedLabelsCache[$lkMenu][$id] ?? $lbl;
                }
                $val = ['id'=>$id,'label'=>$lbl];
            } else {
                $val = null;
            }
            $out[] = ['fieldID'=>$fid,'column_name'=>$key,'type'=>$type,'single'=>$val];
            $byKey[$key] = $val;
        }
    }

    return ['base'=>$base, 'dynamic'=>$out, 'dynamicByKey'=>$byKey];
}
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
    $fieldData = $this->mapDynamicFeilds($module, $CI->input->post());

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

/** Accepts array | csv string | scalar → array of trimmed strings (numeric tokens only kept later) */
private function csvToArray($v): array
{
    if (is_array($v)) {
        $out = [];
        foreach ($v as $x) {
            $s = trim((string)$x);
            if ($s !== '') $out[] = $s;
        }
        return $out;
    }
    if ($v === null) return [];
    $s = trim((string)$v);
    if ($s === '') return [];
    return array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $s))));
}


/**
 * STRICT: find an existing option_id by label (no auto-create).
 * Label is normalized to value_key (lowercase, underscores).
 */
private function findOptionIdByLabelStrict(int $fieldId, string $label)
{
    $CI  = $this->CI;
    $lab = trim($label);
    if ($lab === '') return null;
    $key = strtolower(preg_replace('/\s+/', '_', $lab));

    $row = $CI->CommonModel->GetMasterListDetails(
        $selectC='id',
        'dynamic_feilds_option', // ← use your exact table name for options (rename if different)
        ['field_id' => '='.(int)$fieldId, 'value_key' => "='" . $CI->db->escape_str($key) . "'"],
        1, 0, [], []
    );
    return !empty($row) ? (int)$row[0]['id'] : null;
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

    // Map label/id → option_id in dynamic_field_options (upsert on label)
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
            'dynamic_field_options',  // keep your current table name/spelling
            ['field_id' => '='.(int)$fieldId, 'value_key' => "='".$CI->db->escape_str($key)."'"],
            1, 0, [], ['resultType'=>"array"]
        );
        if (!empty($exists)) return (int)$exists[0]['id'];

        $CI->db->insert('dynamic_field_options', [
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
            '', '', [], ['orderBy' => 'field_id, position', 'order' => 'ASC','resultType'=>"array"]
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
                'dynamic_field_options',
                ['id' => 'IN ('.implode(',', $optionIds).')'],
                '', '', [], ['resultType'=>"array"]
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
    /**
 * Returns ONE array merging:
 *  - base table columns
 *  - (optional) wide dynamic table columns (dynamic_{module})
 *  - resolved dynamic select fields (labels + ids)
 *
 * $mode: 'labels' (default), 'ids', 'objects'
 * If $mode === 'labels':
 *   - single  → key => "Label", key_id => 123
 *   - multi   → key => ["A","B"], key_ids => [1,2]
 */
// public function fetchRecordFlat(int $menuId, int $recordId, string $mode = 'labels', bool $includeWide = true): array
// {
//     $CI = $this->CI;
//     if (!isset($CI->CommonModelNew)) $CI->load->model('core/CommonModelNew');

//     // --- Menu meta
//     $meta = $CI->CommonModelNew->getMenuMeta($menuId);
//     if (!$meta) return [];

//     $baseTbl = $meta['base_table'];
//     $baseAli = $meta['base_alias'];
//     $pk      = $meta['pk'];
//     $dynTbl  = $meta['dynamic_table'] ?? null;
//     $dynMenuCol = $meta['dynamic_menu_col'] ?? null;

//     // --- Base row
//     $q = $CI->db->select("$baseAli.*")
//                 ->from($baseTbl.' '.$baseAli)
//                 ->where("$baseAli.$pk", $recordId)
//                 ->limit(1)->get();
//     $flat = $q && $q->num_rows() ? (array)$q->row_array() : [];

//     // --- Merge wide dynamic table row (if you still keep non-select values there)
//     if ($includeWide && $dynTbl) {
//         $CI->db->from($dynTbl);
//         $CI->db->where($pk, $recordId);
//         if ($dynMenuCol) $CI->db->where($dynMenuCol, (int)$menuId);
//         $rowDyn = $CI->db->limit(1)->get()->row_array();
//         if ($rowDyn) {
//             // Avoid overriding base primary key if names clash
//             unset($rowDyn[$pk]);
//             // Base takes precedence; dynamic fills gaps
//             $flat = $flat + $rowDyn;
//         }
//     }

//     // --- Load dynamic select values from ab_dynamic_values_option
//     //     (re-uses your existing helper)
//     $opts = $this->loadDynamicDataOptions($menuId, $recordId); // [{fieldID, single|multi}, ...]

//     // --- Need map: fieldID -> column_name + is_multi + linkedWith (optional)
//     $defs = $CI->CommonModel->GetMasterListDetails(
//         'fieldID, column_name, fieldType, allowMultiSelect, linkedWith',
//         'dynamic_fields',
//         ['menuId' => "='".(int)$menuId."'"],
//         '', '', [], ['resultType'=>"array"]
//     );
//     $byId = [];
//     foreach ($defs as $d) {
//         $fid = (int)$d['fieldID'];
//         $key = $d['column_name'] ?: ('dyn_'.$fid);
//         $isMulti = ($d['allowMultiSelect'] =="yes") ? 1 : 0;
//         $byId[$fid] = ['key'=>$key, 'multi'=>$isMulti];
//     }

//     // --- Merge resolved selects into flat array
//     foreach ($opts as $o) {
//         $fid = (int)$o['fieldID'];
//         if (!isset($byId[$fid])) continue;
//         $key = $byId[$fid]['key'];
//         $isM = $byId[$fid]['multi'];

//         if ($isM) {
//             $vals = $o['multi'] ?? [];
//             if ($mode === 'ids') {
//                 $flat[$key] = array_column($vals, 'id');
//             } elseif ($mode === 'objects') {
//                 $flat[$key] = $vals; // [{id,label}, ...]
//             } else { // labels (default)
//                 $flat[$key] = array_column($vals, 'label');
//                 //$flat[$key.'_ids'] = array_column($vals, 'id');
//                 $flat[$key] = array_column($vals, 'id');
//             }
//         } else {
//             $val = $o['single'] ?? null;
//             if ($mode === 'ids') {
//                 $flat[$key] = $val['id'] ?? null;
//             } elseif ($mode === 'objects') {
//                 $flat[$key] = $val; // {id,label} or null
//             } else { // labels (default)
//                 $flat[$key] = $val['label'] ?? null;
//                 //$flat[$key.'_id'] = $val['id'] ?? null;
//                 $flat[$key] = $val['id'] ?? null;
//             }
//         }
//     }

//     return $flat;
// }
public function fetchRecordFlat(
    int $menuId,
    int $recordId,
    string $mode = 'labels',
    bool $includeWide = true,
    array $joinMap = [],          // <— NEW: pass your join definitions here
    string $joinType = 'left'     // 'left' | 'inner'
): array {
    $CI = $this->CI;
    if (!isset($CI->CommonModelNew)) $CI->load->model('core/CommonModelNew');
    if (!isset($CI->CommonModel))    $CI->load->model('CommonModel');

    // --- Menu meta
    $meta = $CI->CommonModelNew->getMenuMeta($menuId);
    if (!$meta) return [];

    $baseTbl = $meta['base_table'];
    $baseAli = $meta['base_alias'];
    $pk      = $meta['pk'];
    $dynTbl  = $meta['dynamic_table'] ?? null;
    $dynMenuCol = $meta['dynamic_menu_col'] ?? null;

    // --- Build SELECT with optional joins
    $db = $CI->db;
    $db->start_cache();
    $db->from("$baseTbl $baseAli");

    $selects = ["$baseAli.*"];

    // Keep track of aliases to avoid duplicate joins
    $joinedAliases = [];

    // Helper to resolve {alias} in select strings
    $resolveAlias = static function ($s, $alias) {
        return str_replace('{alias}', $alias, $s);
    };

    foreach ($joinMap as $baseField => $cfg) {
      // Validate minimal cfg
      if (empty($cfg['table']) || empty($cfg['alias']) || empty($cfg['key2'])) continue;

      $alias  = $cfg['alias'];
      $table  = $cfg['table'];
      $col    = $cfg['column'] ?? null;        // readable column in joined table
      $fkBase = $cfg['fk'] ?? $baseField;      // base table FK column; default = map key
      $key2   = $cfg['key2'];                  // joined table PK/lookup column
      $sel    = $cfg['select'] ?? null;        // extra selects (string|array)

      // Do not join twice with the same alias
      if (isset($joinedAliases[$alias])) {
        // still add select if provided
        if ($col) $selects[] = "$alias.$col AS {$baseField}_val";
        if ($sel) {
          if (is_array($sel)) foreach ($sel as $sx) $selects[] = $resolveAlias($sx, $alias);
          else                $selects[] = $resolveAlias($sel, $alias);
        }
        continue;
      }

      // JOIN
      $db->join("$table $alias", "$alias.$key2 = $baseAli.$fkBase", strtolower($joinType)==='inner' ? 'inner' : 'left');
      $joinedAliases[$alias] = true;

      // SELECT
      if ($col) {
        $selects[] = "$alias.$col AS {$baseField}_val";
      }
      if ($sel) {
        if (is_array($sel)) {
          foreach ($sel as $sx) $selects[] = $resolveAlias($sx, $alias);
        } else {
          $selects[] = $resolveAlias($sel, $alias);
        }
      }
    }

    $db->select(implode(',', $selects));
    $db->where("$baseAli.$pk", $recordId);
    $db->stop_cache();

    // --- Fetch base row (with joins)
    $q = $db->limit(1)->get();
    $flat = ($q && $q->num_rows()) ? (array) $q->row_array() : [];
    $db->flush_cache();
    $db->last_query();
    // --- Merge wide dynamic table row
    if ($includeWide && $dynTbl) {
        $CI->db->from($dynTbl);
        $CI->db->where($pk, $recordId);
        if ($dynMenuCol) $CI->db->where($dynMenuCol, (int)$menuId);
        $rowDyn = $CI->db->limit(1)->get()->row_array();
        if ($rowDyn) {
            unset($rowDyn[$pk]); // don’t overwrite PK
            $flat = $flat + $rowDyn; // base wins; dynamic fills gaps
        }
    }

    // --- Dynamic select values (from ab_dynamic_values_option)
    $opts = $this->loadDynamicDataOptions($menuId, $recordId); // [{fieldID, single|multi}, ...]

    // Map fieldID -> column_name + is_multi
    $defs = $CI->CommonModel->GetMasterListDetails(
        'fieldID, column_name, fieldType, allowMultiSelect, linkedWith',
        'dynamic_fields',
        ['menuId' => "='".(int)$menuId."'"],
        '', '', [], ['resultType'=>"array"]
    );

    $byId = [];
    foreach ($defs as $d) {
        $fid = (int)$d['fieldID'];
        $key = $d['column_name'] ?: ('dyn_'.$fid);
        $isMulti = (strtolower($d['allowMultiSelect'] ?? '') === 'yes') ? 1 : 0;
        $byId[$fid] = ['key'=>$key, 'multi'=>$isMulti];
    }

    // Merge resolved selects into flat array (FIXED: no accidental overwrite)
    foreach ($opts as $o) {
        $fid = (int)$o['fieldID'];
        if (!isset($byId[$fid])) continue;
        $key = $byId[$fid]['key'];
        $isM = $byId[$fid]['multi'];

        if ($isM) {
            $vals = $o['multi'] ?? [];
            if ($mode === 'ids') {
                $flat[$key] = array_column($vals, 'id');
            } elseif ($mode === 'objects') {
                $flat[$key] = $vals; // [{id,label}, ...]
            } else { // 'labels'
                $flat[$key] = array_column($vals, 'label');
                // If you also want ids, uncomment next line:
                // $flat[$key.'_ids'] = array_column($vals, 'id');
            }
        } else {
            $val = $o['single'] ?? null;
            if ($mode === 'ids') {
                $flat[$key] = $val['id'] ?? null;
            } elseif ($mode === 'objects') {
                $flat[$key] = $val ?: null; // {id,label} or null
            } else { // 'labels'
                $flat[$key] = $val['label'] ?? null;
                // If you also want id, uncomment next line:
                // $flat[$key.'_id'] = $val['id'] ?? null;
            }
        }
    }

    return $flat;
}

// In application/libraries/FilterBuilder.php (inside the class)
public function getDynamicFieldBundle($menuId, $recordId = null)
{
    $CI = $this->CI;

    // 1) Load field definitions for this module
    $fields = $CI->CommonModel->GetMasterListDetails(
        $selectC = 'fieldID, column_name, fieldLabel, fieldType, source_type, allowMultiSelect, linkedWith,linkedDisplayColumn,linked_with_name, status',
        'dynamic_fields',
        ['menuId' => "='".(int)$menuId."'"],
        '', '', [], ['resultType' => 'array']
    );

    if (empty($fields)) {
        return ['menuId' => (int)$menuId, 'fields' => [], 'valuesByField' => []];
    }

    // Index by fieldID for quick joins
    $fieldIds = array_map(static fn($f) => (int)$f['fieldID'], $fields);

    // 2) Load OPTIONS for local lists (radio/dropdown/checkbox with source_type = local_list)
    $optionsByField = [];
    if (!empty($fieldIds)) {
        $in = implode(',', $fieldIds);
        $opts = $CI->CommonModel->GetMasterListDetails(
            $selectC = 'id, field_id, label, value_key, is_active',
            'dynamic_field_options',
            ['field_id' => "IN ($in)", 'is_active' => '= 1'],
            '', '', [], ['orderBy' => 'field_id,id', 'order' => 'ASC', 'resultType' => 'array']
        );
        foreach ($opts as $o) {
            $fid = (int)$o['field_id'];
            $optionsByField[$fid][] = [
                'id'    => (int)$o['id'],     // also used as value
                'value' => (int)$o['id'],
                'label' => (string)$o['label']
            ];
        }
    }

    // 3) If editing/viewing a specific record, load selected values
    $valuesByField = [];
    if ($recordId !== null) {
        $vals = $CI->CommonModel->GetMasterListDetails(
            $selectC = 'id, field_id, option_id, linked_id, position, is_multi',
            'dynamic_values_option',
            [
                'module_id' => '='.(int)$menuId,
                'record_id' => '='.(int)$recordId,
            ],
            '', '', [], ['orderBy' => 'field_id, position, id', 'order' => 'ASC', 'resultType' => 'array']
        );
        foreach ($vals as $r) {
            $fid = (int)$r['field_id'];
            $id  = !empty($r['option_id']) ? (int)$r['option_id']
                 : (!empty($r['linked_id']) ? (int)$r['linked_id'] : null);
            if ($id === null) continue;
            $valuesByField[$fid][] = $id;
        }
    }

    // 4) Enrich fields for UI (options for local_list; selected values if $recordId given)
    foreach ($fields as &$f) {
        $f['allowMultiSelect'] = (int)($f['allowMultiSelect'] ?? 0);
        $f['source_type']      = strtolower($f['source_type'] ?? 'local_list');
        $fid                   = (int)$f['fieldID'];

        // For local lists, attach options; for linked tables, only attach metadata (options loaded via separate search API)
        if ($f['source_type'] === 'local_list') {
            $f['fieldOptions'] = $optionsByField[$fid] ?? [];
        } else {
            // optional: surface minimal linked meta so UI knows how to fetch labels
            // (expects CommonModelNew::getLinkedMenuMeta($menuId) to exist)
            if (isset($CI->CommonModelNew)) {
                $f['linked_meta'] = $CI->CommonModelNew->getLinkedMenuMeta((int)($f['linkedWith'] ?? 0));
            } elseif (isset($CI->CommonModel) && method_exists($CI->CommonModel, 'getLinkedMenuMeta')) {
                $f['linked_meta'] = $CI->CommonModel->getLinkedMenuMeta((int)($f['linkedWith'] ?? 0));
            } else {
                $f['linked_meta'] = null;
            }
        }

        // Selected value(s) for this record
        if ($recordId !== null) {
            $selected = $valuesByField[$fid] ?? [];
            $f['value'] = $f['allowMultiSelect'] ? $selected : ( ($selected ? $selected[0] : null) );
        }
    }
    unset($f);

    return [
        'menuId'        => (int)$menuId,
        'fields'        => $fields,         // enriched defs for UI
        'valuesByField' => $valuesByField,  // plain map: fieldID => [ids...]
    ];
}
// /**
//  * Merge forced/override filters into an existing filter array.
//  *
//  * @param array $filters   Existing filters (from payload)
//  * @param array $overrides Key/value pairs to force, e.g. ['type'=>'lead','status'=>'active']
//  * @return array           Updated filters with overrides injected
//  */
// public function applyOverrideFilters(array $filters, array $overrides): array
// {
//     foreach ($overrides as $col => $val) {
//         // Remove any existing filter on same column to avoid conflicts
//         foreach ($filters as $k => $f) {
//             if (is_array($f) && ($f['columnName'] ?? '') === $col) {
//                 unset($filters[$k]);
//             }
//         }

//         // Add deterministic key for this forced filter
//         $filters['__forced_'.$col] = [
//             "columnName"      => $col,
//             "condition"       => "equal_to",
//             "isDynamic"       => false,
//             "logicalOp"       => "AND",
//             "mappedFieldName" => "",
//             "optionsArray"    => [],
//             "value"           => (string)$val,
//             "valueToShow"     => (string)$val,
//         ];
//     }
//     return $filters;
// }

/**
 * Normalize and inject forced filters that align with FilterEngine::buildFilters().
 *
 * $filtersRows: the same array you pass to FilterEngine->buildFilters(...)
 * $overrides:   either shorthand ['status' => 'OPEN'] or full rows per column:
 *               ['status' => ['condition'=>'in','value'=>['OPEN','HOLD'],'logicalOp'=>'AND']]
 *
 * Supported conditions match FilterEngine::buildOpCallable:
 * equal_to, not_equal_to, in, not_in, like, ilike, start_with, end_with,
 * gte, lte, between|range, is_empty, is_not_empty, date_range, and presets:
 * today|yesterday|tomorrow|this_week|this_month|exact_date
 */
public function applyOverrideFilters(array $filtersRows, array $overrides): array
{
    $DEFAULT = [
        'condition'       => 'equal_to',
        'isDynamic'       => false,
        'logicalOp'       => 'AND',
        'mappedFieldName' => '',
        'optionsArray'    => [],
        // optional: fieldObj, value, valueToShow, columnName
    ];

    $aliases = [
        'eq'=>'equal_to','neq'=>'not_equal_to',
        '>'=>'gte','>='=>'gte','<'=>'lte','<='=>'lte', // (gte/lte are what your engine handles)
        'null'=>'is_null','notnull'=>'not_null',
        'isempty'=>'is_empty','notempty'=>'is_not_empty',
        'date-between'=>'date_range','daterange'=>'date_range'
    ];

    // local helpers
    $isAssoc = static function($arr) {
        return is_array($arr) && array_keys($arr) !== range(0, count($arr)-1);
    };

    $removeExisting = function(array &$rows, array $row) {
        foreach ($rows as $k => $f) {
            if (!is_array($f)) continue;
            // same columnName
            if (($f['columnName'] ?? null) === ($row['columnName'] ?? null)) {
                unset($rows[$k]);
                continue;
            }
            // same dynamic fieldObj (if both dynamic)
            if (!empty($row['isDynamic']) && !empty($row['fieldObj']) &&
                !empty($f['isDynamic'])   && !empty($f['fieldObj']) &&
                (int)$row['fieldObj'] === (int)$f['fieldObj']) {
                unset($rows[$k]);
            }
        }
    };

    $normalize = function(string $col, $spec) use ($DEFAULT, $aliases, $isAssoc) {
        // Shorthand → equal_to
        if (!is_array($spec) || !$isAssoc($spec)) {
            $row = $DEFAULT;
            $row['columnName']  = $col;
            $row['condition']   = 'equal_to';
            $row['value']       = $spec;
            $row['valueToShow'] = is_array($spec) ? implode(',', $spec) : (string)$spec;
            return $row;
        }

        // Full form
        $row = array_merge($DEFAULT, $spec);
        $row['columnName'] = $row['columnName'] ?? $col;

        // normalize condition alias
        $cond = strtolower((string)($row['condition'] ?? 'equal_to'));
        $row['condition'] = $aliases[$cond] ?? $cond;

        // normalize logicalOp
        $lop = strtoupper($row['logicalOp'] ?? 'AND');
        $row['logicalOp'] = $lop === 'OR' ? 'OR' : 'AND';

        // dynamic convenience: if isDynamic + fieldObj but no columnName
        if (!empty($row['isDynamic']) && !empty($row['fieldObj']) && empty($row['columnName'])) {
            $row['columnName'] = 'dyn_'.(int)$row['fieldObj'];
        }

        // make value/valueToShow consistent with engine expectations
        $noValueConds = ['is_null','not_null','is_empty','is_not_empty','today','yesterday','tomorrow','this_week','this_month'];
        if (in_array($row['condition'], $noValueConds, true)) {
            $row['value'] = $row['valueToShow'] = '';
        } elseif (in_array($row['condition'], ['in','not_in'], true)) {
            $vals = $row['value'] ?? [];
            if (!is_array($vals)) {
                $vals = preg_split('/\s*,\s*/', (string)$vals, -1, PREG_SPLIT_NO_EMPTY);
            }
            $row['value']       = $vals;
            $row['valueToShow'] = implode(',', array_map('strval', $vals));
        } elseif (in_array($row['condition'], ['between','range','date_range'], true)) {
            $v = $row['value'] ?? [];
            if (is_array($v) && isset($v['from'], $v['to'])) {
                $row['valueToShow'] = $v['from'].' ~ '.$v['to'];
            } elseif (is_array($v) && count($v) >= 2) {
                $row['valueToShow'] = (string)$v[0].' ~ '.(string)$v[1];
            } else {
                $row['valueToShow'] = (string)($v ?? '');
            }
        } else {
            $v = $row['value'] ?? '';
            if (is_array($v)) {
                $row['valueToShow'] = implode(',', array_map('strval', $v));
            } else {
                $row['value']       = $v;
                $row['valueToShow'] = (string)($row['valueToShow'] ?? $v);
            }
        }
        return $row;
    };

    foreach ($overrides as $col => $spec) {
        // Allow multiple rules for same column: [['condition'=>...], ...]
        if (is_array($spec) && !$isAssoc($spec) && count($spec) && $isAssoc($spec[0] ?? [])) {
            foreach ($spec as $one) {
                $row = $normalize($col, $one);
                $removeExisting($filtersRows, $row);
                $filtersRows['__forced_'.$row['columnName'].'_'.md5(json_encode([$row['condition'],$row['value']]))] = $row;
            }
            continue;
        }
        $row = $normalize($col, $spec);
        $removeExisting($filtersRows, $row);
        $filtersRows['__forced_'.$row['columnName']] = $row;
    }

    return $filtersRows;
}
public function mapDynamicFeilds($module_name, $data)
    { 
        $CI = $this->CI;
        $customData = array();
        // get module details
        // $where = array("menuID" => $module_name);
        // $menuDetails = $CI->CommonModel->getMasterDetails("menu_master", "", $where);
        // if (!isset($menuDetails) || empty($menuDetails)) {
        //     return array();
        // }
        // if (!isset($data) || empty($data)) {
        //     return array();
        // }
        // get field from database
        $wherec = array();
        //$wherec["menuID = "] = $menuDetails[0]->menuID;
        $wherec["menuID = "] = $module_name;
        $fieldDetails = $CI->CommonModel->GetMasterListDetails($selectC = '', 'dynamic_fields', $wherec, '', '', array(), array());
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

}
