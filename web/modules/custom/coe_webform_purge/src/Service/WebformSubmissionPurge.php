<?php

namespace Drupal\coe_webform_purge\Service;

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

  public function __construct(QueueFactory $queueFactory) {
    $this->queueFactory = $queueFactory;
  }

  public function addToDrupalPurgeQueue(WebformSubmissionInterface $webform_submission) {
    $wsid = $webform_submission->id();
    // Create object to store info
    $item = new \stdClass();
    $item->id = $wsid;
    $item->created = $webform_submission->created->value;
    $item->webform_id = $webform_submission->getWebform()->id();
    // Add to queue
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('webform_submission_drupal_purge');
    $queue->createItem($item);
  }

  public function addToSheetsPurgeQueue(WebformSubmissionInterface $webform_submission) {
    $wsid = $webform_submission->id();
    // Create object to store info
    $item = new \stdClass();
    $item->id = $wsid;
    $item->created = $webform_submission->created->value;
    $item->webform_id = $webform_submission->getWebform()->id();
    // Add to queue
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('webform_submission_sheets_purge');
    $queue->createItem($item);
  }

}
