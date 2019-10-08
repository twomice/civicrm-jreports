<?php

/**
 * Description of Report
 *
 * @author as
 */
class CRM_Jreports_Form_Report extends CRM_Report_Form {
  var $_debug = FALSE;

  /**
   * Depending on the value of $this->_debug, either indicate that the given
   * table should be temporary, or that it should be created as a regular table
   * for later review. For regular tables, drop the table in case it exists
   * already.
   *
   * @param <type> $table_name
   * @return string
   */
  function _debug_temp_table($table_name) {
    if ($this->_debug) {
      $query = "DROP TABLE IF EXISTS {$table_name}";
      CRM_Core_DAO::executeQuery($query);
      $temporary = '';
    }
    else {
      $temporary = 'TEMPORARY';
    }
    return $temporary;
  }

  /**
   * Debug logger. If $this->_debug is TRUE, send $var to dsm() with label $label.
   */
  function _debugDsm($var, $label = NULL) {
    if ($this->_debug && function_exists('dsm')) {
      dsm($var, $label);
    }
  }


}
