<?php

namespace Drupal\coe_webform_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ReportsPage
 *
 * Controller class for handling reports-related pages and actions.
 */
class ReportsPage extends ControllerBase {

  /**
   * Constructor for MyController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\views\ViewExecutableFactory $viewExecutableFactory
   *   The ViewExecutableFactory service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ViewExecutableFactory $viewExecutableFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->viewExecutableFactory = $viewExecutableFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('views.executable')
    );
  }

  public function content($webform) {

    $webform_id = $webform;

    $view = $this->entityTypeManager->getStorage('view')->load('webform_report');
    $view = $this->viewExecutableFactory->get($view);
    $display_id = 'embed_administer';
    $view->setDisplay('embed_administer');
    // Add a contextual filter to the view.
    $contextual_filter_value = $webform_id;
    // Set the argument.
    $view->setArguments([$contextual_filter_value]);

    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    $fields = $webform->getElementsDecoded();
//    ksm($fields);
    $fields['operations'] = [
      '#title' => 'Operations',
      '#type' => 'operations',
    ];
    foreach ($fields as $field_name => $field_config) {
//      ksm($field_config);
      if ($field_config['#type'] != 'webform_actions') {
        // Add field
        $table = 'webform_submission_field_' . $webform_id . '_' . $field_name;
        $field = 'webform_submission_value';
        if ($field_config['#type'] == 'operations') {
          $table = 'webform_submission';
          $field = 'operations';
        }
        $field_settings = [
          'table' => $table,
          'field' => $field,
          'label' => $field_config['#title'],
          'webform_element_format' => $field_config['#type'],
          'webform_multiple_value' => TRUE,
          'webform_multiple_delta' => 0,
          'webform_check_access' => 1,
        ];
        $image_fields = [
          'webform_signature',
          'webform_image',
        ];
        if (in_array($field_config['#type'], $image_fields)) {
          $field_settings['webform_element_format'] = 'image';
        }
        $view->addHandler($display_id, 'field', $table, $field, $field_settings);
//        ksm($field_name);
//        ksm($field_config);

        // Add filters
        $text_filters = [
          'textfield',
          'textarea',
          'email',
        ];
        if (in_array($field_config['#type'], $text_filters)) {
          // Add filter
          $view->addHandler($display_id, 'filter', $table, $field, [
            'id' => $table . '_' . $field,
            'table' => $table,
            'field' => $field,
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => "",
            'operator' => 'contains',
            'value' => '',
            'group' => 1,
            'exposed' => TRUE,
            'is_grouped' => FALSE,
            'expose' => [
              'operator_id' => 'webform_submission_value_' . $field_name . '_op',
              'label' => $field_config['#title'],
              'description' => '',
              'use_operator' => FALSE,
              'operator' => 'webform_submission_value_' . $field_name . '_op',
              'operator_limit_selection' => FALSE,
              'operator_list' => [],
              'identifier' => $field_name,
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
              'placeholder' => '',
            ],
            'plugin_id' => 'webform_submission_field_filter',
          ], $field_name);
        }

        $radio_filters = [
          'radios',
        ];
        if (in_array($field_config['#type'], $radio_filters)) {
          // Add filter
          $view->addHandler($display_id, 'filter', $table, $field, [
            'id' => $table . '_' . $field,
            'table' => $table,
            'field' => $field,
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => "",
            'operator' => 'in',
            'value' => [],
            'group' => 1,
            'exposed' => TRUE,
            'is_grouped' => FALSE,
            'expose' => [
              'operator_id' => 'webform_submission_value_' . $field_name . '_op',
              'label' => $field_config['#title'],
              'description' => '',
              'use_operator' => FALSE,
              'operator' => 'webform_submission_value_' . $field_name . '_op',
              'operator_limit_selection' => FALSE,
              'operator_list' => [],
              'identifier' => $field_name,
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
              'reduce' => FALSE,
            ],
            'plugin_id' => 'webform_submission_select_filter',
          ], $field_name);
        }
        $checkboxes_filters = [
          'checkbox',
        ];
        if (in_array($field_config['#type'], $checkboxes_filters)) {
            // Add filter
            $view->addHandler($display_id, 'filter', $table, $field, [
              'id' => $table . '_' . $field,
              'table' => $table,
              'field' => $field,
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => "",
              'operator' => '=',
              'value' => '',
              'group' => 1,
              'exposed' => TRUE,
              'is_grouped' => TRUE,
              'expose' => [
                'operator_id' => 'webform_submission_value_' . $field_name . '_op',
                'label' => $field_config['#title'],
                'description' => '',
                'use_operator' => FALSE,
                'operator' => 'webform_submission_value_' . $field_name . '_op',
                'operator_limit_selection' => FALSE,
                'operator_list' => [],
                'identifier' => $field_name,
                'required' => TRUE,
                'remember' => FALSE,
                'multiple' => FALSE,
                'is_grouped' => TRUE,
              ],
              'group_info' => [
                'label' => $field_config['#title'],
                'description' => '',
                'identifier' => $field_name,
                'optional' => TRUE,
                'widget' => 'select',
                'multiple' => FALSE,
                'remember' => FALSE,
                'default_group' => 'All',
                'default_group_multiple' => [],
                'group_items' => [
                  1 => [
                    'title' => 'Yes',
                    'operator' => '=',
                    'value' => 1,
                  ],
                  2 => [
                    'title' => 'No',
                    'operator' => '=',
                    'value' => 0,
                  ],
                  3 => [
                    'title' => '',
                    'operator' => '=',
                    'value' => 0,
                  ],
                ]
              ],
              'plugin_id' => 'webform_submission_checkbox_filter',
            ], $field_name);
        }
      }
    }

//    $view->preExecute();
    $view->execute();
    $view_output = $view->render();

    return [
      'top' => [
        '#markup' => 'Hello, this is my custom page content!',
      ],
      'body' => [
        '#type' => 'block',
        'content' => $view_output,
        '#cache' => $view->getCacheTags(),
      ]
    ];
  }

}
