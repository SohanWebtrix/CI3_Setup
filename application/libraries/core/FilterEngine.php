<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class FilterEngine
{
  protected $CI;
  protected $Model;
  public function __construct()
  {
      $this->CI =& get_instance();
      $this->CI->load->database();
      // Always load the renamed model; do NOT rely on the globally autoloaded CommonModel
      if (!isset($this->CI->CommonModelNew)) {
        $this->CI->load->model('core/CommonModelNew');
      }
      $this->Model = $this->CI->CommonModelNew;
  }

  protected function isMultiSelect(array $d = null) {
    if (!$d) return false;
    $t = strtolower($d['fieldType'] ?? '');
    return in_array($t, ['checkbox list','checkbox_list','checkbox']);
  }
  /** Try to resolve a dynamic field definition for a selected column key. */
  protected function resolveDynamicDefForKey($key, array $dynMap) {
    // 1) direct key (field_key or dyn_{id} if we indexed that way)
    // print "DY key";
    //print_r($dynMap);
    if (isset($dynMap[$key])) return $dynMap[$key];
      //print "<br> KEy ".$key."  => ".$dynMap[$key];
      // 2) dyn_{id} pattern
      if (preg_match('/^dyn_(\d+)$/i', $key, $m)) {
      $want = (int)$m[1];
      foreach ($dynMap as $d) if ((int)$d['fieldID'] === $want) return $d;
      }

      // 3) normalized compare on field_key
      $normKey = $this->normKey($key);
      foreach ($dynMap as $d) {
      $fk = isset($d['column_name']) ? $d['column_name'] : (isset($d['_key']) ? $d['_key'] : '');
      if ($this->normKey($fk) === $normKey) return $d;
    }
    return null;
  }

protected function dynColumnName(array $d, array $plan)
{
  // Prefer explicit column_name if present, else field_key
  if (!empty($d['column_name'])) return $d['column_name'];
  if (!empty($d['field_key']))   return $d['field_key'];
  // last resort: dyn_{id}
  return 'dyn_'.$d['fieldID'];
}
protected function dynJoinAlias(array $plan) {
  return !empty($plan['dynamic_join_alias']) ? $plan['dynamic_join_alias'] : 'dv';
}
  protected function normKey($s) {
  $s = (string)$s;
  $s = strtolower($s);
  // collapse non-alnum to single underscore
  $s = preg_replace('/[^a-z0-9]+/i', '_', $s);
  return trim($s, '_');
}
/** Resolve a dynamic def for a selected "column" key (column_name / field_key / dyn_{id}). */

/** Boolean-mode query for MATCH ... AGAINST. */
protected function booleanQuery($needle) {
  $terms = preg_split('/\s+/', trim((string)$needle));
  $parts = [];
  foreach ($terms as $t) {
    $t = preg_replace('/[^\pL\pN]/u', '', $t);
    if ($t === '') continue;
    $parts[] = '+'.$t.'*';
  }
  return implode(' ', $parts);
}
  public function buildPlan($menuId, array $selectedColumns, array $filtersRows, $freeText='', array $sort=[], array $columnNames=[], array $customCol=[],array $staticmenuMeta=[])
{
    $menuMeta = $this->Model->getMenuMeta($menuId);
    if (!$menuMeta)
      { 
        if (empty($staticmenuMeta)){
          show_error('Unknown menu/module id: '.$menuId, 500);
        }else{
          $menuMeta = $staticmenuMeta;
        }
      }

    // dynamic enabled only if table exists
    $dynEnabled = !empty($menuMeta['dynamic_enabled']);
    $defs = $dynEnabled ? $this->Model->getDynamicDefs($menuId) : [];
    $dynMap = $this->indexDynamicDefs($defs);
    // print "sd";
    // var_dump($dynEnabled);
   
    $plan = [
      'from'             => $menuMeta['base_table'],
      'base_alias'       => $menuMeta['base_alias'],
      'select'           => [],
      'joins'            => [],
      'where'            => [],
      'where_groups'     => [],
      'order_by'         => [],
      'group_by'         => [],
      'pk'               => $menuMeta['pk'],
      'dynamic_table'    => $menuMeta['dynamic_table'],     // may be NULL
      'dynamic_menu_col' => $menuMeta['dynamic_menu_col'],  // may be NULL
      'dynamic_enabled'  => $dynEnabled,                    // TRUE/FALSE
      'menu_id'          => $menuId,
      'module_name'      => $menuMeta['module_name'],
    ];
 //    print "<pre>";
  //print_r($plan);
    $this->buildSelects($plan, $selectedColumns, $dynMap, $columnNames, $customCol);
    $this->buildFilters($plan, $filtersRows, $dynMap, $columnNames, $customCol);

    if (!empty($freeText)) {
      $this->buildFreeText($plan, $freeText, $selectedColumns,$columnNames, $dynMap);
    }

    // if (!empty($sort['by'])) {
    //   $dir = (!empty($sort['dir']) && strtoupper($sort['dir']) === 'DESC') ? 'DESC' : 'ASC';
    //   $plan['order_by'][] = ['col' => $this->safeCol($sort['by'], $plan, $dynMap), 'dir' => $dir];
    // } else {
    //   $plan['order_by'][] = ['col' => $plan['base_alias'].'.'.$plan['pk'], 'dir' => 'DESC'];
    // }
    if (!empty($sort['by'])) {
    $dir = (!empty($sort['dir']) && strtoupper($sort['dir']) === 'DESC') ? 'DESC' : 'ASC';
    $col = $this->safeCol($sort['by'], $plan, $dynMap); // must return a safe, qualified column

    // Optional: let client send $sort['nulls'] = 'first'|'last' (default: ASC => NULLs last)
    $nullsPref = isset($sort['nulls']) ? strtolower($sort['nulls']) : null;

    if ($dir === 'ASC' && ($nullsPref === 'last' || $nullsPref === null)) {
        // ensure NULLs appear after non-NULLs for ASC
        $plan['order_by'][] = ['col' => "$col IS NULL", 'dir' => 'ASC', 'escape' => false];
    } elseif ($dir === 'DESC' && $nullsPref === 'first') {
        // make NULLs appear first for DESC if requested
        $plan['order_by'][] = ['col' => "$col IS NULL", 'dir' => 'DESC', 'escape' => false];
    }

    // main sort
    $plan['order_by'][] = ['col' => $col, 'dir' => $dir];
    } else {
        $plan['order_by'][] = ['col' => $plan['base_alias'].'.'.$plan['pk'], 'dir' => 'DESC'];
    }

        return $plan;
    }

  protected function buildSelects(array &$plan, array $cols, array $dynMap, array $columnNames, array $customCol)
{
    $t  = $plan['base_alias']; $pk = $plan['pk'];
    $plan['select'][] = $t.'.'.$pk.' AS '.$pk;
    foreach ($cols as $key) {
      if ($key === $pk) continue;
      if (isset($dynMap[$key])) {
        if (!empty($plan['dynamic_enabled'])) {
          $d = $this->resolveDynamicDefForKey($key, $dynMap);
          if ($d) {
            if ($this->isSelectType($d)) {
              // NEW: use ab_dynamic_values_option → label (group_concat for multi)
              $expr = $this->dynOptionLabelExpr($plan, $d);
              $plan['select'][] = $expr.' AS '.$this->escapeAlias($key);
            } else {
              // OLD: for non-select dynamics keep your existing approach
              $this->ensureDynamicJoin($plan, $d);
              $colExpr = $this->dynValueColExpr($plan, $d);
              $plan['select'][] = $colExpr.' AS '.$this->escapeAlias($key);
            }
            continue;
          }
        } else {
          // dynamic disabled → keep null for shape
          $plan['select'][] = 'NULL AS '.$this->escapeAlias($key);
          continue;
        }
      }
     
       if (isset($customCol[$key]) && ($customCol[$key]['type'] ?? '') === 'raw') {
          $expr = $customCol[$key]['expr'];
          if (is_callable($expr)) {
              $sql = $expr($plan);
              $plan['select'][] = "{$sql} AS ".$this->escapeAlias($key);
          }
          continue;
      }

      // FK helpers (base-table joins)
      if (isset($columnNames[$key])) {
        $j = $columnNames[$key];
        $alias = $j['alias'];
        $on = $alias.'.'.$j['key2'].' = '.$t.'.'.$key;
        $this->addJoin($plan,$j['table'], $alias, $on, 'left');
        $plan['select'][] = $alias.'.'.$j['column'].' AS '.$this->escapeAlias($key);
        if (!empty($j['select'])) $plan['select'][] = $j['select'];
        continue;
      }
     
      if (isset($customCol[$key])) {
        $j = $customCol[$key];
        $alias = $j['alias'];
        $on = $alias.'.'.$j['key2'].' = '.$t.'.'.$key;
        $this->addJoin($plan, $j['table'], $alias, $on, 'left');
        $plan['select'][] = $alias.'.'.$j['column'].' AS '.$this->escapeAlias($key);
        if (!empty($j['select'])) $plan['select'][] = $j['select'];
        continue;
      }

      // Base column
      $plan['select'][] = $t.'.'.$key.' AS '.$this->escapeAlias($key);
    }
    //  print "<pre>";
    //   print_r($plan['select']);
}

  protected function buildFilters(array &$plan, array $filtersRows, array $dynMap, array $columnNames, array $customCol)
{
    $t  = $plan['base_alias'];
   
    foreach ($filtersRows as $row) {
      if (empty($row['columnName']) || empty($row['condition'])) continue;

      
      $field = $row['columnName'];
      $op    = strtolower($row['condition']);
      $isDyn = !empty($row['isDynamic']);
      $value = isset($row['value']) ? $row['value'] : null;
      $logic = strtoupper($row['logicalOp'] ?? 'AND');

      $condCallable = null;

      // Dynamic filter
      $dynMapById = null;
      if ($isDyn && !empty($plan['dynamic_enabled']) && isset($row['fieldObj'])) {
        $dynMapById = $this->dynById($dynMap, (int)$row['fieldObj']);
      }
      // print "<pre>";
      // var_dump($isDyn);
      // print_r($plan);
      if ($isDyn && !empty($plan['dynamic_enabled']) && $dynMapById !== null) {
      $d = $dynMapById;
    if ($this->isSelectType($d)) {
        // SELECT/RADIO/DROPDOWN/CHECKBOX → use options table
        $condCallable = $this->dynOptionExistsCallable($plan, $d, $op, $value);
        // IMPORTANT: do NOT override $condCallable below
    } else {
        // Non-select dynamic → compare actual wide-table column
        $this->ensureDynamicJoin($plan, $d);
        $colExpr = $this->dynValueColExpr($plan, $d);

        // Optional: if linked and UI asked for display text, filter on display column
        if ($this->isLinked($d) && !empty($row['mappedFieldName']) && $row['mappedFieldName'] === 'display') {
            $lkMeta = $this->Model->getLinkedMenuMeta((int)$d['linkedWith']);
            if ($lkMeta) {
                $lkAlias = $this->linkedAlias($d);
                $on2 = $lkAlias.'.'.$lkMeta['pk_name'].' = '.$this->dynJoinAlias($plan).'.'.$this->CI->db->escape_str($this->dynColumnName($d, $plan));
                $this->addJoin($plan, $lkMeta['table_name'], $lkAlias, $on2, 'left');
                $display = $this->linkedDisplay($d, $lkAlias);
                $condCallable = $this->buildOpCallable($op, $display, $value, $d);
            } else {
                $condCallable = $this->buildOpCallable($op, $colExpr, $value, $d);
            }
        } else {
            $condCallable = $this->buildOpCallable($op, $colExpr, $value, $d);
        }
    }
}elseif ($isDyn && empty($plan['dynamic_enabled'])) {
        // No dynamic table present: skip this filter safely
        continue;

      } else {
        
        // Base/custom column filter
        $columnExpr = null;

        if (isset($columnNames[$field])) {
          $j = $columnNames[$field];
          $alias = $j['alias'];
          $on = $alias.'.'.$j['key2'].' = '.$t.'.'.$field;
          //print $j['table'];
          $this->addJoin($plan,$j['table'], $alias, $on, 'left');
          // hide this as category table or any other table we used to match the key.
          //$columnExpr = $alias.'.'.$j['column'];
          $columnExpr = $alias.'.'.$j['key2'];
        } elseif (isset($customCol[$field])) {
          $j = $customCol[$field];
          $alias = $j['alias'];
          $on = $alias.'.'.$j['key2'].' = '.$t.'.'.$field;
          $this->addJoin($plan,$j['table'], $alias, $on, 'left');
          $columnExpr = $alias.'.'.$j['key2'];
          //$columnExpr = $alias.'.'.$j['key2'];
        } else {
          $columnExpr = $t.'.'.$field;
        }
         //print_r($op);
        $condCallable = $this->buildOpCallable($op, $columnExpr, $value, null);
      }

      if (!$condCallable) continue;

      // Do NOT pre-seed an empty group — it produces AND ( )
      if ($logic === 'OR') {
        // OR should extend the most-recent group; if none exists, start one
        if (empty($plan['where_groups'])) {
          $plan['where_groups'][] = [];
        }
        $idx = count($plan['where_groups']) - 1;
        $plan['where_groups'][$idx][] = function($qb) use ($condCallable) {
          $qb->or_group_start();
          $condCallable($qb);
          $qb->group_end();
        };
      } else {
        // AND starts a new group
        $plan['where_groups'][] = [$condCallable];
      }

    }
}
protected function ftIndexesFor($table) {
  $q = $this->CI->db->query("SHOW INDEX FROM `".$this->CI->db->escape_str($table)."` WHERE Index_type='FULLTEXT'");
  if (!$q || !$q->num_rows()) return [];
  $rows = $q->result_array();
  $idx = [];
  foreach ($rows as $r) {
    $name = $r['Key_name'];
    $seq  = (int)$r['Seq_in_index'];
    $idx[$name][$seq] = $r['Column_name'];
  }
  // normalize order
  foreach ($idx as $k => $cols) {
    ksort($cols);
    $idx[$k] = array_values($cols);
  }
  return $idx; // [index_name => [col1,col2,...]]
}

protected function buildFreeText(array &$plan, $needle, array $selectedColumns, array $columnNames,array $dynMap)
{
  $t     = $plan['base_alias'];
  $table = $this->chekPreFix($plan['from']);
  $like  = '%'.mb_strtolower($needle, 'UTF-8').'%';
  $boolQ = $this->booleanQuery($needle);

  $orExprs = [];

  // ----- BASE: prefer FULLTEXT (single OR term). Fallback: OR of LIKEs on selected base text-like cols.
  $usedFT = false;
  $ftIdx = $this->CI->db->query(
    "SHOW INDEX FROM `".$this->CI->db->escape_str($table)."` WHERE Index_type='FULLTEXT'"
  );
  if ($ftIdx && $ftIdx->num_rows()) {
    // pick the largest FT index
    $rows = $ftIdx->result_array();
    $byName = [];
    foreach ($rows as $r) {
      $byName[$r['Key_name']][(int)$r['Seq_in_index']] = $t.'.'.$this->CI->db->escape_str($r['Column_name']);
    }
    $bestCols = [];
    $bestCnt  = 0;
    foreach ($byName as $cols) {
      ksort($cols);
      if (count($cols) > $bestCnt) { $bestCnt = count($cols); $bestCols = array_values($cols); }
    }
    if ($bestCols) {
      $orExprs[] = 'MATCH('.implode(',', $bestCols).') AGAINST ('.$this->CI->db->escape($boolQ).' IN BOOLEAN MODE)';
      $usedFT = true;
    }
  }

  if (!$usedFT) {

    //print_r($selectedColumns);
    foreach ($selectedColumns as $col) {
      // dynamic selected columns handled below
      if (!empty($plan['dynamic_enabled']) && $this->resolveDynamicDefForKey($col, $dynMap)) continue;
      // skip obvious non-text base columns
      if (preg_match('/(_id|^id$|date|time|count|amount|qty|price|status)$/i', $col)) continue;
        if(isset($columnNames[$col]))
          $orExprs[] = 'LOWER('.$columnNames[$col]['alias'].'.'.$this->CI->db->escape_str($columnNames[$col]['column']).') LIKE '.$this->CI->db->escape($like);
        else  
          $orExprs[] = 'LOWER('.$t.'.'.$this->CI->db->escape_str($col).') LIKE '.$this->CI->db->escape($like);
    }
  }

  // ----- DYNAMIC: OR LIKE on each selected text-like dynamic column (single dynamic join)
  // if (!empty($plan['dynamic_enabled'])) {
  //   // ensure one join exists
  //   foreach ($selectedColumns as $col) {
  //     $d = $this->resolveDynamicDefForKey($col, $dynMap);
  //     if (!$d) continue;
  //     if (!$this->isTextLike($d)) continue;
  //     $this->ensureDynamicJoin($plan, $d);
  //     $dynCol = $this->dynColumnName($d, $plan);
  //     $orExprs[] = 'LOWER('.$this->dynJoinAlias($plan).'.'.$this->CI->db->escape_str($dynCol).') LIKE '.$this->CI->db->escape($like);
  //   }
  // }

  if (!empty($plan['dynamic_enabled'])) {
    $dbp    = (string)$this->CI->db->dbprefix;
    $menuId = (int)$plan['menu_id'];
    $pk     = $plan['pk'];
    $t      = $plan['base_alias'];
    // Optional: If you want freetext to search all dynamic select fields (not just those in selectedColumns), replace:
    //foreach ($dynMap as $d) {
    foreach ($selectedColumns as $col) {
      $d = $this->resolveDynamicDefForKey($col, $dynMap);
      if (!$d) continue;
      if (!$this->isSelectType($d)) continue;           // only select/radio/checkbox fields

      $fid = (int)$d['fieldID'];

      if ($this->isLinked($d)) {
        // Linked: search display column on linked table
        $lkMeta = $this->Model->getLinkedMenuMeta((int)$d['linkedWith']);
        if (!$lkMeta) continue;

        $tblRaw = $lkMeta['table_name'] ?? '';
        $pkey   = $lkMeta['pk_name']    ?? 'id';
        // Pick display column from dynamic field config first; fallback to lkMeta/display_col
        $disp   = $this->resolveLinkedDisplayColumn($d, $lkMeta);

        // Categories override
        if (stripos($tblRaw, 'categories') !== false) {
          $tbl  = $this->qualifyTbl('categories', $dbp);
          $pkey = 'category_id';
          $disp = 'categoryName';
        } else {
          $tbl  = $this->qualifyTbl($tblRaw, $dbp);
        }

        $orExprs[] =
          "EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
                   JOIN {$tbl} l ON l.{$pkey} = v.linked_id
                   WHERE v.module_id={$menuId}
                     AND v.record_id={$t}.{$pk}
                     AND v.field_id={$fid}
                     AND LOWER(l.{$disp}) LIKE ".$this->CI->db->escape($like).")";
      } else {
        // Local options: search option label
        $orExprs[] =
          "EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
                   JOIN {$dbp}dynamic_field_options o ON o.id = v.option_id
                   WHERE v.module_id={$menuId}
                     AND v.record_id={$t}.{$pk}
                     AND v.field_id={$fid}
                     AND LOWER(o.label) LIKE ".$this->CI->db->escape($like).")";
      }
    }
  }

  if (!$orExprs) return;
  //$plan['where'][] = '(' . implode(' OR ', $orExprs) . ')';
  // ---- IMPORTANT: push as ONE grouped OR via a closure ----
  $plan['where_groups'][] = function($qb) use ($orExprs) {
    $qb->group_start();
    $first = true;
    foreach ($orExprs as $expr) {
      if ($first) { $qb->where($expr, null, false); $first = false; }
      else        { $qb->or_where($expr, null, false); }
    }
    $qb->group_end();
  };
}
  protected function indexDynamicDefs(array $defs)
  {

    $out = [];
    foreach ($defs as $d) {
      // print "check out ".$d['column_name'];
      $key = !empty($d['column_name']) ? $d['column_name'] : ('dyn_'.$d['fieldID']);
      $d['_key'] = $key;
      $out[$key] = $d;
    }
    return $out;
  }

  protected function dynById(array $dynMap, $fieldId)
  {
    foreach ($dynMap as $d) { if ((int)$d['fieldID'] === (int)$fieldId) return $d; }
    return null;
  }
