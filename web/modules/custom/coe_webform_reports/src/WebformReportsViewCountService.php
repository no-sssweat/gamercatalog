<?php

namespace Drupal\coe_webform_reports;

use Drupal\Core\Database\Connection;

/**
 * Class WebformReportsViewCountService
 *
 * Provides methods to interact with and manage view counts for webforms in the database.
 */
class WebformReportsViewCountService {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public function getViewCount(string $webformId) {
    $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
      ->fields('coe_rvc', ['view_count'])
      ->condition('coe_rvc.webform_id', $webformId)
      ->execute();

    $result = $query->fetchAssoc();
    return !empty($result) ? $result['view_count'] : 0;
  }

  public function setViewCount(string $webformId, int $viewCount) {
    $this->database->merge('coe_webform_reports_view_count')
      ->key(['webform_id' => $webformId])
      ->fields(['view_count' => $viewCount])
      ->execute();
  }

}
