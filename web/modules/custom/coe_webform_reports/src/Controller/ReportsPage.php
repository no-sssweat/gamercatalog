<?php

namespace Drupal\coe_webform_reports\Controller;

use Drupal\coe_webform_reports\Service\ViewCount;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\views\ViewExecutableFactory;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ReportsPage
 *
 * Controller class for handling reports-related pages and actions.
 */
class ReportsPage extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewExecutableFactory;

  /**
   * The view count service.
   *
   * @var \Drupal\coe_webform_reports\Service\ViewCount
   */
  protected $viewCountService;

  /**
   * Constructor for MyController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\views\ViewExecutableFactory $viewExecutableFactory
   *   The ViewExecutableFactory service.
   * @param \Drupal\coe_webform_reports\Service\ViewCount $view_count_service
   *   The view count service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ViewExecutableFactory $viewExecutableFactory,
    ViewCount $view_count_service
  ) {
      $this->entityTypeManager = $entityTypeManager;
      $this->viewExecutableFactory = $viewExecutableFactory;
      $this->viewCountService = $view_count_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('views.executable'),
      $container->get('coe_webform_reports.view_count'),
    );
  }

  /**
   * Generates content for the webform report page.
   *
   * @param mixed $webform
   *   The webform or webform ID for which the report is generated.
   *
   * @return array
   *   An associative render array containing the generated content for the webform report page.
   */
  public function content($webform) {

    $webform_id = $webform;

    $view = $this->entityTypeManager->getStorage('view')->load('webform_report');
    $view = $this->viewExecutableFactory->get($view);
    $display_id = 'block_1';
    $view->setDisplay($display_id);
    // Add a contextual filter to the view.
    $contextual_filter_value = $webform_id;
    // Set the argument.
    $view->setArguments([$contextual_filter_value]);

    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    if (!($webform instanceof WebformInterface)) {
      // Webform not found, throw exception
      throw new NotFoundHttpException();
    }
    $fields = $webform->getElementsDecoded();
    $fields['operations'] = [
      '#title' => 'Operations',
      '#type' => 'operations',
    ];

    // Pull out nested fields
    foreach ($fields as $field_name => $field_config) {
      // Pull out nested table fields
      if ($field_config['#type'] == 'webform_table') {
        foreach ($field_config as $table_keys => $table_values) {
          if (strpos($table_keys, 'table_') !== FALSE) {
            foreach ($table_values as $table_row_key => $table_row) {
              if (is_array($table_row)) {
                $fields[$table_row_key] = $table_row;
              }
            }
          }
        }
        unset($fields[$field_name]);
        // unset and set, so it moves operations to the last key of the fields array, to make it display last.
        $operations = $fields['operations'];
        unset($fields['operations']);
        $fields['operations'] = $operations;
      }
      // Pull out nested page fields
      if ($field_config['#type'] == 'webform_wizard_page') {
        foreach ($field_config as $page_field_keys => $page_field_values) {
          if (is_array($page_field_values)) {
            $fields[$page_field_keys] = $page_field_values;
          }
        }
        unset($fields[$field_name]);
      }
    }

    foreach ($fields as $field_name => $field_config) {

      if (!empty($field_config['#type']) && $field_config['#type'] != 'webform_actions' && !empty($field_config['#title'])) {
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
        if ($field_config['#type'] == 'checkbox' || $field_config['#type'] == 'checkboxes') {
          $field_settings['webform_element_format'] = 'value';
        }
        if ($field_config['#type'] == 'date') {
          $field_settings['webform_element_format'] = 'm_n_y';
        }
        $image_fields = [
          'webform_signature',
          'webform_image',
        ];
        if (in_array($field_config['#type'], $image_fields)) {
          $field_settings['webform_element_format'] = 'image';
        }
        $view->addHandler($display_id, 'field', $table, $field, $field_settings);

        $skip_filters_for = [
          'completion_time',
          'google_sheets_url',
          'submission_pdf',
        ];
        if (in_array($field_name, $skip_filters_for)) {
          // don't want to add a filter for this field
          continue;
        }

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
          'checkboxes',
          'select'
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

    $view->preExecute();
    $view->execute();

    $view_output = $view->render();
    $webform_id = $webform->id();
    $view_count = $this->viewCountService->getViewCount($webform_id) ?? 0;
    $average_time = $this->viewCountService->getAverageTime($webform_id);
    $formatted_time = $this->viewCountService->convertSecondsToTime($average_time);

    $current_url = \Drupal::request()->getRequestUri();
    // url has the "?"
    if (strpos($current_url, '?') !== FALSE) {
      $current_url = str_replace('?', '/csv/?', $current_url);
    }
    else {
      $current_url = $current_url . '/csv';
    }

    $destination_url = Url::fromUri('internal:' . $current_url);

    return [
      'top' => [
        '#markup' => "<p>Views: $view_count</p>
            <p>Avg completion time: $formatted_time</p>",
      ],
      'button' => [
        '#type' => 'link',
        '#title' => t('Download as CSV'),
        '#url' => $destination_url,
        '#attributes' => ['class' => ['button', 'button--primary', 'button--download']],
        '#attached' => [
          'library' => [
            'coe_webform_reports/webform-admin-results',
          ],
        ],
      ],
      'body' => [
        '#type' => 'block',
        'content' => $view_output,
        '#cache' => $view->getCacheTags(),
      ],
    ];
  }

}