protected function ensureDynamicJoin(array &$plan, array $d)
{
  if (empty($plan['dynamic_enabled']) || empty($plan['dynamic_table'])) return;

  $alias  = $this->dynJoinAlias($plan);           // <-- single alias for dynamic table
  foreach ($plan['joins'] as $j) { if ($j['alias'] === $alias) return; }

  $t      = $plan['base_alias'];
  $pk     = $plan['pk'];
  $dv     = $plan['dynamic_table'];
  $menuId = (int)$plan['menu_id'];

  // Join once on PK (+ optional menu guard)
  $on = $alias.'.'.$this->recordCol($plan).' = '.$t.'.'.$pk;
  if (!empty($plan['dynamic_menu_col'])) {
    $on .= ' AND '.$alias.'.'.$plan['dynamic_menu_col'].' = '.$menuId;
  }
  $this->addJoin($plan, $dv, $alias, $on, 'left');

  // NOTE: linkedWith label joins are attached per-field elsewhere
}
protected function dynValueColExpr(array $plan, array $d)
{
  $alias  = $this->dynJoinAlias($plan);
  $col    = $this->dynColumnName($d, $plan);
  return $alias.'.'.$this->CI->db->escape_str($col);
}
protected function buildOpCallable($op, $colExpr, $value, $dynDefOrNull)
{
  // Normalize operator
  $op = strtolower($op);
  if ($op === 'is_in')     $op = 'like';
  if ($op === 'is_not_in') $op = 'not_in';

  // Field-type hints (only when dynamic def is present)
  $isCheckboxCsv = $dynDefOrNull && stripos($dynDefOrNull['fieldType'] ?? '', 'checkbox') !== false;
  $isDateLike    = $dynDefOrNull && $this->isDateLikeDef($dynDefOrNull);

  // --- Date presets ---
  $dateOps = ['today','yesterday','tomorrow','this_week','this_month','exact_date'];

  // Coerce: equal_to + ("today"/"yesterday"/...) -> preset op
  if ($op === 'equal_to' && is_string($value) && in_array(strtolower($value), $dateOps, true)) {
    $op = strtolower($value);
    $value = null;
  }

  // Handle direct preset operators
  if (in_array($op, $dateOps, true)) {
    $range = $this->datePresetRange($op, ['value' => $value]);
    return function($qb) use ($colExpr, $range) {
      $from = $qb->escape($range['from']);
      $to   = $qb->escape($range['to']);
      $qb->where("{$colExpr} BETWEEN {$from} AND {$to}", null, false);
    };
  }

  // --- String-based date range like "01-07-2025/31-07-2025" ---
  if (in_array($op, ['date_range','daterange','date-between'], true)) {
    $rng = $this->normalizeDateRange($value);
    if ($rng) {
      return function($qb) use ($colExpr, $rng) {
        $from = $qb->escape($rng['from']);
        $to   = $qb->escape($rng['to']);
        $qb->where("{$colExpr} BETWEEN {$from} AND {$to}", null, false);
      };
    }
    // Invalid range -> no-op
    return function($qb) { /* ignore invalid date_range */ };
  }

  // --- QoL: if it's a date-like field and equal_to has a literal date, use same-day window ---
  if ($op === 'equal_to' && $isDateLike && is_string($value) && strlen($value)) {
    $d = DateTime::createFromFormat('Y-m-d', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if ($d) {
      $from = $d->format('Y-m-d 00:00:00');
      $to   = $d->format('Y-m-d 23:59:59');
      return function($qb) use ($colExpr, $from, $to) {
        $qb->where("{$colExpr} BETWEEN ".$qb->escape($from)." AND ".$qb->escape($to), null, false);
      };
    }
  }
  if ($op === 'equal_to' && !$dynDefOrNull && is_string($value) && strpos($value, ',') !== false) {
    $vals = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    return function($qb) use ($colExpr, $vals) {
        $escaped = array_map([$qb, 'escape'], $vals);
        $qb->where("{$colExpr} IN (".implode(',', $escaped).")", null, false);
    };
  }

  // --- Standard operators ---
  switch ($op) {
    case 'equal_to':
    case '=':
      return function($qb) use ($colExpr, $value) {
        $qb->where("{$colExpr} = ".$qb->escape($value), null, false);
      };

    case 'not_equal_to':
    case '!=':
    case '<>':
      if ($this->isMultiSelect($dynDefOrNull)) {
        $val = $this->CI->db->escape($value);
        return function($qb) use ($colExpr, $val) {
          $qb->where("({$colExpr} IS NULL OR NOT FIND_IN_SET({$val}, {$colExpr}))", null, false);
        };
      }
      $val = is_numeric($value) ? (0 + $value) : $this->CI->db->escape($value);
      return function($qb) use ($colExpr, $val) {
        $qb->where("({$colExpr} IS NULL OR {$colExpr} <> {$val})", null, false);
      };

    case 'in': {
      $vals = is_array($value) ? $value
             : preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
      if ($isCheckboxCsv) {
        return function($qb) use ($colExpr, $vals) {
          $qb->group_start();
          foreach ($vals as $v) {
            $qb->or_where('FIND_IN_SET('.$qb->escape($v).', '.$colExpr.') != 0', null, false);
          }
          $qb->group_end();
        };
      }
      return function($qb) use ($colExpr, $vals) {
        $escaped = array_map([$qb, 'escape'], $vals);
        $qb->where("{$colExpr} IN (".implode(',', $escaped).")", null, false);
      };
    }

    case 'not_in': {
      $vals = is_array($value) ? $value
             : preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
      if ($isCheckboxCsv) {
        return function($qb) use ($colExpr, $vals) {
          foreach ($vals as $v) {
            $qb->where('FIND_IN_SET('.$qb->escape($v).', '.$colExpr.') = 0', null, false);
          }
        };
      }
      return function($qb) use ($colExpr, $vals) {
        $escaped = array_map([$qb, 'escape'], $vals);
        $qb->where("{$colExpr} NOT IN (".implode(',', $escaped).")", null, false);
      };
    }

    case 'like':
      return function($qb) use ($colExpr, $value) {
        // CI's like() would try to backtick field; we build raw:
        $qb->where("{$colExpr} LIKE ".$qb->escape('%'.(string)$value.'%'), null, false);
      };

    case 'ilike':
      return function($qb) use ($colExpr, $value) {
        $v = '%'.mb_strtolower((string)$value, 'UTF-8').'%';
        $qb->where('LOWER('.$colExpr.') LIKE '.$qb->escape($v), null, false);
      };

    case 'start_with':
      return function($qb) use ($colExpr, $value) {
        $qb->where("{$colExpr} LIKE ".$qb->escape((string)$value.'%'), null, false);
      };

    case 'end_with':
      return function($qb) use ($colExpr, $value) {
        $qb->where("{$colExpr} LIKE ".$qb->escape('%'.(string)$value), null, false);
      };

    case 'gte':
    case 'greater_than':
      return function($qb) use ($colExpr, $value) {
        $qb->where("{$colExpr} >= ".$qb->escape($value), null, false);
      };

    case 'lte':
    case 'less_than':
      return function($qb) use ($colExpr, $value) {
        $qb->where("{$colExpr} <= ".$qb->escape($value), null, false);
      };

    case 'between':
    case 'range': {
      // Accept both ['from'=>..,'to'=>..] and [from,to]
      $from = is_array($value) ? ($value['from'] ?? ($value[0] ?? null)) : null;
      $to   = is_array($value) ? ($value['to']   ?? ($value[1] ?? null)) : null;

      return function($qb) use ($colExpr, $from, $to) {
        if ($from !== null && $to !== null) {
          $qb->where("{$colExpr} BETWEEN ".$qb->escape($from)." AND ".$qb->escape($to), null, false);
        } else {
          if ($from !== null) $qb->where("{$colExpr} >= ".$qb->escape($from), null, false);
          if ($to   !== null) $qb->where("{$colExpr} <= ".$qb->escape($to),   null, false);
        }
      };
    }

    case 'is_empty':
      return function($qb) use ($colExpr) {
        $qb->group_start()
             ->where("{$colExpr} IS NULL", null, false)
             ->or_where("{$colExpr} = ''", null, false)
           ->group_end();
      };

    case 'is_not_empty':
      return function($qb) use ($colExpr) {
        $qb->group_start()
             ->where("{$colExpr} IS NOT NULL", null, false)
             ->where("{$colExpr} != ''", null, false)
           ->group_end();
      };

    default:
      return null;
  }
}
  protected function isTextLike(array $d)
  {
    $t = strtolower($d['fieldType']);
    return (strpos($t,'text') !== false) || in_array($t, ['email','web link','mobile no','string']);
  }

  protected function isLinked(array $d = null) {
  return $d && !empty($d['linkedWith']) && $d['linkedWith'] != null;
  }
  protected function dynAlias(array $d) { return 'dv_'.$d['fieldID']; }
  protected function linkedAlias(array $d) { return 'lk_'.$d['fieldID']; }

  // protected function linkedDisplay(array $d, $lkAlias)
  // {
  //   if (empty($d['fieldOptions'])) return $lkAlias.'.name';
  //   $opt = trim($d['fieldOptions']);
  //   if (strpos($opt, ',') !== false) { $opt = trim(explode(',', $opt)[0]); }
  //   if (strpos($opt, '.') !== false) {
  //     $parts = explode('.', $opt, 2);
  //     return $lkAlias.'.'.$parts[1];
  //   }
  //   return $lkAlias.'.'.$opt;
  // }

  protected function linkedDisplay(array $d, $lkAlias)
  {
    $lkMeta = $this->Model->getLinkedMenuMeta((int)$d['linkedWith']) ?: [];
    $col    = $this->resolveLinkedDisplayColumn($d, $lkMeta);
    return $lkAlias.'.'.$col;
  }

  protected function recordCol(array $plan) { return $plan['pk']; }

  protected function addJoin(array &$plan, $table, $alias, $on, $type='left')
  {
    $dbprefix = (string) $this->CI->db->dbprefix;

    // Decide if we need to prefix:
    $needsPrefix = ($dbprefix !== '');

    // Already prefixed?
    if ($needsPrefix && strncmp($table, $dbprefix, strlen($dbprefix)) === 0) {
        $needsPrefix = false;
    }
    // Has schema/db name? (e.g., mydb.table) → don’t prefix
    if ($needsPrefix && strpos($table, '.') !== false) {
        $needsPrefix = false;
    }
    // Backticked/qualified? (e.g., `ab_table`) → assume caller handled it
    if ($needsPrefix && strlen($table) && $table[0] === '`') {
        $needsPrefix = false;
    }

    if ($needsPrefix) {
        $table = $dbprefix.$table;
    }
    foreach ($plan['joins'] as $j) if ($j['alias'] === $alias) return;
    $plan['joins'][] = ['table'=>$table, 'alias'=>$alias, 'on'=>$on, 'type'=>$type];
  }

// protected function safeCol($key, array $plan, array $dynMap)
// {
//   $t = $plan['base_alias'];
//   if (!empty($plan['dynamic_enabled'])) {
//     $d = $this->resolveDynamicDefForKey($key, $dynMap);
//     if ($d) {
//       $this->ensureDynamicJoin($plan, $d);
//       return $this->dynValueColExpr($plan, $d);
//     }
//   }
//   return $t.'.'.$this->CI->db->escape_str($key);
// }
protected function safeCol($key, array $plan, array $dynMap)
{
  $t = $plan['base_alias'];
  if (!empty($plan['dynamic_enabled'])) {
    $d = $this->resolveDynamicDefForKey($key, $dynMap);
    if ($d) {
      // For select-type dynamics, sort by label (subselect)
      if ($this->isSelectType($d)) {
        // Use label expression without alias
        return $this->dynOptionLabelExpr($plan, $d, false);
      }
      // Non-select dynamic: sort by value column
      $this->ensureDynamicJoin($plan, $d);
      return $this->dynValueColExpr($plan, $d);
    }
  }
  return $t.'.'.$this->CI->db->escape_str($key);
}


  protected function escapeAlias($key) { return preg_replace('/[^A-Za-z0-9_]/', '_', $key); }
 // In FilterEngine
protected function datePresetRange($op, array $f = [])
{
  // Use your app timezone if needed
  $tz = new DateTimeZone('Asia/Kolkata'); // or read from config
  $today = new DateTime('today', $tz);

  $from = (clone $today)->setTime(0,0,0);
  $to   = (clone $today)->setTime(23,59,59);

  switch (strtolower($op)) {
    case 'today':
      break;

    case 'yesterday':
      $from->modify('-1 day'); 
      $to->modify('-1 day'); 
      break;

    case 'tomorrow':
      $from->modify('+1 day'); 
      $to->modify('+1 day'); 
      break;

    case 'this_week': {
      // Monday..Sunday
      $dow = (int)$today->format('w'); // 0=Sun..6=Sat
      $monday = (clone $today)->modify(($dow==0?'-6':'-'.($dow-1)).' days')->setTime(0,0,0);
      $sunday = (clone $monday)->modify('+6 days')->setTime(23,59,59);
      return ['from'=>$monday->format('Y-m-d H:i:s'), 'to'=>$sunday->format('Y-m-d H:i:s')];
    }

    case 'this_month': {
      $first = new DateTime($today->format('Y-m-01 00:00:00'), $tz);
      $last  = (new DateTime($today->format('Y-m-t 23:59:59'), $tz));
      return ['from'=>$first->format('Y-m-d H:i:s'), 'to'=>$last->format('Y-m-d H:i:s')];
    }

    case 'exact_date': {
      if (!empty($f['value'])) {
        $value = trim((string)$f['value']);

        // Try multiple date formats, incl. dd-mm-yyyy and ISO-ish inputs
        $formats = [
          'Y-m-d H:i:s',
          'Y-m-d',
          'd-m-Y H:i:s',
          'd-m-Y',
          'd/m/Y H:i:s',
          'd/m/Y',
          'Y/m/d H:i:s',
          'Y/m/d',
          'Y-m-d\TH:i:sP', // 2025-09-01T12:34:56+05:30
          'Y-m-d\TH:i:s',  // 2025-09-01T12:34:56
          'Y-m-d\TH:i',    // 2025-09-01T12:34
        ];

        $d = null;
        foreach ($formats as $fmt) {
          $d = DateTime::createFromFormat($fmt, $value, $tz);
          if ($d instanceof DateTime) {
            // ensure timezone is applied (createFromFormat with tz already sets it)
            break;
          }
        }

        // Fallback: unix timestamp (seconds)
        if (!$d && ctype_digit($value)) {
          $d = (new DateTime('@' . $value))->setTimezone($tz);
        }

        if ($d) {
          $start = (clone $d)->setTime(0, 0, 0);
          $end   = (clone $d)->setTime(23, 59, 59);
          return [
            'from' => $start->format('Y-m-d H:i:s'),
            'to'   => $end->format('Y-m-d H:i:s'),
          ];
        }
      }
      break;
    }

  }

  return ['from'=>$from->format('Y-m-d H:i:s'), 'to'=>$to->format('Y-m-d H:i:s')];
}

protected function isDateLikeDef($d) {
  if (!$d || !isset($d['fieldType'])) return false;
  $t = strtolower($d['fieldType']);
  return (strpos($t,'date') !== false) || (strpos($t,'time') !== false) || (strpos($t,'datetime') !== false);
}
protected function normalizeDateRange($value) {
  $from = null; $to = null;

  if (is_string($value)) {
    // Split between dates by "/", " - " (with spaces), or " to "
    // IMPORTANT: do not split on bare '-' inside the date tokens
    $parts = preg_split('/\s*\/\s*|\s+-\s+|\s+to\s+/i', $value);
    if (count($parts) >= 2) {
      $from = $this->parseFlexibleDate($parts[0]);
      $to   = $this->parseFlexibleDate($parts[1]);
    } else {
      // Single date → same-day window
      $d = $this->parseFlexibleDate($value);
      if ($d) { $from = clone $d; $to = clone $d; }
    }
  } elseif (is_array($value)) {
    $fv = $value['from'] ?? ($value[0] ?? null);
    $tv = $value['to']   ?? ($value[1] ?? null);
    $from = $this->parseFlexibleDate($fv);
    $to   = $this->parseFlexibleDate($tv);
  }

  if (!$from || !$to) return null;

  $from->setTime(0,0,0);
  $to->setTime(23,59,59);
  if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

  return [
    'from' => $from->format('Y-m-d H:i:s'),
    'to'   => $to->format('Y-m-d H:i:s'),
  ];
}


protected function parseFlexibleDate($s) {
  $s = trim((string)$s);
  if ($s === '') return null;

  // Common formats: dd-mm-yyyy, dd/mm/yyyy, yyyy-mm-dd, yyyy/mm/dd
  $formats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd.m.Y', 'Y.m.d'];

  foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $s);
    if ($dt && $dt->format($fmt) === $s) return $dt;
  }

  // Fallback: strtotime
  $ts = strtotime($s);
  if ($ts !== false) return (new DateTime())->setTimestamp($ts);

  return null;
}
// protected function isSelectType(array $d=null){
//   if(!$d) return false;
//   $t = strtolower($d['fieldType'] ?? '');
//   return in_array($t, ['radiolist','dropdown','checkboxlist','radio list','drop down'], true);
// }
protected function isSelectType(array $d=null){
  if(!$d) return false;
  $t = strtolower(trim($d['fieldType'] ?? ''));
  // normalize spaces/underscores
  $t = str_replace(['  ','_'], [' ',' '], $t);
  $aliases = [
    'radio list','radiolist','dropdown','drop down',
    'checkbox list','checkboxlist','checkbox'
  ];
  return in_array($t, $aliases, true);
}
protected function qualifyTbl(string $tbl, string $dbprefix) : string {
  // if caller already passed a fully-qualified table (starts with prefix), leave it
  if ($tbl === '') return $tbl;
  return (strpos($tbl, $dbprefix) === 0) ? $tbl : ($dbprefix . $tbl);
}
protected function dynOptionLabelExpr(array $plan, array $d, $wantIds = false) {
  $menuId   = (int)$plan['menu_id'];
  $t        = $plan['base_alias'];
  $pk       = $plan['pk'];
  $fid      = (int)$d['fieldID'];
  $multi    = (int)($d['is_multi'] ?? (stripos($d['fieldType'],'check')!==false ? 1:0)) === 1;
  $dbprefix = (string)$this->CI->db->dbprefix;

  // If caller asked for IDs, always return the IDs stored (linked_id or option_id)
  if ($wantIds) {
    return "(SELECT GROUP_CONCAT(v.linked_id ORDER BY v.position)
             FROM {$dbprefix}dynamic_values_option v
             WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid})";
  }

  // LINKED FIELD?
  if ($this->isLinked($d)) {
    $lkMeta = $this->Model->getLinkedMenuMeta((int)$d['linkedWith']);



    if (!$lkMeta) {
      // fallback: show stored IDs
      return "(SELECT GROUP_CONCAT(v.linked_id ORDER BY v.position)
               FROM {$dbprefix}dynamic_values_option v
               WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid})";
    }
    $dispRaw = $lkMeta['display_col'] ?? '';
    $tblRaw  = $lkMeta['table_name']  ?? '';
    $pkeyRaw = $lkMeta['pk_name']     ?? '';
    $tbl     = $this->qualifyTbl($tblRaw, $dbprefix);
    $pkey    = $pkeyRaw ?: 'id';
    $disp    = $this->resolveLinkedDisplayColumn($d, $lkMeta);
    // $dispRaw = $lkMeta['display_col'] ?? '';
    // $tblRaw  = $lkMeta['table_name']  ?? '';
    // $pkeyRaw = $lkMeta['pk_name']     ?? '';
    // $tbl     = $this->qualifyTbl($tblRaw, $dbprefix);
    // $disp    = $dispRaw ?: 'name';
    // $pkey    = $pkeyRaw ?: 'id';

    // SPECIAL CASE: categories
    // Expect table "ab_categories" (or "categories" which we qualify to "{$dbprefix}categories")
    // Key:  category_id; Label: categoryName
    if (stripos($tblRaw, 'categories') !== false) {
      $tbl   = $this->qualifyTbl('categories', $dbprefix); // force to ab_categories
      $pkey  = 'category_id';
      $disp  = 'categoryName';

      if ($multi) {
        return "(SELECT GROUP_CONCAT(c.{$disp} ORDER BY v.position SEPARATOR ', ')
                 FROM {$dbprefix}dynamic_values_option v
                 JOIN {$tbl} c ON c.{$pkey} = v.linked_id
                 WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid})";
      } else {
        return "(SELECT c.{$disp}
                 FROM {$dbprefix}dynamic_values_option v
                 JOIN {$tbl} c ON c.{$pkey} = v.linked_id
                 WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid}
                 ORDER BY v.position LIMIT 1)";
      }
    }

    // GENERAL LINKED TABLES
    if ($multi) {
      return "(SELECT GROUP_CONCAT(l.{$disp} ORDER BY v.position SEPARATOR ', ')
               FROM {$dbprefix}dynamic_values_option v
               JOIN {$tbl} l ON l.{$pkey} = v.linked_id
               WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid})";
    } else {
      return "(SELECT l.{$disp}
               FROM {$dbprefix}dynamic_values_option v
               JOIN {$tbl} l ON l.{$pkey} = v.linked_id
               WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid}
               ORDER BY v.position LIMIT 1)";
    }
  }

  // NOT LINKED: use local options table for labels
  if ($multi) {
    return "(SELECT GROUP_CONCAT(o.label ORDER BY v.position SEPARATOR ', ')
             FROM {$dbprefix}dynamic_values_option v
             JOIN {$dbprefix}dynamic_field_options o ON o.id = v.option_id
             WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid})";
  } else {
    return "(SELECT o.label
             FROM {$dbprefix}dynamic_values_option v
             JOIN {$dbprefix}dynamic_field_options o ON o.id = v.option_id
             WHERE v.module_id={$menuId} AND v.record_id={$t}.{$pk} AND v.field_id={$fid}
             ORDER BY v.position LIMIT 1)";
  }
}
protected function dynOptionExistsCallable(array $plan, array $d, $op, $value)
{
    $menuId   = (int)$plan['menu_id'];
    $base     = $plan['base_alias'];
    $pk       = $plan['pk'];
    $fieldId  = (int)$d['fieldID'];
    $isLinked = $this->isLinked($d);
    $dbp      = (string)$this->CI->db->dbprefix;
    $idCol    = $isLinked ? 'linked_id' : 'option_id';
    //$idCol    = 'option_id';

    $vals = $this->normalizeIdList($value); // accepts "9,10" or [9,10]
    $op   = strtolower((string)$op);

    // is_empty / is_not_empty
    if ($op === 'is_empty') {
        $sql = "NOT EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
                WHERE v.module_id={$menuId} AND v.record_id={$base}.{$pk} AND v.field_id={$fieldId})";
        return fn($qb) => $qb->where($sql, null, false);
    }
    if ($op === 'is_not_empty') {
        $sql = "EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
               WHERE v.module_id={$menuId} AND v.record_id={$base}.{$pk} AND v.field_id={$fieldId})";
        return fn($qb) => $qb->where($sql, null, false);
    }

    // LIKE on labels (also works for linked) – unchanged…

    // equal_to / in  → OR semantics via IN (...)
    if (in_array($op, ['equal_to', '=', 'in'], true)) {
        // if (empty($vals)) return fn($qb) => null;
        // $list = implode(',', array_map('intval', $vals));
        // $sql = "EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
        //        WHERE v.module_id={$menuId}
        //          AND v.record_id={$base}.{$pk}
        //          AND v.field_id={$fieldId}
        //          AND v.$idCol IN ($list))";
        // return fn($qb) => $qb->where($sql, null, false);
        // LIKE / ILIKE / starts / ends on labels
        if (in_array($op, ['like','ilike','start_with','end_with','contains'], true)) {
            $needle = (string)$value;
            $likeExpr = null;
            $dbp = $dbp ?? (string)$this->CI->db->dbprefix;

            if ($isLinked) {
                // Linked: join the linked master and search its display column
                $lkMeta = $this->Model->getLinkedMenuMeta((int)$d['linkedWith']);
                if ($lkMeta) {
                    $tblRaw = $lkMeta['table_name'] ?? '';
                    $pkey   = $lkMeta['pk_name']    ?? 'id';

                    // pick display column from dynamic field config first
                    $disp   = $this->resolveLinkedDisplayColumn($d, $lkMeta);

                    // category override
                    if (stripos($tblRaw, 'categories') !== false) {
                        $tbl  = $this->qualifyTbl('categories', $dbp);
                        $pkey = 'category_id';
                        $disp = 'categoryName';
                    } else {
                        $tbl  = $this->qualifyTbl($tblRaw, $dbp);
                    }

                    $likeExpr = function($qb) use ($dbp, $tbl, $disp, $pkey, $menuId, $base, $pk, $fieldId, $op, $needle) {
                        $lhs = "l.{$disp}";
                        $val = $needle;
                        if ($op === 'ilike') {
                            $lhs = "LOWER({$lhs})";
                            $val = mb_strtolower($needle, 'UTF-8');
                        }
                        $pattern = match ($op) {
                            'start_with' => $val.'%',
                            'end_with'   => '%'.$val,
                            default      => '%'.$val.'%',
                        };
                        $pattern = $qb->escape($pattern);

                        $sql = "EXISTS (
                          SELECT 1 FROM {$dbp}dynamic_values_option v
                          JOIN {$tbl} l ON l.{$pkey} = v.linked_id
                          WHERE v.module_id={$menuId}
                            AND v.record_id={$base}.{$pk}
                            AND v.field_id={$fieldId}
                            AND {$lhs} LIKE {$pattern}
                        )";
                        $qb->where($sql, null, false);
                    };
                }
            } else {
                // Local options table
                $likeExpr = function($qb) use ($dbp, $menuId, $base, $pk, $fieldId, $op, $needle) {
                    $lhs = "o.label";
                    $val = $needle;
                    if ($op === 'ilike') {
                        $lhs = "LOWER({$lhs})";
                        $val = mb_strtolower($needle, 'UTF-8');
                    }
                    $pattern = match ($op) {
                        'start_with' => $val.'%',
                        'end_with'   => '%'.$val,
                        default      => '%'.$val.'%',
                    };
                    $pattern = $qb->escape($pattern);

                    $sql = "EXISTS (
                      SELECT 1 FROM {$dbp}dynamic_values_option v
                      JOIN {$dbp}dynamic_field_options o ON o.id = v.option_id
                      WHERE v.module_id={$menuId}
                        AND v.record_id={$base}.{$pk}
                        AND v.field_id={$fieldId}
                        AND {$lhs} LIKE {$pattern}
                    )";
                    $qb->where($sql, null, false);
                };
            }

            return $likeExpr ?: (fn($qb) => null);
        }

    }

    // not_equal_to / not_in → NOT EXISTS with IN (...)
    if (in_array($op, ['not_equal_to','!=','<>','not_in'], true)) {
        if (empty($vals)) return fn($qb) => null;
        $list = implode(',', array_map('intval', $vals));
        $sql = "NOT EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
               WHERE v.module_id={$menuId}
                 AND v.record_id={$base}.{$pk}
                 AND v.field_id={$fieldId}
                 AND v.$idCol IN ($list))";
        return fn($qb) => $qb->where($sql, null, false);
    }

    // Fallback: behave like IN
    if (!empty($vals)) {
        $list = implode(',', array_map('intval', $vals));
        $sql = "EXISTS (SELECT 1 FROM {$dbp}dynamic_values_option v
               WHERE v.module_id={$menuId}
                 AND v.record_id={$base}.{$pk}
                 AND v.field_id={$fieldId}
                 AND v.$idCol IN ($list))";
        return fn($qb) => $qb->where($sql, null, false);
    }
    return fn($qb) => null;
}


