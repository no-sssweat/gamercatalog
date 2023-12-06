<?php

namespace Drupal\coe_webform_reports\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Queue\QueueFactory;
use Drupal\webform\WebformInterface;

/**
 * Class ViewCount
 *
 * Manages the view count for Webforms and interacts with the database.
 */
class ViewCount {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Queue Factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  public function __construct(Connection $database, QueueFactory $queueFactory) {
    $this->database = $database;
    $this->queueFactory = $queueFactory;
  }

  public function getViewCount(string $webformId) {
    $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
      ->fields('coe_rvc', ['view_count'])
      ->condition('coe_rvc.id', $webformId)
      ->execute();

    $result = $query->fetchAssoc();
    return !empty($result) ? $result['view_count'] : 0;
  }

  public function setViewCount(string $webformId, int $viewCount) {
    $this->database->merge('coe_webform_reports_view_count')
      ->key(['id' => $webformId])
      ->fields(['view_count' => $viewCount])
      ->execute();
  }

  public function onWebformCreate(WebformInterface $webform) {
    try {
      $data = [
        'id' => $webform->id(),
        'view_count' => 0,
        'type' => 'webform',
        'title' => $webform->label(),
      ];

      $this->database->insert('coe_webform_reports_view_count')
        ->fields($data)
        ->execute();
    }
    catch (DatabaseExceptionWrapper $e) {
      \Drupal::logger('coe_webform_reports')
        ->error('Database insert error: @message',
          ['@message' => $e->getMessage()]);
    }
    $this->addToQueue($webform);
  }

  public function onWebformDelete(WebformInterface $webform) {
    try {
      $this->database->delete('coe_webform_reports_view_count')
        ->condition('id', $webform->id())
        ->execute();
    }
    catch (DatabaseExceptionWrapper $e) {
      \Drupal::logger('coe_webform_reports')
        ->error('Database delete error: @message',
          ['@message' => $e->getMessage()]);
    }
  }

  public function addToQueue($webform_id, bool $run_tomorrow = FALSE) {
    // Need to move this to a service method
    $queue = $this->queueFactory->get('coe_webform_reports_view_count_queue');
    //Add to the queue.
    $item = new \stdClass();
    $item->id = $webform_id;
    $item->queued_time = time();
    $item->run_tomorrow = $run_tomorrow;
    $queue->createItem($item);
  }

}
