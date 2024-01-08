<?php

namespace Drupal\coe_webform_reports\Service;

use Drupal\Core\Cache\CacheTagsInvalidator;
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

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidator
   */
  protected CacheTagsInvalidator $cacheTagsInvalidator;

  /**
   * Constructs the ViewCount object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The Queue Factory.
   * @param \Drupal\Core\Cache\CacheTagsInvalidator $cacheTagsInvalidator
   *   The cache tags invalidator.
   */
  public function __construct(Connection $database, QueueFactory $queueFactory, CacheTagsInvalidator $cacheTagsInvalidator) {
    $this->database = $database;
    $this->queueFactory = $queueFactory;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  public function getViewCount(string $webformId) {
    $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
      ->fields('coe_rvc', ['view_count'])
      ->condition('coe_rvc.id', $webformId)
      ->execute();

    $result = $query->fetchAssoc();
    if ($result) {
      return $result['view_count'];
    }
  }

  public function setViewCount(string $webformId, int $viewCount) {
    $this->database->merge('coe_webform_reports_view_count')
      ->key(['id' => $webformId])
      ->fields(['view_count' => $viewCount])
      ->execute();
  }

  public function onWebformCreate(WebformInterface $webform) {
    $webform_id = $webform->id();
    try {
      $data = [
        'id' => $webform_id,
        'view_count' => 0,
        'type' => 'webform',
        'title' => $webform->label(),
      ];

      $this->database->merge('coe_webform_reports_view_count')
        ->key(['id' => $webform_id])
        ->fields($data)
        ->execute();
    }
    catch (DatabaseExceptionWrapper $e) {
      \Drupal::logger('coe_webform_reports')
        ->error('Database insert error: @message',
          ['@message' => $e->getMessage()]);
    }
    $this->addToQueue($webform->id());
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

  public function increaseTotalSubmissions(string $webformId) {
    $total_submissions = $this->getTotalSubmissions($webformId);
    $total_submissions = $total_submissions + 1;
    $this->setTotalSubmissions($webformId, $total_submissions);
  }

  public function decreaseTotalSubmissions(string $webformId) {
    $total_submissions = $this->getTotalSubmissions($webformId);
    $total_submissions = $total_submissions - 1;
    $this->setTotalSubmissions($webformId, $total_submissions);
  }

  public function increaseTotalTime(string $webformId, $completionTime) {
    $total_time = $this->getTotalTime($webformId);
    $total_time = $total_time + $completionTime;
    $this->setTotalTime($webformId, $total_time);
  }

  public function decreaseTotalTime(string $webformId, $completionTime) {
    $total_time = $this->getTotalTime($webformId);
    $total_time = $total_time - $completionTime;
    $this->setTotalTime($webformId, $total_time);
  }

  public function updateAverageTime(string $webformId) {
    $total_time = $this->getTotalTime($webformId);
    $total_submissions = $this->getTotalSubmissions($webformId);
    if ($total_submissions > 0) {
      $average_time = $total_time / $total_submissions;
      $this->setAverageTime($webformId, $average_time);
    }
  }

  public function getTotalTime(string $webformId) {
    $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
      ->fields('coe_rvc', ['total_time'])
      ->condition('coe_rvc.id', $webformId)
      ->execute();

    $result = $query->fetchAssoc();
    if ($result) {
      return $result['total_time'];
    }
  }

  public function setTotalTime($webformId, $totalTime) {
    $this->database->merge('coe_webform_reports_view_count')
      ->key(['id' => $webformId])
      ->fields(['total_time' => $totalTime])
      ->execute();
  }

  public function getTotalSubmissions(string $webformId) {
    $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
      ->fields('coe_rvc', ['total_submissions'])
      ->condition('coe_rvc.id', $webformId)
      ->execute();

    $result = $query->fetchAssoc();
    if ($result) {
      return $result['total_submissions'];
    }
  }

  public function setTotalSubmissions($webformId, $totalSubmissions) {
    $this->database->merge('coe_webform_reports_view_count')
      ->key(['id' => $webformId])
      ->fields(['total_submissions' => $totalSubmissions])
      ->execute();
  }

  public function getAverageTime(string $webformId) {
    $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
      ->fields('coe_rvc', ['average_time'])
      ->condition('coe_rvc.id', $webformId)
      ->execute();

    $result = $query->fetchAssoc();
    if ($result) {
      return $result['average_time'];
    }
  }

  public function setAverageTime($webformId, $averageTime) {
    $this->database->merge('coe_webform_reports_view_count')
      ->key(['id' => $webformId])
      ->fields(['average_time' => $averageTime])
      ->execute();
  }

  /**
   * Convert seconds to minutes with seconds or to hours and minutes.
   *
   * @param int $seconds
   *   The number of seconds to convert.
   *
   * @return string
   *   The formatted time string.
   */
  public function convertSecondsToTime($seconds) {
    // Check if the value is negative (e.g., for elapsed time).
    $is_negative = $seconds < 0;
    $seconds = abs($seconds);

    // Calculate hours, minutes, and remaining seconds.
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $remaining_seconds = $seconds % 60;

    // Build the formatted time string.
    $formatted_time = '';
    if ($hours > 0) {
      if ($hours == 1) {
        $formatted_time .= ($is_negative ? '-' : '') . $hours . ' hour ';
      }
      else {
        $formatted_time .= ($is_negative ? '-' : '') . $hours . ' hours ';
      }
    }

    if ($minutes > 0 || $hours > 0) {
      if ($minutes == 1) {
        $formatted_time .= $minutes . ' minute ';
      }
      else {
        $formatted_time .= $minutes . ' minutes ';
      }
    }
    if ($hours == 0) {
      if ($remaining_seconds == 0) {
        $formatted_time .= '';
      }
      elseif ($remaining_seconds == 1) {
        $formatted_time .= $remaining_seconds . ' second';
      }
      else {
        $formatted_time .= $remaining_seconds . ' seconds';
      }
    }

    return trim($formatted_time);
  }

  /**
   * Clears custom cache tag.
   */
  public function clearCache() {
    $this->cacheTagsInvalidator->invalidateTags([
      'coe_webform_reports:view',
    ]);
  }

}
