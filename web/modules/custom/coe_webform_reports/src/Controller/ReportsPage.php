<?php

namespace Drupal\coe_webform_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewExecutableFactory;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  public function content() {

    $view = $this->entityTypeManager->getStorage('view')->load('webform_report');
    $view = $this->viewExecutableFactory->get($view);
    $display_id = 'embed_administer';
    $view->setDisplay('embed_administer');

    $webform_id = 'contact';
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    $fields = $webform->getElementsDecoded();
    $fields['operations'] = [
      '#title' => 'Operations',
      '#type' => 'operations',
    ];
    foreach($fields as $field_name => $field_config) {
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
          'webform_element_format' => 'value',
        ];
        $view->addHandler($display_id, 'field', $table, $field, $field_settings);
//        ksm($field_name);
//        ksm($field_config);
        $text_filters = [
          'textfield',
          'textarea',
          'email',
        ];
        if (in_array($field_config['#type'], $text_filters)) {
          // Add filter
          $view->addHandler($display_id, 'filter', $table, $field, [
            'id' => $table . '_' . $field, // Replace with your actual table name and field name.
            'table' => $table,
            'field' => $field, // Replace with the appropriate operator.
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => "",
            'operator' => 'contains',
            'value' => '',
            'group' => 1,
            'exposed' => true,
            'is_grouped' => false,
            'expose' => [
              'operator_id' => 'webform_submission_value_' . $field_name . '_op',
              'label' => $field_config['#title'],
              'description' => '',
              'use_operator' => false,
              'operator' => 'webform_submission_value_' . $field_name . '_op',
              'operator_limit_selection' => false,
              'operator_list' => [],
              'identifier' => $field_name,
              'required' => false,
              'remember' => false,
              'multiple' => false,
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
            'id' => $table . '_' . $field, // Replace with your actual table name and field name.
            'table' => $table,
            'field' => $field, // Replace with the appropriate operator.
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => "",
            'operator' => 'in',
            'value' => [],
            'group' => 1,
            'exposed' => true,
            'is_grouped' => false,
            'expose' => [
              'operator_id' => 'webform_submission_value_' . $field_name . '_op',
              'label' => $field_config['#title'],
              'description' => '',
              'use_operator' => false,
              'operator' => 'webform_submission_value_' . $field_name . '_op',
              'operator_limit_selection' => false,
              'operator_list' => [],
              'identifier' => $field_name,
              'required' => false,
              'remember' => false,
              'multiple' => false,
              'reduce' => false,
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
              'id' => $table . '_' . $field, // Replace with your actual table name and field name.
              'table' => $table,
              'field' => $field, // Replace with the appropriate operator.
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => "",
              'operator' => '=',
              'value' => 'All',
              'group' => 1,
              'exposed' => true,
              'is_grouped' => false,
              'expose' => [
                'operator_id' => 'webform_submission_value_' . $field_name . '_op',
                'label' => $field_config['#title'],
                'description' => '',
                'use_operator' => false,
                'operator' => 'webform_submission_value_' . $field_name . '_op',
                'operator_limit_selection' => false,
                'operator_list' => [],
                'identifier' => $field_name,
                'required' => false,
                'remember' => false,
                'multiple' => false,
                'is_grouped' => false,
              ],
              'plugin_id' => 'webform_submission_checkbox_filter',
            ], $field_name);
        }
      }
    }

    $view->preExecute();
    $view->execute();
    $view->render();
//    ksm($view);
//    ksm($view->filter);


    return [
      'top' => [
        '#markup' => 'Hello, this is my custom page content!',
      ],
      'body' => [
        '#type' => 'view',
        '#view' => $view,
        '#embed' => true,
        '#cache' => $view->getCacheTags(),
      ]
    ];
  }

}
