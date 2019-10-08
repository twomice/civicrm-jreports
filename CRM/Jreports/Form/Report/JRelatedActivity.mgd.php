<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Jreports_Form_Report_JRelatedActivity',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'Related Activities',
      'description' => 'If I\'m meeting with Bill next week, who else might have meetings with Bill, or with others in his company?',
      'class_name' => 'CRM_Jreports_Form_Report_JRelatedActivity',
      'report_url' => 'jreports/jrelatedactivity',
      'component' => '',
    ),
  ),
);
