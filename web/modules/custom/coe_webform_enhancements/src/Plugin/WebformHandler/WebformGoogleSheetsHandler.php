<?php

namespace Drupal\coe_webform_enhancements\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\google_api_client\Entity\GoogleApiServiceClient;
use Drupal\google_api_client\Entity\GoogleApiClient;
use Exception;

/**
 * Form submission to Google Sheets handler.
 *
 * @WebformHandler(
 *   id = "googlesheets_submissions",
 *   label = @Translation("Google Sheets Submissions"),
 *   category = @Translation("Google Sheets Submissions"),
 *   description = @Translation("Append webform submissions to a Google Sheet."),
 *   cardinality = 1,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class WebformGoogleSheetsHandler extends WebformHandlerBase {

  /**
   * The webform element plugin manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * GoogleSheetsService to simplify Google API operation.
   *
   * @var \Drupal\coe_webform_enhancements\Service\GoogleSheetsService
   */
  protected $googleSheetsService;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->elementManager = $container->get('plugin.manager.webform.element');
    $instance->fileSystem = $container->get('file_system');

    // Initialize the Google API client if a Google API Client was configured.
    $googleSheetsService = $container->get('coe_webform_enhancements.google_sheets');
    if (isset($configuration['settings']['google_api_authentication_method']) && $configuration['settings']['google_api_authentication_method'] != '') {
      switch ($configuration['settings']['google_api_authentication_method']) {
        case 'google_api_service_client':
          if (isset($configuration['settings']['google_api_service_client_id']) && $configuration['settings']['google_api_service_client_id'] != '') {
            // Check that the Google API Service Account exists.
            $google_api_service_client = GoogleApiServiceClient::load($configuration['settings']['google_api_service_client_id']);
            if ($google_api_service_client) {
              $googleSheetsService->setGoogleApiServiceClient($configuration['settings']['google_api_service_client_id']);
            }
            else {
              $instance->messenger()->addError($instance->t('The Google Service Account @service_account does not exist.', ['@service_account' => $configuration['settings']['google_api_service_client_id']]));
              $instance->loggerFactory->get('coe_webform_enhancements')->error($instance->t('The Google Service Account @service_account does not exist.', ['@service_account' => $configuration['settings']['google_api_service_client_id']]));
            }
          }
          break;

        case 'google_api_client':
          if (isset($configuration['settings']['google_api_client_id']) && $configuration['settings']['google_api_client_id'] != '') {
            // Check that the Google API Client exists.
            $google_api_client = GoogleApiClient::load($configuration['settings']['google_api_client_id']);
            if ($google_api_client) {
              $googleSheetsService->setGoogleApiClient($configuration['settings']['google_api_client_id']);
            }
            else {
              $instance->messenger()->addError($instance->t('The Google API Client @api_client does not exist.', ['@api_client' => $configuration['settings']['google_api_client_id']]));
              $instance->loggerFactory->get('coe_webform_enhancements')->error($instance->t('The Google API Client @api_client does not exist.', ['@api_client' => $configuration['settings']['google_api_client_id']]));
            }
          }
          break;
      }
    }

    $instance->googleSheetsService = $googleSheetsService;


    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();

    return [
      '#settings' => $configuration['settings'],
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $field_names = array_keys(
      // The 'entity_field.manager' service can't be injected into WebformHandler, known issue.
      \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('webform_submission')
    );

    return [
      'google_api_authentication_method' => '',
      'google_api_client_id' => '',
      'google_api_service_client_id' => '',
      'spreadsheet_id' => '',
      'spreadsheet_delete_on_handler_delete' => '',
      'spreadsheet_access_user_writer' => '',
      'spreadsheet_access_revoke_writers_on_handler_delete' => '',
      'spreadsheet_access_user_reader' => '',
      'spreadsheet_access_revoke_readers_on_handler_delete' => '',
      'spreadsheet_drive_directory_id' => '',
      'spreadsheet_submission_metadata_include' => 'completed|Completed',
      'spreadsheet_column_exclude' => 'google_upload_status, submission_pdf',
      'included_data' => array_combine($field_names, $field_names),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    parent::buildConfigurationForm($form, $form_state);

    $form['googlesheets'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Google API settings'),
    ];

    $form['googlesheets']['google_api_authentication_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication Method'),
      '#options' => [
        'google_api_service_client' => $this->t('Service Account'),
        'google_api_client' => $this->t('OAuth 2.0 Client'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['google_api_authentication_method'] ?? 'google_api_service_client',
    ];

    $form['googlesheets']['google_api_client_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Google API Client'),
      '#options' => array_map(fn($client) => $client->label(), GoogleApiClient::loadMultiple()),
      '#default_value' => $this->configuration['google_api_client_id'] ?? '',
      '#empty_value' => '',
      '#states' => [
        'visible' => [
          ':input[name="settings[google_api_authentication_method]"]' => ['value' => 'google_api_client'],
        ],
        'required' => [
          ':input[name="settings[google_api_authentication_method]"]' => ['value' => 'google_api_client'],
        ],
      ],
    ];

    $form['googlesheets']['google_api_service_client_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Google API Service Account'),
      '#options' => array_map(fn($client) => $client->label(), GoogleApiServiceClient::loadMultiple()),
      '#default_value' => $this->configuration['google_api_service_client_id'] ?? '',
      '#empty_value' => '',
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="settings[google_api_authentication_method]"]' => ['value' => 'google_api_service_client'],
        ],
        'required' => [
          ':input[name="settings[google_api_authentication_method]"]' => ['value' => 'google_api_service_client'],
        ],
      ],
    ];

    $form['googlesheets']['spreadsheet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Spreadsheet ID'),
      '#description' => $this->t('If blank a new Google Spreadsheet will be created and this field will be updated with the Id.'),
      '#default_value' => $this->configuration['spreadsheet_id'] ?? '',
      '#empty_value' => '',
      '#disabled' => TRUE,
    ];

    $form['googlesheets']['spreadsheet_delete_on_handler_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Spreadsheet when Handler is deleted'),
      '#description' => $this->t('If checked the Google Drive subdirectory, spreadsheet, and submission file attachments associated with this webform will be deleted when this handler is deleted.'),
      '#default_value' => $this->configuration['spreadsheet_delete_on_handler_delete'] ?? FALSE,
    ];

    $form['googlesheets']['spreadsheet_access_user_writer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Spreadsheet Writer Access'),
      '#description' => $this->t('Comma seperated list of email addresses that are granted writer access to this webform\'s Google Drive subdirectory, spreadsheet, and submission file attachments.'),
      '#default_value' => $this->configuration['spreadsheet_access_user_writer'] ?? '',
      '#empty_value' => '',
    ];

    $form['googlesheets']['spreadsheet_access_revoke_writers_on_handler_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Revoke Spreadsheet Writer Access when Handler is deleted'),
      '#description' => $this->t('If checked the writers listed above will have their access revoked when this handler is deleted.'),
      '#default_value' => $this->configuration['spreadsheet_access_revoke_writers_on_handler_delete'] ?? FALSE,
    ];

    $form['googlesheets']['spreadsheet_access_user_reader'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Spreadsheet Reader Access'),
      '#description' => $this->t('Comma seperated list of email addresses that are granted reader access to this webform\'s Google Drive subdirectory, spreadsheet, and submission file attachments.'),
      '#default_value' => $this->configuration['spreadsheet_access_user_reader'] ?? '',
      '#empty_value' => '',
    ];

    $form['googlesheets']['spreadsheet_access_revoke_readers_on_handler_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Revoke Spreadsheet Readers Access when Handler is deleted'),
      '#description' => $this->t('If checked the readers listed above will have their access revoked when this handler is deleted.'),
      '#default_value' => $this->configuration['spreadsheet_access_revoke_readers_on_handler_delete'] ?? FALSE,
    ];

    // This needs just one parent directory ID field and a checkbox to create a subdirectory for each submission.
    $form['googlesheets']['spreadsheet_drive_directory_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Drive Directory ID'),
      '#description' => $this->t('Specify a directory ID on Google Drive to use for webform storage, a subdirectory will be created to store the spreadsheet and submission file attachments for this webform. If this directory is not owned by the Google API Service Account or Google API Client, the user specified in the Google API Service Account or Google API Client must grant the Google API Service Account or Google API Client access to this directory.'),
      '#default_value' => $this->configuration['spreadsheet_drive_directory_id'] ?? '',
      '#required' => TRUE,
    ];

    // Include metadata keys from submission as columns. One per line as key|name.
    $form['googlesheets']['spreadsheet_submission_metadata_include'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Include Submission Metadata Columns'),
      '#description' => $this->t('List of metadata field data to include as columns, possible keys: serial, sid, uuid, token, uri, created, completed, changed, in_draft, current_page, remote_addr, uid, langcode, webform_id, entity_type, entity_id, locked, sticky, notes, metatag. <br>Enter one per line as key|name, for example: <br>remote_addr|User IP Address'),
      '#default_value' => $this->configuration['spreadsheet_submission_metadata_include'] ?? '',
      '#empty_value' => '',
    ];

    // Comma seperated list of named ranges to exclude as columns.
    $form['googlesheets']['spreadsheet_column_exclude'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude Columns'),
      '#description' => $this->t('Comma seperated list of column named ranges that should not be included in the spreadsheet.'),
      '#default_value' => $this->configuration['spreadsheet_column_exclude'] ?? '',
      '#empty_value' => '',
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    // Initialize the Google API client so it can be used to validate the Google Drive directory ID.
    $google_api_authentication_method = $form_state->getValue('google_api_authentication_method');
    if (isset($google_api_authentication_method) && $google_api_authentication_method != '') {
      switch ($google_api_authentication_method) {
        case 'google_api_service_client':
          $google_api_service_client_id = $form_state->getValue('google_api_service_client_id');
          if ($google_api_service_client_id && $google_api_service_client_id != '') {
            $client_id = $google_api_service_client_id;
            $this->googleSheetsService->setGoogleApiServiceClient($client_id);
          }
          break;

        case 'google_api_client':
          $google_api_client_id = $form_state->getValue('google_api_client_id');
          if ($google_api_client_id && $google_api_client_id != '') {
            $client_id = $google_api_client_id;
            $this->googleSheetsService->setGoogleApiClient($client_id);
          }
          break;
      }
    }

    // If a Google API Client was configured, validate the Google Drive directory ID.
    if (isset($client_id) && $client_id != '') {
      // Validate the Google Drive directory ID.
      $spreadsheet_drive_directory_id = $form_state->getValue('spreadsheet_drive_directory_id');
      if (!$this->googleSheetsService->checkDirectoryExists($spreadsheet_drive_directory_id)) {
        $form_state->setErrorByName('spreadsheet_drive_directory_id', $this->t('The Google Drive Directory ID is not valid.'));
      }
    }

    // Validate spreadsheet_submission_metadata_include is in the correct format.
    $spreadsheet_submission_metadata_include = $form_state->getValue('spreadsheet_submission_metadata_include');
    if (!empty($spreadsheet_submission_metadata_include)) {
      $metadata_keys = array_filter(array_map('trim', explode("\n", $spreadsheet_submission_metadata_include)));
      $metadata_keys = array_filter(array_map(fn($metadata_key) => explode('|', $metadata_key), $metadata_keys));
      $metadata_keys = array_combine(array_column($metadata_keys, 0), array_column($metadata_keys, 1));

      if (empty($metadata_keys)) {
        $form_state->setErrorByName('spreadsheet_submission_metadata_include', $this->t('The metadata keys must be in the format key|name.'));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Get the old configuration before it is updated.
    $old_configuration = $this->getConfiguration();

    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);

    $webform = $this->getWebform();

    // Get updated settings for this handler.
    $handler_settings = $this->getSettings();
    $spreadsheet_id = $handler_settings['spreadsheet_id'];

    if ($spreadsheet_id) {
      // Previous user access settings for this handler.
      $old_spreadsheet_access_user_writer = $old_configuration['settings']['spreadsheet_access_user_writer']
        ? array_map(fn ($email) => trim($email), explode(',', $old_configuration['settings']['spreadsheet_access_user_writer'])) : [];
      $old_spreadsheet_access_user_reader = $old_configuration['settings']['spreadsheet_access_user_reader']
        ? array_map(fn ($email) => trim($email), explode(',', $old_configuration['settings']['spreadsheet_access_user_reader'])) : [];

      // Current user access settings for this handler.
      $spreadsheet_access_user_writer = $handler_settings['spreadsheet_access_user_writer']
        ? array_map(fn ($email) => trim($email), explode(',', $handler_settings['spreadsheet_access_user_writer'])) : [];
      $spreadsheet_access_user_reader = $handler_settings['spreadsheet_access_user_reader']
        ? array_map(fn ($email) => trim($email), explode(',', $handler_settings['spreadsheet_access_user_reader'])) : [];

      // Get the id for this webform's subdirectory.
      $webform_subdirectory_id = $this->googleSheetsService->getFolderId($webform->id(), $handler_settings['spreadsheet_drive_directory_id']);

      $drive_permissions = $this->googleSheetsService->getDrivePermissions($webform_subdirectory_id);
      $drive_permissions_writers = [];
      $drive_permissions_readers = [];

      foreach ($drive_permissions as $drive_permission) {
        if ($drive_permission->getRole() == 'writer') {
          $drive_permissions_writers[] = $drive_permission->getEmailAddress();
        }
        elseif ($drive_permission->getRole() == 'reader') {
          $drive_permissions_readers[] = $drive_permission->getEmailAddress();
        }
      }

      // Grant write access to the users listed in this handler's settings.
      $add_writers = array_diff($spreadsheet_access_user_writer, $drive_permissions_writers);
      if (!empty($add_writers)) {
        $add_writer_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
          // Grant access to this webform's Google drive subdirectory and its descendants.
          return $this->googleSheetsService->createPermission($webform_subdirectory_id, [
            'type' => 'user',
            'role' => 'writer',
            'emailAddress' => trim($email),
          ]);
        }, $add_writers);
      }

      // Revoke write access to users that are no longer listed in this handler's settings.
      $remove_writers = array_diff($old_spreadsheet_access_user_writer, $spreadsheet_access_user_writer);
      if (!empty($remove_writers)) {
        $remove_writer_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
          return $this->googleSheetsService->deletePermissionByEmail($webform_subdirectory_id, $email, 'writer');
        }, $remove_writers);
      }

      // Grant read access to the users listed in this handler's settings.
      $add_readers = array_diff($spreadsheet_access_user_reader, $drive_permissions_readers);
      if (!empty($add_readers)) {
        $add_reader_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
          return $this->googleSheetsService->createPermission($webform_subdirectory_id, [
            'type' => 'user',
            'role' => 'reader',
            'emailAddress' => trim($email),
          ]);
        }, $add_readers);
      }

      // Revoke read access to this spreadsheet.
      $remove_readers = array_diff($old_spreadsheet_access_user_reader, $spreadsheet_access_user_reader);
      if (!empty($remove_readers)) {
        $remove_reader_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
          return $this->googleSheetsService->deletePermissionByEmail($webform_subdirectory_id, $email, 'reader');
        }, $remove_readers);
      }

      // Check if the spreadsheet_drive_directory_id has been changed.
      if (isset($old_configuration['settings']['spreadsheet_drive_directory_id']) && $old_configuration['settings']['spreadsheet_drive_directory_id'] !== $handler_settings['spreadsheet_drive_directory_id']) {
        // Move the subdirectory from the old directory to the new directory.
        $this->googleSheetsService->moveSubdirectory(
          $old_configuration['settings']['spreadsheet_drive_directory_id'],
          $handler_settings['spreadsheet_drive_directory_id'],
          $webform->id()
        );
      }

      // Update the Google spreadsheet columns.
      $current_columns = $this->googleSheetsService->getNamedRanges($spreadsheet_id);

      // Get the elements of the webform.
      $webform_elements = getWebformElementsArray($webform, TRUE);

      // Get the list of metadata keys that are included as columns.
      $spreadsheet_submission_metadata_include = $handler_settings['spreadsheet_submission_metadata_include'];
      $metadata_columns = array_filter(array_map('trim', explode("\n", $spreadsheet_submission_metadata_include)));
      $metadata_columns = array_filter(array_map(fn($metadata_key) => explode('|', $metadata_key), $metadata_columns));
      $metadata_columns = array_combine(array_column($metadata_columns, 0), array_column($metadata_columns, 1));
      // Prefix every key with "metadata_".
      $metadata_columns = array_combine(array_map(fn($key) => "metadata_{$key}", array_keys($metadata_columns)), $metadata_columns);

      // Exclude configured columns.
      $spreadsheet_column_exclude = array_filter(array_map('trim', explode(',', $handler_settings['spreadsheet_column_exclude'])));

      // The final array of columns that should exist in the spreadsheet.
      $spreadsheet_columns = array_merge($webform_elements, $metadata_columns);
      $spreadsheet_columns = array_diff_key($spreadsheet_columns, array_flip($spreadsheet_column_exclude));

      // Add columns that don't exist in the spreadsheet.
      foreach ($spreadsheet_columns as $spreadsheet_column_key => $spreadsheet_column_value) {
        if (!in_array($spreadsheet_column_key, $current_columns)) {
          // Add column to the spreadsheet.
          $header_cell = [
            'column' => count($current_columns),
            'row' => 1,
            'value' => $spreadsheet_column_value,
          ];

          // Update the first row of the column with the element name.
          $this->googleSheetsService->updateCellValues($spreadsheet_id, [$header_cell]);

          // Add a named range for the column.
          $this->googleSheetsService->addColumnNamedRange($spreadsheet_id, $spreadsheet_column_key, count($current_columns));

          // Get the current columns in the spreadsheet.
          $current_columns = $this->googleSheetsService->getNamedRanges($spreadsheet_id);
        }
      }

      // Remove columns that should no longer exist in the spreadsheet.
      foreach ($current_columns as $column) {
        if (!array_key_exists($column, $spreadsheet_columns)) {
          // Remove column from the spreadsheet.
          // Get the range from the named range of the column.
          $column_index = $this->googleSheetsService->getNamedRangeColumnIndex($spreadsheet_id, $column);

          // Delete the named range.
          $named_range_id = $this->googleSheetsService->getNamedRangeId($spreadsheet_id, $column);
          $this->googleSheetsService->removeNamedRange($spreadsheet_id, $named_range_id);

          // Delete the column.
          $this->googleSheetsService->removeColumn($spreadsheet_id, $column_index);

          // Get the current columns in the spreadsheet.
          $current_columns = $this->googleSheetsService->getNamedRanges($spreadsheet_id);
        }
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function createHandler() {
    $webform = $this->getWebform();
    $handler = $webform->getHandler($this->getHandlerId());
    $configuration = $handler->getConfiguration();

    // Create a new spreadsheet and store the spreadsheet_id if it wasn't provided.
    if (empty($configuration['settings']['spreadsheet_id'])) {
      $spreadsheet = $this->createNewSpreadsheet($webform);

      // Update this handler's config with the new spreadsheet ID.
      $configuration['settings']['spreadsheet_id'] = $spreadsheet->getSpreadsheetId();
      $handler->setConfiguration($configuration);
      $webform->save();
    }
    else {
      // Load the spreadsheet if a spreadsheet_id was provided.
      $spreadsheet = $this->googleSheetsService->getSpreadsheet($configuration['settings']['spreadsheet_id']);
    }

    // Create a subdirectory in the specified directory and move the spreadsheet to that subdirectory.
    if (!empty($configuration['settings']['spreadsheet_drive_directory_id'])) {

      // Create a subdirectory for the webform.
      $webform_subdirectory_id = $this->googleSheetsService->getFolderId($webform->id(), $configuration['settings']['spreadsheet_drive_directory_id']);
      if (empty($webform_subdirectory_id)) {
        $webform_subdirectory_id = $this->googleSheetsService->createSubdirectory($configuration['settings']['spreadsheet_drive_directory_id'], $webform->id());
      }

      // Create a subdirectory for the submissions in the subdirectory for the webform.
      $submissions_subdirectory_id = $this->googleSheetsService->getFolderId('submissions', $webform_subdirectory_id);
      if (empty($submissions_subdirectory_id)) {
        $submissions_subdirectory_id = $this->googleSheetsService->createSubdirectory($webform_subdirectory_id, 'submissions');
      }

      $this->googleSheetsService->moveSpreadsheetToDirectory($spreadsheet->getSpreadsheetId(), $webform_subdirectory_id);

      // Grant write access to this webform's subdirectory.
      if (!empty($configuration['settings']['spreadsheet_access_user_writer'])) {
        $write_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
          return $this->googleSheetsService->createPermission($webform_subdirectory_id, [
            'type' => 'user',
            'role' => 'writer',
            'emailAddress' => trim($email),
          ]);
        }, explode(',', $configuration['settings']['spreadsheet_access_user_writer']));
      }

      // Grant read access to this spreadsheet.
      if (!empty($configuration['settings']['spreadsheet_access_user_reader'])) {
        $write_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
          return $this->googleSheetsService->createPermission($webform_subdirectory_id, [
            'type' => 'user',
            'role' => 'reader',
            'emailAddress' => trim($email),
          ]);
        }, explode(',', $configuration['settings']['spreadsheet_access_user_reader']));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteHandler() {
    $webform = $this->getWebform();
    $configuration = $this->getConfiguration();
    $spreadsheet_id = $configuration['settings']['spreadsheet_id'];
    $webform_subdirectory_id = $this->googleSheetsService->getFolderId($webform->id(), $configuration['settings']['spreadsheet_drive_directory_id']);

    if ($webform_subdirectory_id) {
      $drive_permissions = $this->googleSheetsService->getDrivePermissions($webform_subdirectory_id);

      // Revoke write access from the users listed in this handler's settings.
      if ($configuration['settings']['spreadsheet_access_revoke_writers_on_handler_delete']) {
        $spreadsheet_access_user_writer = $configuration['settings']['spreadsheet_access_user_writer']
          ? array_map(fn ($email) => trim($email), explode(',', $configuration['settings']['spreadsheet_access_user_writer'])) : [];

        $drive_permissions_writers = [];

        foreach ($drive_permissions as $drive_permission) {
          if ($drive_permission->getRole() == 'writer') {
            $drive_permissions_writers[] = $drive_permission->getEmailAddress();
          }
        }

        // Revoke write access to this spreadsheet.
        $remove_writers = array_intersect($spreadsheet_access_user_writer, $drive_permissions_writers);
        if (!empty($remove_writers)) {
          $remove_writer_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
            return $this->googleSheetsService->deletePermissionByEmail($webform_subdirectory_id, $email, 'writer');
          }, $remove_writers);
        }
      }

      // Revoke read access from the users listed in this handler's settings.
      if ($configuration['settings']['spreadsheet_access_revoke_readers_on_handler_delete']) {
        // Remove the handler's permissions from the spreadsheet.
        $spreadsheet_access_user_reader = $configuration['settings']['spreadsheet_access_user_reader']
          ? array_map(fn ($email) => trim($email), explode(',', $configuration['settings']['spreadsheet_access_user_reader'])) : [];

        $drive_permissions_readers = [];

        foreach ($drive_permissions as $drive_permission) {
          if ($drive_permission->getRole() == 'reader') {
            $drive_permissions_readers[] = $drive_permission->getEmailAddress();
          }
        }
        // Revoke read access to this spreadsheet.
        $remove_readers = array_intersect($spreadsheet_access_user_reader, $drive_permissions_readers);
        if (!empty($remove_readers)) {
          $remove_reader_permissions = array_map(function ($email) use ($webform_subdirectory_id) {
            return $this->googleSheetsService->deletePermissionByEmail($webform_subdirectory_id, $email, 'reader');
          }, $remove_readers);
        }
      }

      // Delete the spreadsheet if configured to do so.
      if ($configuration['settings']['spreadsheet_delete_on_handler_delete']) {
        // Get the subdirectory that was created for this webform.
        $webform_subdirectory_id = $this->googleSheetsService->getFolderId($webform->id(), $configuration['settings']['spreadsheet_drive_directory_id']);

        // Delete the subdirectory for this webform.
        $this->googleSheetsService->deleteFile($webform_subdirectory_id);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    try {
      $webform = $webform_submission->getWebform();
      $configuration = $this->getConfiguration();
      $spreadsheet_id = $configuration['settings']['spreadsheet_id'];

      // Set the Google Upload Status to FALSE before the submission is processed.
      $webform_submission->setElementData('google_upload_status', FALSE);

      $processed_submission_data = $this->processWebformSubmissionData($webform_submission);

      // Associative array of field names and values from submission data.
      $submission_elements_array = $this->getWebformSubmissionArray($processed_submission_data, TRUE);

      $submission_fields_array = $this->getWebformSubmissionFieldsArray($webform_submission);

      // Merge the submission data and metadata arrays.
      $submission_data_array = array_merge($submission_elements_array, $submission_fields_array);

      // Attach the submission ID as metadata to the row.
      $submission_id = $webform_submission->id();
      $metadata = $submission_id ? ['submission_id' => $submission_id] : [];

      // Map the submission data to the spreadsheet named range columns.
      $append_values_response = $this->googleSheetsService->appendNamedRangeValues($spreadsheet_id, $submission_data_array, $metadata);

      // Set upload status element to TRUE and delete the submission data if upload was successful.
      if ($append_values_response != NULL) {
        // Delete the submission file attachments after submission has been stored on Google.
        $this->deleteWebformSubmissionFiles($webform_submission);

        // Delete the submission data after submission has been stored on Google.
        $this->deleteWebformSubmissionData($webform_submission);

        // Mark this submission was successfully uploaded to Google.
        $webform_submission->setElementData('google_upload_status', TRUE);
        $webform_submission->resave();
      }
    }
    catch (Exception $e) {
      $this->messenger()->addError('There was an error processing this submission.');
      $this->loggerFactory->get('coe_webform_enhancements')->error($e->getMessage());

      // Error saving submission on Google drive, remove orphaned file attachments from Google Drive.
      $webform_directory_id = $this->googleSheetsService->getFolderId($webform->id(), $configuration['settings']['spreadsheet_drive_directory_id']);
      $webform_submissions_directory_id = $this->googleSheetsService->getFolderId('submissions', $webform_directory_id);
      $submission_directory_id = $this->googleSheetsService->getFolderId($submission_id, $webform_submissions_directory_id);
      $this->googleSheetsService->deleteFile($submission_directory_id);

      // There was an error processing the submission data to Drupal, so delete the entire submission.
      //$webform_submission->delete();
    }
  }

  /**
   * Creates a new Google Sheets spreadsheet with the given title and columns.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform entity.
   *
   * @return \Google\Service\Sheets\Spreadsheet
   *   The newly created spreadsheet.
   */
  public function createNewSpreadsheet($webform) {
    $configuration = $this->getConfiguration();

    $spreadsheet_title = "{$webform->label()} Webform Submissions";
    $spreadsheet_columns = $this->getWebformElementsArray($webform, TRUE);

    // Include configured metadata keys from submission as columns.
    if (!empty($configuration['settings']['spreadsheet_submission_metadata_include'])) {
      $spreadsheet_submission_metadata_include = $configuration['settings']['spreadsheet_submission_metadata_include'];
      $metadata_columns = array_filter(array_map('trim', explode("\n", $spreadsheet_submission_metadata_include)));
      $metadata_columns = array_filter(array_map(fn($metadata_key) => explode('|', $metadata_key), $metadata_columns));
      $metadata_columns = array_combine(array_column($metadata_columns, 0), array_column($metadata_columns, 1));
      // Prefix every key with "metadata_".
      $metadata_columns = array_combine(array_map(fn($key) => "metadata_{$key}", array_keys($metadata_columns)), $metadata_columns);

      $spreadsheet_columns = array_merge($spreadsheet_columns, $metadata_columns);
    }

    // Exclude configured columns.
    if (!empty($configuration['settings']['spreadsheet_column_exclude'])) {
      $spreadsheet_column_exclude = array_filter(array_map('trim', explode(',', $configuration['settings']['spreadsheet_column_exclude'])));

      $spreadsheet_columns = array_diff_key($spreadsheet_columns, array_flip($spreadsheet_column_exclude));
    }

    $spreadsheet = $this->googleSheetsService->addSpreadsheet([
      'title' => $spreadsheet_title,
    ]);

    // Add row of column names to the spreadsheet and define a NamedRange for each column.
    if (!empty($spreadsheet_columns)) {
      $column_names = array_keys($spreadsheet_columns);
      $column_values = array_values($spreadsheet_columns);

      // Add more columns if there aren't enough.
      $column_count_difference = count($spreadsheet_columns) - $this->googleSheetsService->getColumnCount($spreadsheet->getSpreadsheetId());
      if ($column_count_difference > 0) {
        for ($i = 0; $i < $column_count_difference; $i++) {
          $new_column = $this->googleSheetsService->addColumn($spreadsheet->getSpreadsheetId());
        }
      }

      // Add the header row.
      $this->googleSheetsService->appendRow($spreadsheet->getSpreadsheetId(), $column_values);

      // And add a named range for each column.
      foreach ($column_names as $column_index => $column_name) {
        $this->googleSheetsService->addColumnNamedRange($spreadsheet->getSpreadsheetId(), $column_name, $column_index);
      }

      // Freeze the first row.
      $this->googleSheetsService->freezeFirstRow($spreadsheet->getSpreadsheetId());
    }

    return $spreadsheet;
  }

  /**
   * Gets an associative array of webform elements, optionally flattened.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform entity.
   * @param bool $flatten
   *   (Optional) Whether to flatten the column names.
   *
   * @return array
   *   An associative array of 'element_id' => 'element_name'.
   */
  public function getWebformElementsArray($webform, $flatten = FALSE) {
    $webform_elements = $webform->getElementsInitializedFlattenedAndHasValue();

    $webform_elements_array = [];
    foreach ($webform_elements as $webform_element_id => $webform_element) {
      if ($webform_element['#webform_composite']) {
        foreach ($webform_element['#webform_composite_elements'] as $webform_element_composite_element_id => $webform_element_composite_element) {
          $webform_element_composite_id = "{$webform_element_id}__{$webform_element_composite_element_id}";
          $webform_elements_array[$webform_element_id][$webform_element_composite_id] = $webform_element_composite_element['#title'];
        }
      }
      else {
        $webform_elements_array[$webform_element_id] = $webform_element['#title'];
      }
    }

    return $flatten ? $this->flattenArray($webform_elements_array) : $webform_elements_array;
  }

  /**
   * Process file attachments in submission data.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *  The webform submission entity.
   *
   * @return array
   *  The submission data with file attachments processed.
   */
  public function processWebformSubmissionData(WebformSubmissionInterface $webform_submission) {
    $submission_data = $webform_submission->getData();

    foreach ($submission_data as $element_key => $element_value) {
      $element = $webform_submission->getWebform()->getElement($element_key);

      // Check if the element is a file.
      $file_field_types = ['managed_file', 'webform_audio_file', 'webform_document_file', 'webform_image_file', 'webform_video_file', 'webform_signature'];
      if ($element && in_array($element['#type'], $file_field_types)) {
        // Upload file attachments to Google Drive and record the URL in the submission data.
        $file_name = NULL;
        $file_path = NULL;

        if ($element['#type'] == 'webform_signature') {
          if ($element_value != '') {
            // Decode the base64 signature
            $file_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $element_value));
            // Save the file
            $file_path = tempnam(sys_get_temp_dir(), 'sig');
            file_put_contents($file_path, $file_data);
            $file_name = $element_key . '.png';
          }
        }
        elseif (isset($element_value) && $element_value != '') {
          // Load the file.
          $file = File::load($element_value);
          if ($file) {
            $file_path = $this->fileSystem->realpath($file->getFileUri());
            $file_name = $file->getFilename();
          }
        }

        if ($file_name && $file_path) {
          //$spreadsheet_drive_attachment_directory_id = $this->getConfiguration()['settings']['spreadsheet_drive_attachment_directory_id'];
          $spreadsheet_drive_id = $this->getConfiguration()['settings']['spreadsheet_drive_directory_id'];

          // Webform subdirectory id.
          $webform_subdirectory_id = $this->googleSheetsService->getFolderId($webform_submission->getWebform()->id(), $spreadsheet_drive_id);

          // Submissions subdirectory id.
          $webform_submissions_subdirectory_id = $this->googleSheetsService->getFolderId('submissions', $webform_subdirectory_id);

          // Create submision subdirectory.
          $submission_subdirectory_id = $this->googleSheetsService->getFolderId($webform_submission->id(), $webform_submissions_subdirectory_id);
          if (empty($submission_subdirectory_id)) {
            $submission_subdirectory_id = $this->googleSheetsService->createSubdirectory($webform_submissions_subdirectory_id, $webform_submission->id());
          }

          // Use the Google Drive API to upload the file to Google Drive.
          $drive_file = $this->googleSheetsService->uploadFile($file_path, $file_name, $submission_subdirectory_id);

          // Store the URL to the file on Google Drive in the submission data.
          if ($drive_file) {
            $file_url = $drive_file->getWebViewLink();
            $submission_data[$element_key] = $file_url;
          }
          else {
            throw new Exception("Failed to upload file {$file_name} to Google Drive.");
          }
        }
      }

      // Check if the element is a checkbox element.
      if ($element && $element['#type'] == 'checkbox') {
        $submission_data[$element_key] = $element_value ? 'Yes' : 'No';
      }

      // Check if the element is a single select type i.e. select, radios, webform_select_other, webform_radios_other.
      $single_select_field_types = ['select', 'radios', 'webform_select_other', 'webform_radios_other'];
      if ($element && in_array($element['#type'], $single_select_field_types)) {
        // Use the selected option value or the element value if the option is not found.
        $selected_option = isset($element['#options'][$element_value]) ? $element['#options'][$element_value] : $element_value;

        // Store the selected option in the submission data.
        $submission_data[$element_key] = $selected_option;
      }

      // Check if the element is a multi select type i.e. checkboxex, webform_checkboxes_other.
      $multi_select_field_types = ['checkboxes', 'webform_checkboxes_other', 'tableselect', 'webform_tableselect_sort', 'webform_table_sort'];
      if ($element && in_array($element['#type'], $multi_select_field_types)) {
        $selected_options_values = [];
        foreach ($element_value as $selected_option_value) {
          // Use the selected option value or the element value if the option is not found.
          $selected_option = isset($element['#options'][$selected_option_value]) ? $element['#options'][$selected_option_value] : $selected_option_value;
          $selected_options_values[] = $selected_option;
        }

        // Store the selected options in the submission data.
        $submission_data[$element_key] = implode(', ', $selected_options_values);
      }

    }

    return $submission_data;
  }

  /**
   * Gets an associative array of webform submission data, optionally flattened.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform entity.
   * @param bool $flatten
   *   (Optional) Whether to flatten the column names.
   *
   * @return array
   *   An associative array of 'submission_data_id' => 'submission_data_value'.
   */
  public function getWebformSubmissionArray($submission_data, $flatten = FALSE) {
    $submission_data_array = [];
    foreach ($submission_data as $submission_data_id => $submission_data_value) {
      if (is_array($submission_data_value)) {
        foreach ($submission_data_value as $submission_data_value_id => $submission_data_value_value) {
          $submission_data_composite_id = "{$submission_data_id}__{$submission_data_value_id}";
          $submission_data_array[$submission_data_id][$submission_data_composite_id] = $submission_data_value_value;
        }
      }
      else {
        $submission_data_array[$submission_data_id] = $submission_data_value;
      }
    }

    return $flatten ? $this->flattenArray($submission_data_array) : $submission_data_array;
  }

  /**
   * Gets an associative array of webform submission metadata fields.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   *
   * @return array
   *   An associative array of 'metadata_key' => 'metadata_value'.
   */
  public function getWebformSubmissionFieldsArray($webform_submission) {
    $configuration = $this->getConfiguration();

    // Webform submission metadata fields.
    $submission_fields = $webform_submission->getFields(TRUE);

    $sumbission_metadata_array = [];

    // Include the configured metadata from webfrom_submission values.
    $spreadsheet_submission_metadata_include = $configuration['settings']['spreadsheet_submission_metadata_include'];
    $metadata_keys = array_filter(array_map('trim', explode("\n", $spreadsheet_submission_metadata_include)));
    $metadata_keys = array_filter(array_map(fn($metadata_key) => explode('|', $metadata_key), $metadata_keys));
    $metadata_keys = array_combine(array_column($metadata_keys, 0), array_column($metadata_keys, 1));
    $metadata_keys = array_keys($metadata_keys);

    foreach ($metadata_keys as $metadata_key) {
      /** @var \Drupal\Core\Field\FieldItemListInterface */
      $webform_metadata_field = $submission_fields[$metadata_key];
      $webform_metadata_value = $webform_metadata_field->getValue()[0]['value'];

      // Convert created, completed, changed timestamps to date strings.
      if (in_array($metadata_key, ['created', 'completed', 'changed'])) {
        $webform_metadata_value = date('Y-m-d H:i', $webform_metadata_value);
      }
      elseif (in_array($metadata_key, ['webform_id'])) {
        $webform_metadata_value = $webform_metadata_field->getValue()[0]['target_id'];
      }

      $sumbission_metadata_array["metadata_{$metadata_key}"] = $webform_metadata_value;
    }

    return $sumbission_metadata_array;
  }

  /**
   * Delete all data from a webform_submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   *
   * @return void
   */
  public function deleteWebformSubmissionData($webform_submission) {
    $submission_data = $webform_submission->getData();

    $exclude_elements = [
      'google_upload_status',
      'completion_time',
      'submission_pdf',
    ];

    foreach ($submission_data as $element_key => $element_value) {
      if (!in_array($element_key, $exclude_elements)) {
        $webform_submission->setElementData($element_key, NULL);
      }
    }

    $webform_submission->resave();
  }

  /**
   * Delete all files attached to a webformsubmission from local Drupal storage.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   *
   * @return void
   */
  public function deleteWebformSubmissionFiles($webform_submission) {
    $submission_data = $webform_submission->getData();

    foreach ($submission_data as $element_key => $element_value) {
      $element = $webform_submission->getWebform()->getElement($element_key);

      $file_field_types = ['managed_file', 'webform_audio_file', 'webform_document_file', 'webform_image_file', 'webform_video_file', 'webform_signature'];
      if ($element && in_array($element['#type'], $file_field_types) && isset($element_value) && $element_value != '') {
        $file = File::load($element_value);

        if ($file) {
          $file->delete();
        }
      }
    }
  }

  /**
   * Flattens a multi-dimensional array into a single-dimensional array.
   *
   * @param array $array
   *   The multi-dimensional array to flatten.
   *
   * @return array
   *   The flattened array.
   */
  public function flattenArray($array) {
    $flat_array = [];

    array_walk_recursive($array, function ($value, $key) use (&$flat_array) {
      $flat_array[$key] = $value;
    });

    return $flat_array;
  }

}
