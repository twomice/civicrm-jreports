<?php
use CRM_Jreports_ExtensionUtil as E;

class CRM_Jreports_Form_Report_JRelatedActivity extends CRM_Jreports_Form_Report {
  /**
   * @var Boolean
   *   Set to TRUE to cause temporary tables to be preserved (and probably then
   *   you should remember to delete them manually)
   * @see parent::_debug_temp_table() and parent::_debugDsm()
   */
  var $_debug = FALSE;

  /**
   * @var String
   *   Base name of the temporary table holding related contact_ids.
   */
  var $_contactsTableName = 'JRelatedActivity_contacts';

  /**
   * @var Array
   *  List of display names, cached in this var for performance
   */
  var $_displayNames = [];

  /**
   * @var Array
   *  List of lowest associated contact_id per activity, cached in this var for performance
   */
  var $_activityCids;

  function __construct() {
    // Filters in this report support special parameters beginning with 'x_filter',
    // to indicate the context in which the filter should be applied. This report
    // uses a multi-step procsss with multiple queries, and this 'x_filter' approach
    // allows us to indicate exactly which WHERE clauses should contain the given
    // filter values.  This is similar in desig to the 'pseudoconstant' filter
    // parameter.
    $this->_columns = array(
      'civicrm_contact' => array(
      ),
      'civicrm_activity' => array(
        'fields' => array(
          'id' => array(
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'atype' => array(
            'name' => 'activity_type_id',
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'aid' => array(
            'name' => 'id',
            'title' => E::ts('Activity ID'),
          ),
          'subject' => array(
            'title' => E::ts('Subject'),
            'default' => TRUE,
          ),
          'activity_date_time' => array(
            'title' => E::ts('Date/Time'),
            'default' => TRUE,
          ),
          'activity_type_id' => array(
            'title' => E::ts('Activity Type'),
          ),
          'duration' => array(
            'title' => E::ts('Duration'),
          ),
          'status_id' => array(
            'title' => E::ts('Status'),
          ),
          'campaign_id' => array(
            'title' => E::ts('Campaign'),
          ),
        ),
        'filters' => array(
          'activity_date_time' => array(
            'title' => E::ts('Activity date/time'),
            'operatorType' => CRM_Report_Form::OP_DATETIME,
            'type' => CRM_Utils_Type::T_DATE,
            'x_filterContactsTableSubselectA' => TRUE,
          ),
          'activity_type_id' => array(
            'title' => E::ts('Activity type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT,
            'options' => CRM_Activity_BAO_Activity::buildOptions('activity_type_id'),
            'default' => array(
              1,2,3
            ),
            'x_filterContactsTableSubselectA' => TRUE,
          ),
          'campaign_id' => array(
            'title' => E::ts('Campaign'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT,
            'options' => CRM_Activity_BAO_Activity::buildOptions('campaign_id'),
            'x_filterContactsTableSubselectA' => TRUE,
          ),
        ),
      ),
      'civicrm_activity_contact' => array(
        'alias' => 'ac',
      ),
      'civicrm_relationship' => array(
        'filters' => array(
          'relationship_type_id' => array(
            'title' => E::ts('Relationship type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'type' => CRM_Utils_Type::T_INT,
            'options' => self::_buildRelationshipyTypeFilterOptions(),
            'pseudofield' => TRUE,
            'x_filterContactsTableRelationshipHops' => TRUE,
            'pseudofield' => TRUE,
          ),
        ),
      ),
      'rca' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'record_type_1' => array(
            'title' => E::ts('Assigned Contacts'),
            'default' => TRUE,
            'dbAlias' => "GROUP_CONCAT(if (ac_civireport.record_type_id = 1, ac_civireport.contact_id, NULL))",
          ),
          'record_type_2' => array(
            'title' => E::ts('Source Contacts'),
            'default' => TRUE,
            'dbAlias' => "GROUP_CONCAT(if (ac_civireport.record_type_id = 2, ac_civireport.contact_id, NULL))",
          ),
          'record_type_3' => array(
            'title' => E::ts('With Contacts'),
            'default' => TRUE,
            'dbAlias' => "GROUP_CONCAT(if (ac_civireport.record_type_id = 3, ac_civireport.contact_id, NULL))",
          ),
        ),
        'grouping' => 'contact-fields',
      ),
    );

    parent::__construct();

    // Remove 'contact id' column; it's not needed for us, but parent added it.
    unset($this->_columns['civicrm_contact']['fields']['exposed_id']);
  }

  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $this->_from = "

      FROM civicrm_activity_contact {$this->_aliases['civicrm_activity_contact']}
      INNER JOIN civicrm_activity {$this->_aliases['civicrm_activity']} ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_contact']}.activity_id
        INNER JOIN (
          SELECT distinct ac.activity_id
          FROM {$this->_contactsTableName} rc
          INNER JOIN civicrm_activity_contact ac ON ac.contact_id = rc.contact_id AND ac.record_type_id IN (1,3)
        ) rca ON rca.activity_id = {$this->_aliases['civicrm_activity']}.id
    ";
  }

  /**
   * Replacement for parent::where(); allows us to specify WHERE context, which
   * matters in this report because we have filters that need to be applied at
   * various places in the multi-query data construction process.
   *
   * @param String $context Match for $this->_columns[x]['filters'] elements like
   *  'x_filter*'. See also comments for $this->_columns in self::_construct().
   */
  function where($context = NULL) {    
    foreach ($this->_columns as $tableName => &$tableProperties) {
      if (array_key_exists('filters', $tableProperties)) {
        foreach ($tableProperties['filters'] as &$filter) {
          if ($context !== NULL) {
            if (!array_key_exists('x_pseudofield_original', $filter)) {
              $filter['x_pseudofield_original'] = CRM_Utils_Array::value('pseudofield', $filter, FALSE);
            }
            $filter['pseudofield'] = !CRM_Utils_Array::value($context, $filter, FALSE);
          }
          else {
            $filter['pseudofield'] = CRM_Utils_Array::value('x_pseudofield_original', $filter, FALSE);
          }
        }
      }
    }

    // Clear these, because we actually run where() multiple times.
    $this->_whereClauses = [];
    $this->_havingClauses = [];

    parent::where();

    if ($context === NULL) {
      $this->_where .= "
        
        -- Exclude activities from the final output if 'source' contact is current user.
        AND {$this->_aliases['civicrm_activity']}.id NOT IN (
          SELECT activity_id
          FROM civicrm_activity_contact
          WHERE contact_id = ". (int) CRM_Core_Session::getLoggedInContactID() ."
            AND record_type_id = 2
      )";
    }

    $this->_where = "
      -- START where clause for context '$context'.
      {$this->_where}
      -- END where clause for context '$context'.
    ";
  }

  function groupBy() {
    $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_activity']}.id";
  }

  function alterDisplay(&$rows) {
    $userCid = (int) CRM_Core_Session::getLoggedInContactID();

    $displayNameQuery = "
      SELECT c.id, c.display_name
      FROM civicrm_contact c
        INNER JOIN {$this->_contactsTableName} rc ON rc.contact_id = c.id
      UNION
      SELECT id, display_name
      FROM civicrm_contact
      WHERE id = '{$userCid}';
    ";
    $dao = crm_core_dao::executeQuery($displayNameQuery);
    $this->_displayNames = CRM_Utils_Array::rekey($dao->fetchAll(), 'id');

    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_activity_subject', $row) &&
        $row['civicrm_activity_id']
      ) {
        if (empty($rows[$rowNum]['civicrm_activity_subject'])) {
          $rows[$rowNum]['civicrm_activity_subject'] = '[' . E::ts('no subject') . ']';
        }
        $activitySourceCid = $this->_getActivityCid($row['civicrm_activity_id'], $rows);
        $url = CRM_Utils_System::url("civicrm/activity",
          "atype={$row['civicrm_activity_atype']}&action=view&reset=1&id={$row['civicrm_activity_id']}&cid={$activitySourceCid}" ,
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_activity_subject_link'] = $url;
        $rows[$rowNum]['civicrm_activity_subject_hover'] = E::ts("View Activity");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_activity_campaign_id', $row) &&
        $row['civicrm_activity_campaign_id']
      ) {
        $rows[$rowNum]['civicrm_activity_campaign_id'] = $this->_columns['civicrm_activity']['filters']['campaign_id']['options'][$rows[$rowNum]['civicrm_activity_campaign_id']];
        $entryFound = TRUE;
      }

      if ($columnValue = CRM_Utils_Array::value('rca_record_type_1', $row)) {
        $rows[$rowNum]['rca_record_type_1'] = $this->_alterDisplayRecordTypeContacts($columnValue);
        $entryFound = TRUE;
      }

      if ($columnValue = CRM_Utils_Array::value('rca_record_type_2', $row)) {
        $rows[$rowNum]['rca_record_type_2'] = $this->_alterDisplayRecordTypeContacts($columnValue);
        $entryFound = TRUE;
      }

      if ($columnValue = CRM_Utils_Array::value('rca_record_type_3', $row)) {
        $rows[$rowNum]['rca_record_type_3'] = $this->_alterDisplayRecordTypeContacts($columnValue);
        $entryFound = TRUE;
      }

      if ($columnValue = CRM_Utils_Array::value('civicrm_activity_activity_type_id', $row)) {
        $options = CRM_Activity_BAO_Activity::buildOptions('activity_type_id');
        $rows[$rowNum]['civicrm_activity_activity_type_id'] = $options[$rows[$rowNum]['civicrm_activity_activity_type_id']];
        $entryFound = TRUE;
      }

      if ($columnValue = CRM_Utils_Array::value('civicrm_activity_status_id', $row)) {
        $options = CRM_Activity_BAO_Activity::buildOptions('status_id');
        $rows[$rowNum]['civicrm_activity_status_id'] = $options[$rows[$rowNum]['civicrm_activity_status_id']];
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  function _alterDisplayRecordTypeContacts($columnValue) {
    $values = [];
    foreach (explode(',', $columnValue) as $cid) {
      if (!$contact = CRM_Utils_Array::value($cid, $this->_displayNames)) {
        $this->_displayNames[$cid]['display_name'] = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $cid, 'display_name');
      }
      $displayName = $this->_displayNames[$cid]['display_name'];
      $url = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='. $cid);
      $title = E::ts('View Contact');
      $values[] = '<a title="'. $title .'" href="'. $url .'">'. $displayName .'</a>';
    }
    return implode(', ', $values);
  }

  /**
   * Overrides parent::beginPostProcessCommon().
   */
  function beginPostProcessCommon() {
    parent::beginPostProcessCommon();
    $this->_buildContactsTable();
  }

  private function _buildContactsTable() {
    $userCid = CRM_Core_Session::getLoggedInContactID();

    $temporary_rcontacts = $this->_debug_temp_table($this->_contactsTableName);
    $temporary_rcontacts2 = $this->_debug_temp_table("{$this->_contactsTableName}2");
    $temporary_rcontacts3 = $this->_debug_temp_table("{$this->_contactsTableName}3");

    // Build the where clauses for the subselect
    $this->where('x_filterContactsTableSubselectA');
    $where_filterContactsTableSubselectA = $this->_where;

    // Build the where clauses for the relationship hops
    $this->where('x_filterContactsTableRelationshipHops');
    $where_filterContactsTableRelationshipHops = $this->_where;

    $query = "
      -- Create initial {$this->_contactsTableName} table
      CREATE $temporary_rcontacts TABLE {$this->_contactsTableName} (UNIQUE (contact_id)) SELECT DISTINCT ac.contact_id, 0 AS hops FROM
      (
        SELECT ac.activity_id
        FROM civicrm_activity_contact ac
          INNER JOIN civicrm_activity {$this->_aliases['civicrm_activity']} ON {$this->_aliases['civicrm_activity']}.id = ac.activity_id
          {$where_filterContactsTableSubselectA}
          AND ac.record_type_id = 2
          AND ac.contact_id = '$userCid' -- CURRENT USER
      ) a
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.activity_id
        AND ac.record_type_id IN (1,3)
        AND contact_id != '$userCid' -- CURRENT USER
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']} ON {$this->_aliases['civicrm_contact']}.id = ac.contact_id
    ";

    $this->sqlArray[] = $query;
    CRM_Core_DAO::executeQuery($query);

    $query = "CREATE $temporary_rcontacts2 TABLE {$this->_contactsTableName}2 SELECT * FROM {$this->_contactsTableName}";
    $this->sqlArray[] = $query;
    CRM_Core_DAO::executeQuery($query);

    $query = "
      -- Add new 1-hop contacts to {$this->_contactsTableName} table
      INSERT IGNORE INTO {$this->_contactsTableName} (contact_id, hops)
      SELECT
        IF(c.contact_id = {$this->_aliases['civicrm_relationship']}.contact_id_a, {$this->_aliases['civicrm_relationship']}.contact_id_b, {$this->_aliases['civicrm_relationship']}.contact_id_a) AS contact_id,
        1 AS hops
      FROM {$this->_contactsTableName}2 c
        INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON c.contact_id IN ({$this->_aliases['civicrm_relationship']}.contact_id_a, {$this->_aliases['civicrm_relationship']}.contact_id_b)
          $where_filterContactsTableRelationshipHops
          AND c.hops = 0
    ";

    $this->sqlArray[] = $query;
    CRM_Core_DAO::executeQuery($query);

    $query = "CREATE $temporary_rcontacts3 TABLE {$this->_contactsTableName}3 SELECT * FROM {$this->_contactsTableName}";
    $this->sqlArray[] = $query;
    CRM_Core_DAO::executeQuery($query);

    $query = "
      -- Add new 2-hop contacts to {$this->_contactsTableName} table
      INSERT IGNORE INTO {$this->_contactsTableName} (contact_id, hops)
      SELECT
        IF(c.contact_id = {$this->_aliases['civicrm_relationship']}.contact_id_a, {$this->_aliases['civicrm_relationship']}.contact_id_b, {$this->_aliases['civicrm_relationship']}.contact_id_a) AS contact_id,
        2 AS hops
      FROM {$this->_contactsTableName}3 c
        INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON c.contact_id IN ({$this->_aliases['civicrm_relationship']}.contact_id_a, {$this->_aliases['civicrm_relationship']}.contact_id_b)
          $where_filterContactsTableRelationshipHops
          AND c.hops = 1
    ";
    $this->sqlArray[] = $query;
    CRM_Core_DAO::executeQuery($query);
  }

  static function _buildRelationshipyTypeFilterOptions() {
    $options = array();
    foreach (CRM_Core_PseudoConstant::relationshipType() as $relationshipTypeId => $relationshipType) {
      $options[$relationshipTypeId] = "{$relationshipType['label_a_b']} / {$relationshipType['label_b_a']}";
    }
    return $options;
  }

  private function _getActivityCid($aid, $rows) {
    if (!isset($this->_activityCids)) {
      $aids = CRM_Utils_Array::collect('civicrm_activity_id', $rows);
      if (!empty($aids)) {
        $query = "
          SELECT activity_id, min(contact_id) as cid
          FROM
            civicrm_activity_contact
          WHERE
            activity_id IN (". implode(',', $aids) .")
          GROUP BY activity_id
        ";
        $dao = CRM_Core_DAO::executeQuery($query);
        while($dao->fetch()) {
          $this->_activityCids[$dao->activity_id] = $dao->cid;
        }
      }
    }
    return $this->_activityCids[$aid];
  }
}
