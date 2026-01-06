<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Loads dynamic field definitions from ab_dynamic_fields for a given menu/module.
 * Adjust column names to your schema if needed.
 */
class DynamicFieldModel extends CI_Model
{
  protected $defTable = 'ab_dynamic_fields'; // definitions table

  public function __construct()
  {
    parent::__construct();
    $this->load->database();
  }

  /**
   * Returns a dynamic map keyed by a safe field key for UI/filters/CRUD.
   * Each entry: ['field_id'=>int, 'type'=>'string|number|date|linkedwith|enum', 'linked'=>[...optional...]]
   *
   * REQUIRED columns (rename in SELECT if your names differ):
   * - fieldID, menuID, field_key (or slug), type
   * OPTIONAL for linkedwith:
   * - linked_table, linked_alias, linked_on_left, linked_on_right, linked_display
   */
  public function getDynamicMap($menuId)
  {
    $this->db->from($this->defTable.' f')
             ->select('f.fieldID, f.menuID, f.field_key, f.slug, f.type, f.linked_table, f.linked_alias, f.linked_on_left, f.linked_on_right, f.linked_display')
             ->where('f.menuID', (int)$menuId)
             ->where('f.status', 'active'); // optional

    $defs = $this->db->get()->result_array();
    $map  = [];

    foreach ($defs as $d) {
      $key = !empty($d['field_key']) ? $d['field_key'] : (!empty($d['slug']) ? $d['slug'] : ('dyn_'.$d['fieldID']));
      $type = strtolower($d['type']);

      // normalize types
      if (in_array($type, ['text','string','varchar'], true)) $type = 'string';
      if ($type === 'datetime') $type = 'date';
      if ($type === 'linked_with' || $type === 'linked') $type = 'linkedwith';

      $entry = [
        'field_id' => (int)$d['fieldID'],
        'type'     => $type,
      ];

      if ($type === 'linkedwith' && !empty($d['linked_table']) && !empty($d['linked_alias']) && !empty($d['linked_on_left']) && !empty($d['linked_on_right'])) {
        $entry['linked'] = [
          'table'  => $d['linked_table'],
          'alias'  => $d['linked_alias'],
          'on'     => $d['linked_on_left'].' = '.$d['linked_on_right'], // e.g., city.id = dv_XX.int_value
          'display'=> !empty($d['linked_display']) ? $d['linked_display'] : $d['linked_alias'].'.name',
          'filter_field' => ['column' => (!empty($d['linked_display']) ? $d['linked_display'] : $d['linked_alias'].'.name'), 'type' => 'string', 'ops' => ['like','ilike','equal_to','start_with','end_with']],
        ];
      }

      $map[$key] = $entry;
    }

    return $map;
  }
}