/** Normalize array/CSV/scalar to a unique list of integer IDs. */
protected function normalizeIdList($value): array
{
    if (is_array($value)) {
        $tokens = $value;
    } elseif ($value === null || $value === '') {
        $tokens = [];
    } else {
        $tokens = preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
    }
    $out = [];
    foreach ($tokens as $t) {
        $s = trim((string)$t);
        if ($s !== '' && ctype_digit($s)) $out[] = (int)$s;
    }
    return array_values(array_unique($out));
}
protected function resolveLinkedDisplayColumn(array $d, ?array $lkMeta = null): string
{
  // 1) Prefer explicit column from dynamic field config
  $opt = trim((string)($d['linkedDisplayColumn'] ?? ''));
  if ($opt === '' && !empty($d['fieldOptions'])) {
    $opt = trim((string)$d['fieldOptions']);        // fallback if older data stored it here
  }
  if ($opt !== '') {
    if (strpos($opt, ',') !== false)  $opt = trim(explode(',', $opt)[0]); // take first if CSV
    if (strpos($opt, '.') !== false)  $opt = explode('.', $opt, 2)[1];    // strip table alias if present
    return $opt;
  }

  // 2) Fallback to lkMeta.display_col, else sane default
  $fallback = trim((string)($lkMeta['display_col'] ?? ''));
  return $fallback !== '' ? $fallback : 'name';
}

protected function chekPreFix($table){
  $dbprefix = (string) $this->CI->db->dbprefix;

    // Decide if we need to prefix:
    $needsPrefix = ($dbprefix !== '');

    // Already prefixed?
    if ($needsPrefix && strncmp($table, $dbprefix, strlen($dbprefix)) === 0) {
        $needsPrefix = false;
    }
    // Has schema/db name? (e.g., mydb.table) → don’t prefix
    if ($needsPrefix && strpos($table, '.') !== false) {
        $needsPrefix = false;
    }
    // Backticked/qualified? (e.g., `ab_table`) → assume caller handled it
    if ($needsPrefix && strlen($table) && $table[0] === '`') {
        $needsPrefix = false;
    }

    if ($needsPrefix) {
        $table = $dbprefix.$table;
    }
    return $table;
}

}
