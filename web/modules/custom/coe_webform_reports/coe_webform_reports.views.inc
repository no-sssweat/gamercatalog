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

//  $data['coe_webform_reports_view_count']['webform_id'] = [
//    'title' => t('Example Webform content'),
//    'help' => t('Some example content that references a webform.'),
//    'relationship' => [
//      'base' => 'webform',
//      'base field' => 'webform_id', // The name of the field on the joined table.
//      // 'field' => 'nid' -- see hook_views_data_alter(); not needed here.
//      'handler' => 'views_handler_relationship',
//      'label' => t('Example Webform'),
//    ],
//  ];

//  // Define 'webform_id' as the primary field.
//  $data['coe_webform_reports_view_count']['webform_id'] = [
//    'title' => t('Webform ID'),
//    'help' => t('The unique Webform ID'),
//    'field' => [
//      'title' => t('Webform ID'),
//      'field' => 'webform_id',
//      'help' => t('The unique Webform ID'),
//      'id' => 'string',
//    ],
//  ];
//
//  $data['example_table']['table']['group'] = t('Example table');
//
//  $data['example_table']['table']['base'] = array(
//    'field' => 'nid',
//    'title' => t('Example table'),
//    'help' => t('Example table contains example content and can be related to nodes.'),
//    'weight' => -10,
//  );
//
//  $data['example_table']['table']['join'] = array(
//    'node' => array(
//      'left_field' => 'nid',
//      'field' => 'nid',
//    ),
//  );
//
//  $data['example_table']['nid'] = array(
//    'title' => t('Example content'),
//    'help' => t('Some example content that references a node.'),
//    'relationship' => array(
//      'base' => 'node',
//      'base field' => 'nid', // The name of the field on the joined table.
//      // 'field' => 'nid' -- see hook_views_data_alter(); not needed here.
//      'handler' => 'views_handler_relationship',
//      'label' => t('Example node'),
//    ),
//  );
//
//  $data['example_table']['plain_text_field'] = array(
//    'title' => t('Plain text field'),
//    'help' => t('Just a plain text field.'),
//    'field' => array(
//      'handler' => 'views_handler_field',
//      'click sortable' => TRUE,
//    ),
//    'sort' => array(
//      'handler' => 'views_handler_sort',
//    ),
//    'filter' => array(
//      'handler' => 'views_handler_filter_string',
//    ),
//    'argument' => array(
//      'handler' => 'views_handler_argument_string',
//    ),
//  );
//
//  $data['example_table']['numeric_field'] = array(
//    'title' => t('Numeric field'),
//    'help' => t('Just a numeric field.'),
//    'field' => array(
//      'handler' => 'views_handler_field_numeric',
//      'click sortable' => TRUE,
//    ),
//    'filter' => array(
//      'handler' => 'views_handler_filter_numeric',
//    ),
//    'sort' => array(
//      'handler' => 'views_handler_sort',
//    ),
//  );
//
//  $data['example_table']['boolean_field'] = array(
//    'title' => t('Boolean field'),
//    'help' => t('Just an on/off field.'),
//    'field' => array(
//      'handler' => 'views_handler_field_boolean',
//      'click sortable' => TRUE,
//    ),
//    'filter' => array(
//      'handler' => 'views_handler_filter_boolean_operator',
//      'label' => t('Published'),
//      'type' => 'yes-no',
//      'use equal' => TRUE,
//    ),
//    'sort' => array(
//      'handler' => 'views_handler_sort',
//    ),
//  );
//
//  $data['example_table']['timestamp_field'] = array(
//    'title' => t('Timestamp field'),
//    'help' => t('Just a timestamp field.'),
//    'field' => array(
//      'handler' => 'views_handler_field_date',
//      'click sortable' => TRUE,
//    ),
//    'sort' => array(
//      'handler' => 'views_handler_sort_date',
//    ),
//    'filter' => array(
//      'handler' => 'views_handler_filter_date',
//    ),
//  );

  return $data;
}
