<?php

/**
 * Implements hook_views_data().
 */
function coe_webform_reports_views_data() {
  $data = [];

  $data['coe_webform_reports_view_count']['table']['group'] = t('Reports');
  $data['coe_webform_reports_view_count']['table']['base'] = [
    'field' => 'webform_id',
    'title' => t('Webform Reports'),
  ];

  // Define 'view_count' as a field.
  $data['coe_webform_reports_view_count']['view_count'] = [
    'title' => t('View Count'),
    'help' => t('The view count of a webform'),
    'field' => [
      'title' => t('View Count'),
      'field' => 'view_count',
      'help' => t('The view count of a webform'),
      'id' => 'numeric',
    ],
    'filter' => [
      'title' => t('View Count Filter'),
      'field' => 'view_count',
      'id' => 'numeric',
    ],
  ];

  $data['coe_webform_reports_view_count']['entity_label'] = [
    'title' => t('Entity label'),
    'help' => t('The label of the entity that is related to the file.'),
    'real field' => 'id',
    'field' => [
      'id' => 'entity_label',
      'entity type field' => 'type'
    ],
  ];

  $data['coe_webform_reports_view_count']['entity_title'] = [
    'title' => t('Entity label'),
    'help' => t('The label of the entity that is related to the file.'),
    'real field' => 'title',
    'filter' => [
      'title' => t('Entity Label Filter'),
      'help' => t('Filter by Entity Label'),
      'id' => 'string',
    ],
  ];

  // Define a relationship to the Webform entity using a custom handler.
  $data['coe_webform_reports_view_count']['webform'] = [
    'title' => t('Webform'),
    'help' => t('Relate to the Webform entity.'),
    'relationship' => [
      'title' => t('Webform entity'),
      'help' => t('Relate to the Webform entity.'),
      'base' => 'webform',
      'base field' => 'id',
      'relationship field' => 'webform_id',
      'handler' => 'custom_webform_relationship_handler', // Replace with your actual handler name.
      'label' => t('Webform'),
    ],
  ];

  // Add operation fields for the Webform.
  $data['coe_webform_reports_view_count']['webform']['webform_edit'] = [
    'title' => t('Edit Webform'),
    'help' => t('Link to edit the Webform.'),
    'field' => [
      'title' => t('Edit Webform'),
      'id' => 'webform_edit',
    ],
  ];

  $data['webform_reports']['table']['group'] = t('Webform Report');
  $data['webform_reports']['table']['join'] = [
    // #global is a special flag which allows a table to appear all the time.
    '#global' => [],
  ];

  $data['webform_reports']['google_sheets_view_link'] = [
    'title' => t('Google Sheets Link'),
    'help' => t('Shows the google sheets link'),
    'field' => [
      'id' => 'google_sheets_view_link',
      'click sortable' => FALSE,
    ],
  ];

  return $data;
}
