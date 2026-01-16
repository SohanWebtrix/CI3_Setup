<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CommonModelNew extends CI_Model
{
  public function __construct()
  {
    parent::__construct();
    $this->load->database();
    $this->db->db_debug = false;
  }
/** Resolve table name with CI dbprefix (returns e.g. ab_menu_master). */
private function tbl($short)
{
    return $this->db->dbprefix($short);
}

/** Run raw SQL and return row_array() or null; logs SQL on failure. */
private function qrow($sql, array $binds, $ctx)
{
    $q = $this->db->query($sql, $binds);
    if ($q === false) {
        $err = $this->db->error();
        log_message('error', "{$ctx} DB error {$err['code']}: {$err['message']} | SQL: {$sql} | BINDS: ".json_encode($binds));
        return null;
    }
    return $q->row_array();
}

/** Run raw SQL and return result_array() or []; logs SQL on failure. */
private function qres($sql, array $binds, $ctx)
{
    $q = $this->db->query($sql, $binds);
    if ($q === false) {
        $err = $this->db->error();
        log_message('error', "{$ctx} DB error {$err['code']}: {$err['message']} | SQL: {$sql} | BINDS: ".json_encode($binds));
        return [];
    }
    return $q->result_array();
}

/** Check if a column exists on a table (using raw SHOW COLUMNS). */
private function colExists($table, $column)
{
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
    $q   = $this->db->query($sql, [$column]);
    return $q !== false && $q->num_rows() > 0;
}
/** Run a built SELECT safely; returns row_array() or null. Logs SQL + DB error on failure. */
private function getRowSafely(CI_DB_query_builder $qb, $context)
{
    $sql = $qb->get_compiled_select();       // <-- full SQL (SELECT ... FROM ... WHERE ...)
    $q   = $qb->get();
    if ($q === false) {
        $err = $this->db->error();
        log_message('error', "{$context} DB error {$err['code']}: {$err['message']} | SQL: {$sql}");
        // TEMP: print to browser if you want to see it immediately:
        header('Content-Type: text/plain');
        echo "{$context} FAILED\nERR {$err['code']}: {$err['message']}\nSQL: {$sql}\n";
        exit;
        // return null; // (use this instead of exit in production)
    }
    return $q->row_array();
}

/** Run a built SELECT safely; returns result_array() or []. Logs SQL + DB error on failure. */
private function getResultSafely(CI_DB_query_builder $qb, $context)
{
    $sql = $qb->get_compiled_select();
    $q   = $qb->get();
    if ($q === false) {
        $err = $this->db->error();
        log_message('error', "{$context} DB error {$err['code']}: {$err['message']} | SQL: {$sql}");
        header('Content-Type: text/plain');
        echo "{$context} FAILED\nERR {$err['code']}: {$err['message']}\nSQL: {$sql}\n";
        exit;
        // return [];
    }
    return $q->result_array();
}

/** (Optional) Build, don’t run — returns the full SELECT as SQL for a plan. */
public function compilePlanSQL(array $plan)
{
    $qb = $this->db; // CI clones automatically
    // Reuse your plan applier:
    if (!method_exists($this, 'applyPlanToQB')) {
        log_message('error', 'compilePlanSQL: applyPlanToQB not found');
        return '';
    }
    $this->applyPlanToQB($qb, $plan);
    return $qb->get_compiled_select();
}


public function getMenuMeta($menuId)
{
    log_message('error', 'getMenuMeta CALLED with menuId = ' . $menuId);

    $tMenu = $this->tbl('menu_master'); // resolves to ab_menu_master

    $sql = "SELECT * FROM `{$tMenu}` WHERE `menuID` = ? LIMIT 1";
    $row = $this->qrow($sql, [(int)$menuId], 'getMenuMeta');
    if (!$row) return null;

    //$module = $row['module_name'] ?? $row['moduleName'] ?? $row['module'] ?? '';
    // change this to menu id as we can edit the name of the link and it will affect the exiting created dynamic table
    $module = $row['menuID'] ?? '';
    $table  = $row['table_name']  ?? $row['tableName']  ?? $row['table']  ?? $row['base_table'] ?? '';
    if (!$table) return null;

    $pk = $this->detectPrimaryKey($table);
    if (!$pk) {
        $singular = rtrim($table, 's');
        $pk = $singular.'_id';
        log_message('debug', "getMenuMeta: PK guessed '{$pk}' for table '{$table}'");
    }
    // dynamic table may or may not exist (OK either way)
    //$dynBase        = $table ? strtolower($table) : preg_replace('/^'.$this->db->dbprefix.'/i', '', strtolower($table));
    $dynBase        = $module ? strtolower($module) : preg_replace('/^'.$this->db->dbprefix.'/i', '', strtolower($module));
    $dynamicTable   = 'ab_dynamic_'.$dynBase; // adjust if your prefix differs
    //print $dynamicTable   = 'ab_dynamic_'.$module; // adjust if your prefix differs
    $dynamicEnabled = $this->db->table_exists($dynamicTable);
    $dynamicMenuCol = $dynamicEnabled && $this->db->field_exists('menu_id', $dynamicTable) ? 'menu_id' : null;

    return [
        'menuID'            => (int)$menuId,
        'module_name'       => $module,
        'table_name'        => $table,
        'pk_name'           => $pk,
        'base_alias'        => 't',
        'base_table'        => $table,
        'pk'                => $pk,
        'dynamic_table'     => $dynamicEnabled ? $dynamicTable : null,
        'dynamic_menu_col'  => $dynamicMenuCol,
        'dynamic_enabled'   => $dynamicEnabled,
    ];
}



public function getDynamicDefs($menuId)
{
    $tDyn = $this->tbl('dynamic_fields'); // ab_dynamic_fields

    $sql = "SELECT `fieldID`,`menuID`,`fieldType`,`column_name`,allowMultiSelect,linkedDisplayColumn,`linkedWith`,`fieldOptions`,`status`
            FROM `{$tDyn}`
            WHERE `menuID` = ? AND `status` = 'active'
            ORDER BY `fieldID` ASC";
    return $this->qres($sql, [(int)$menuId], 'getDynamicDefs');
}

   public function getLinkedMenuMeta($linkedMenuId)
  {
      $tMenu = $this->tbl('menu_master');

      $sql = "SELECT * FROM `{$tMenu}` WHERE `menuID` = ? LIMIT 1";
      $row = $this->qrow($sql, [(int)$linkedMenuId], 'getLinkedMenuMeta');
      if (!$row) return null;

      $module = $row['module_name'] ?? $row['moduleName'] ?? $row['module'] ?? '';
      $table  = $row['table_name']  ?? $row['tableName']  ?? $row['table']  ?? $row['base_table'] ?? '';
      if (!$table) return null;

      $pk = $this->detectPrimaryKey($table);
      if (!$pk) {
          $singular = rtrim($table, 's');
          $pk = $singular.'_id';
          log_message('debug', "getLinkedMenuMeta: PK guessed '{$pk}' for table '{$table}'");
      }

      return [
          'menuID'            => (int)$linkedMenuId,
          'module_name'       => $module,
          'table_name'        => $table,
          'pk_name'           => $pk,
          'base_alias'        => 'l',
          'display_col_guess' => 'name',
      ];
  }

// helper: does callable accept at least one parameter?
private function callableTakesParam($fn): bool {
    try {
        if (is_array($fn)) { $ref = new \ReflectionMethod($fn[0], $fn[1]); }
        else               { $ref = new \ReflectionFunction($fn); }
        return $ref->getNumberOfParameters() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}
public function applyPlanToQB(CI_DB_query_builder $qb, array $plan, $mode = 'list')
{
    // FROM + alias
    $from = $plan['from'];
    $alias = $plan['base_alias'];
    $qb->from($from.' '.$alias);

    // SELECT (only for list mode)
    if ($mode === 'list') {
        $selects = isset($plan['select']) && is_array($plan['select'])
            ? array_values(array_unique(array_filter($plan['select'])))
            : [];
        if (empty($selects)) {
            // fallback to PK so we never end up with SELECT *
            $selects[] = $alias.'.'.$this->db->escape_str($plan['pk']).' AS '.$this->db->escape_str($plan['pk']);
        }
        // join all select expressions into a single raw string
        $qb->select(implode(', ', $selects), false);
    }

    // JOINS
    if (!empty($plan['joins'])) {
        foreach ($plan['joins'] as $j) {
            $tbl = $j['table'].(!empty($j['alias']) ? ' '.$j['alias'] : '');
            $type = !empty($j['type']) ? $j['type'] : 'left';
            // 4th param false keeps the ON clause raw (CI3 supports it)
            $qb->join($tbl, $j['on'], $type, false);
        }
    }

    // WHERE (raw strings or callables)
    if (!empty($plan['where'])) {
        
        foreach ($plan['where'] as $w) {
            if (is_callable($w)) {
                $w($qb);
            } elseif (is_string($w) && $w !== '') {
                $qb->where($w, null, false);
            }
        }
    }

    // WHERE GROUPS (arrays or callables)
    if (!empty($plan['where_groups'])) {
        foreach ($plan['where_groups'] as $wg) {
            if (is_callable($wg)) {
                $wg($qb);
                continue;
            }
            if (is_array($wg) && !empty($wg)) {
                $qb->group_start();
                $first = true;
                foreach ($wg as $cond) {
                    if (is_callable($cond)) {
                        $cond($qb);
                        $first = false;
                        continue;
                    }
                    if (is_string($cond) && $cond !== '') {
                        if ($first) { $qb->where($cond, null, false); $first = false; }
                        else        { $qb->or_where($cond, null, false); }
                    }
                }
                $qb->group_end();
            }
        }
    }

    // GROUP BY
    if (!empty($plan['group_by'])) {
        foreach ($plan['group_by'] as $g) {
            if ($g) $qb->group_by($g);
        }
    }

    // ORDER BY
    // if (!empty($plan['order_by'])) {
    //     foreach ($plan['order_by'] as $o) {
    //         if (is_array($o) && !empty($o['col'])) {
    //             $qb->order_by($o['col'], $o['dir'] ?? '', false);
    //         } elseif (is_string($o) && $o !== '') {
    //             $qb->order_by($o, '', false);
    //         }
    //     }
    // }
    foreach (($plan['order_by'] ?? []) as $ob) {
        // If 'escape' is set on this entry, pass it; otherwise let CI use its default.
        if (array_key_exists('escape', $ob)) {
            $qb->order_by($ob['col'], $ob['dir'], (bool)$ob['escape']);
        } else {
            $qb->order_by($ob['col'], $ob['dir']);
        }
    }
}

// public function applyPlanToQB(CI_DB_query_builder $qb, array $plan)
// {
//     // FROM
//     $qb->from($plan['from'].' '.$plan['base_alias']);

//     // JOINS
//     foreach (($plan['joins'] ?? []) as $j) {
//         $type = $j['type'] ?? 'left';
//         $qb->join($j['table'].' '.$j['alias'], $j['on'], $type, false); // don't escape ON
//     }

//     // WHERE GROUPS: each group is AND-wrapped by QB; inside, items are ANDed unless you pass OR yourself
//     foreach (($plan['where_groups'] ?? []) as $group) {
//         $materialized = [];
//         $hadQbCallable = false;

//         foreach ($group as $cond) {
//             if (is_callable($cond)) {
//                 if ($this->callableTakesParam($cond)) {
//                     // advanced: callable that MUTATES $qb and should manage its own grouping if needed
//                     $hadQbCallable = true;
//                     $cond($qb); // e.g., $qb->group_start(); ...; $qb->group_end();
//                 } else {
//                     // simple: callable returns string or ['sql'=>..., 'bind'=>...]
//                     $out = $cond();
//                     if (is_string($out)) {
//                         $out = trim($out);
//                         if ($out !== '') $materialized[] = ['sql'=>$out, 'bind'=>null];
//                     } elseif (is_array($out) && isset($out['sql'])) {
//                         $sql = trim($out['sql'] ?? '');
//                         if ($sql !== '') $materialized[] = ['sql'=>$sql, 'bind'=>$out['bind'] ?? null];
//                     }
//                 }
//             } elseif (is_string($cond)) {
//                 $sql = trim($cond);
//                 if ($sql !== '') $materialized[] = ['sql'=>$sql, 'bind'=>null];
//             } elseif (is_array($cond) && isset($cond['sql'])) {
//                 $sql = trim($cond['sql'] ?? '');
//                 if ($sql !== '') $materialized[] = ['sql'=>$sql, 'bind'=>$cond['bind'] ?? null];
//             }
//         }

//         // Only emit (...) if we actually have conditions to place inside
//         if (!empty($materialized)) {
//             $qb->group_start();
//             foreach ($materialized as $m) {
//                 $qb->where($m['sql'], $m['bind'], false);
//             }
//             $qb->group_end();
//         }
//       }

//     // WHERE (non-grouped)
//     foreach (($plan['where'] ?? []) as $w) {
//         if (is_callable($w)) {
//             $w = $w();
//         }
//         if (is_string($w)) {
//             $w = trim($w);
//             if ($w !== '') { $qb->where($w, null, false); }
//         } elseif (is_array($w) && isset($w['sql'])) {
//             $sql  = trim($w['sql'] ?? '');
//             $bind = $w['bind'] ?? null;
//             if ($sql !== '') { $qb->where($sql, $bind, false); }
//         }
//     }

//     // ORDER BY
//     if (!empty($plan['order_by'])) {
//         foreach ($plan['order_by'] as $ob) {
//             $qb->order_by($ob['col'], $ob['dir'], false);
//         }
//     }

//     // GROUP BY (only if you really need it for list queries)
//     if (!empty($plan['group_by'])) {
//         foreach ($plan['group_by'] as $gb) { $qb->group_by($gb, false); }
//     }

//     return $qb;
// }


  // public function applyPlanToQB(CI_DB_query_builder $qb, array $plan)
  // {
  //   // if (!empty($plan['select'])) {
  //   //   $qb->select(implode(',', $plan['select']), false);
  //   // }
  //   $qb->from($plan['from'].' '.$plan['base_alias']);
  //   foreach (($plan['joins'] ?? []) as $j) {
  //     $type = isset($j['type']) ? $j['type'] : 'left';
  //     $qb->join($j['table'].' '.$j['alias'], $j['on'], $type);
  //   }

  //   foreach (($plan['where_groups'] ?? []) as $group) {
  //     $qb->group_start();
  //     foreach ($group as $cond) {
  //       if (is_callable($cond)) {
  //         $cond($qb);
  //       } else {
  //         $qb->where($cond, null, false);
  //       }
  //     }
  //     $qb->group_end();
  //   }

  //   foreach (($plan['where'] ?? []) as $w) {
  //     if (is_callable($w)) { $w($qb); } else { $qb->where($w, null, false); }
  //   }

  //   if (!empty($plan['order_by'])) {
  //     foreach ($plan['order_by'] as $ob) {
  //       $qb->order_by($ob['col'], $ob['dir']);
  //     }
  //   }
  //   if (!empty($plan['group_by'])) {
  //     foreach ($plan['group_by'] as $gb) $qb->group_by($gb);
  //   }

  //   return $qb;
  // }

  public function countByPlan(array $plan, $pk)
{
    // Fresh QB
    $this->db->reset_query();
    $qb = $this->db;

    // Build SELECT ... FROM ... JOIN ... WHERE ...
    $this->applyPlanToQB($qb, $plan,'count');

    // COUNT DISTINCT on base PK
    $qb->select('COUNT(DISTINCT '.$plan['base_alias'].'.'.$this->db->escape_str($pk).') AS c', false);

    // Avoid ORDER BY in count queries (some drivers error out)
    $qb->order_by('', '', false);

    // Log SQL before executing
    $sql = $qb->get_compiled_select();
    $q = $this->db->query($sql);
    //$q = $qb->get();
    if ($q === false) {
        $err = $this->db->error();
        log_message('error', "countByPlan DB error {$err['code']}: {$err['message']} | SQL: {$sql}");
        return 0;
    }

    $row = $q->row_array();
    return (int)($row['c'] ?? 0);
}

public function listByPlan(array $plan, $limit, $offset)
{
    // Fresh QB
    $this->db->reset_query();
    $qb = $this->db;

    // Build SELECT ... FROM ... JOIN ... WHERE ...
    $this->applyPlanToQB($qb, $plan,'list');
    $select = is_array($plan['select'] ?? null) ? implode(',', $plan['select']) : ($plan['select'] ?? '*');
    $qb->select($select, false);

    // Paging
    if($limit != null)
    $qb->limit((int)$limit, (int)$offset);
    
    // Log SQL before executing
    $sql = $qb->get_compiled_select();

    //$q = $qb->get();
    $q = $this->db->query($sql);
    if ($q === false) {
        $err = $this->db->error();
        log_message('error', "listByPlan DB error {$err['code']}: {$err['message']} | SQL: {$sql}");
        return [];
    }

    return $q->result_array();
}
  /** Detect the primary key column for a MySQL table. Returns string|null. */
  private function detectPrimaryKey($tableName)
  {
      // $tableName is already a full table (e.g., ab_customer)
      if(!strpos($this->db->dbprefix,$tableName)){
        $tableName = $this->db->dbprefix.$tableName;
      }
      $safe = str_replace('`','', $this->db->escape_str($tableName));
      $sql  = "SHOW KEYS FROM `{$safe}` WHERE Key_name = 'PRIMARY'";
      $q    = $this->db->query($sql);
      if ($q === false) {
          $err = $this->db->error();
          log_message('error', "detectPrimaryKey DB error {$err['code']}: {$err['message']} | SQL: {$sql}");
          return null;
      }
      $rows = $q->result_array();
      // print "close<pre>";
      // print_r($rows);
      if (!$rows) return null;
      $cols = array_column($rows, 'Column_name');
      if (count($cols) > 1) {
          log_message('debug', 'detectPrimaryKey: composite PK on '.$tableName.' ('.implode(',', $cols).') — using '.$cols[0]);
      }
      return $cols[0];
  }
  /**
 * Return the list of column keys (strings) the user selected for a menu.
 * Reads ab_user_column_data.c_metadata (JSON array of objects) and extracts "column_name".
 * Ensures PK is included as the first column.
 *
 * @param int         $menuId
 * @param int|string  $userId
 * @param string|null $pkRequired  base table PK (if known)
 * @return array
 */
public function getUserSelectedColumns($menuId, $userId, $pkRequired = null)
{

    $tUcd = $this->tbl('user_column_data'); // ab_user_column_data

    if (!$this->db->table_exists($tUcd)) {
        return $pkRequired ? [$pkRequired] : [];
    }

    // Prefer updated_at > created_at if present
    $orderCol = null;
    if ($this->colExists($tUcd, 'updated_at'))      $orderCol = 'updated_at';
    elseif ($this->colExists($tUcd, 'created_at'))  $orderCol = 'created_at';

    $sql = "SELECT `c_metadata` FROM `{$tUcd}` WHERE `menu_id` = ? AND `user_id` = ?";
    if ($orderCol) $sql .= " ORDER BY `{$orderCol}` DESC";
    $sql .= " LIMIT 1";

    $row = $this->qrow($sql, [(int)$menuId, (string)$userId], 'getUserSelectedColumns');
    if (!$row || empty($row['c_metadata'])) {
        return $pkRequired ? [$pkRequired] : [];
    }

    $meta = json_decode($row['c_metadata'], true);
    if (!is_array($meta)) {
        log_message('error', "getUserSelectedColumns: invalid JSON for user_id={$userId}, menu_id={$menuId}");
        return $pkRequired ? [$pkRequired] : [];
    }

    $cols = [];
    foreach ($meta as $item) {
        if (is_array($item) && !empty($item['column_name'])) {
            $cols[] = (string)$item['column_name'];
        }
    }
    $cols = array_values(array_unique(array_filter($cols, 'strlen')));

    if ($pkRequired && !in_array($pkRequired, $cols, true)) {
        array_unshift($cols, $pkRequired);
    }

    return $cols;
}


}
