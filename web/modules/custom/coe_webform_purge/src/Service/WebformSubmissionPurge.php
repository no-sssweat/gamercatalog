<?php

namespace Drupal\coe_webform_purge\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Class WebformSubmissionPurge
 *
 * Provides functionality to manage purging of webform submissions for cleanup tasks.
 *
 * This service class handles the addition of webform submission data to purge queues,
 * facilitating the removal of data from Drupal and external services (e.g., Google Sheets).
 */
class WebformSubmissionPurge {

  /**
   * The Queue Factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  public function __construct(QueueFactory $queueFactory,
                              EntityTypeManagerInterface $entity_type_manager,
                              ConfigFactoryInterface $config_factory) {
    $this->queueFactory = $queueFactory;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  public function addToDrupalPurgeQueue(WebformSubmissionInterface $webform_submission) {
    $wsid = $webform_submission->id();
    // Create object to store info
    $item = new \stdClass();
    $item->wsid = $wsid;
    $item->created = $webform_submission->created->value;
    $item->webform_id = $webform_submission->getWebform()->id();
    // Add to queue
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('webform_submission_drupal_purge');
    $queue->createItem($item);
  }

  public function addToSheetsPurgeQueue(WebformSubmissionInterface $webform_submission, $spreadsheet_id = NULL) {
    $wsid = $webform_submission->id();
    $webform_id = $webform_submission->getWebform()->id();
    $spreadsheet_id = $spreadsheet_id ?? $this->getSpreadSheetID($webform_id);
    // Create object to store info
    $item = new \stdClass();
    $item->wsid = $wsid;
    $item->created = $webform_submission->created->value;
    $item->webform_id = $webform_id;
    $item->spreadsheet_id = $spreadsheet_id;
    // Add to queue
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('webform_submission_sheets_purge');
    $queue->createItem($item);
  }

  /**
   * Removes Webform submission from Drupal DB.
   *
   * @param int $wsid
   *   The Webform Submission ID.
   *
   * @return void
   */
  public function deleteWebformSubmissionFromDrupal($wsid) {
    $webform_submission = $this->entityTypeManager
      ->getStorage('webform_submission')
      ->load($wsid);
    // Delete if it exists
    if ($webform_submission instanceof WebformSubmissionInterface) {
      $webform_submission->delete();
    }
  }

  /**
   * Checks if purging is enabled for a specific webform.
   *
   * @param string $webform_id
   *   The ID of the webform.
   *
   * @return bool
   *   TRUE if purging is enabled for the webform, FALSE otherwise.
   */
  public function needsPurging($webform_id) {
    $config = $this->getConfig($webform_id);
    return $config->get('purging_enabled');
  }

  /**
   * Gets the configuration object for a specific webform.
   *
   * @param string $webform_id
   *   The ID of the webform for which to retrieve the configuration.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   The configuration object for the specified webform.
   */
  public function getConfig($webform_id) {
    // Construct the configuration name based on the webform ID.
    $config_name = 'coe_webform_purge.' . $webform_id;

    // Retrieve and return the configuration object.
    return $this->configFactory->get($config_name);
  }

  /**
   * Retrieves the spreadsheet ID configured for a Webform with Google Sheets submissions.
   *
   * @param int $webform_id
   *   The ID of the Webform to retrieve the spreadsheet ID from.
   *
   * @return string|NULL
   *   The spreadsheet ID if configured, NULL otherwise.
   */
  public function getSpreadSheetID($webform_id) {
    $webform = \Drupal::entityTypeManager()->getStorage('webform')->load($webform_id);
    // Iterate through each handler configured for the Webform.
    foreach ($webform->getHandlers() as $handler) {
      // Check if the handler is the Google Sheets submissions handler.
      if ($handler->getPluginId() == 'googlesheets_submissions') {
        // Check if the spreadsheet_id is configured in the handler settings.
        if (!empty($handler->getConfiguration()['settings']['spreadsheet_id'])) {
          return $handler->getConfiguration()['settings']['spreadsheet_id'];
        }
      }
    }

    return NULL;
  }

}
