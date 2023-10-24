<?php

namespace Drupal\google_analytics_counter;

use Drupal\node\NodeInterface;

/**
 * Provides Google Analytics Counter helper functions.
 */
class GoogleAnalyticsCounterHelper {

  /**
   * Remove queued items from the database.
   */
  public static function gacRemoveQueuedItems() {
    $quantity = 200000;

    $connection = \Drupal::database();

    $query = $connection->select('queue', 'q');
    $query->addExpression('COUNT(*)');
    $query->condition('name', 'google_analytics_counter_worker');
    $queued_workers = $query->execute()->fetchField();
    $chunks = $queued_workers / $quantity;

    // @todo get $t_arg working.
    // $t_arg = ['@quantity' => $quantity];
    for ($x = 0; $x <= $chunks; $x++) {
      \Drupal::database()
        ->query("DELETE FROM {queue} WHERE name = 'google_analytics_counter_worker' LIMIT 200000");
    }
  }

  /**
   * Creates the gac_type_{content_type} configuration.
   *
   * On installation or update.
   */
  public static function gacSaveTypeConfig() {
    $config_factory = \Drupal::configFactory();
    $content_types = \Drupal::service('entity_type.manager')
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($content_types as $machine_name => $content_type) {
      // For updates, don't overwrite existing configuration.
      $gac_type = $config_factory->getEditable('google_analytics_counter.settings')
        ->get("general_settings.gac_type_$machine_name");
      if (empty($gac_type)) {
        $config_factory->getEditable('google_analytics_counter.settings')
          ->set("general_settings.gac_type_$machine_name", NULL)
          ->save();
      }
    }
  }

  /**
   * Get the row count of a table, sometimes with conditions.
   *
   * @param string $table
   *   The table name.
   *
   * @return mixed
   *   Return table rows count.
   */
  public static function getCount($table) {
    $connection = \Drupal::database();

    switch ($table) {
      case 'google_analytics_counter_storage':
        $query = $connection->select($table, 't');
        $query->addField('t', 'field_pageview_total');
        $query->condition('pageview_total', 0, '>');
        break;

      case 'google_analytics_counter_storage_all_nodes':
        $query = $connection->select('google_analytics_counter_storage', 't');
        break;

      case 'queue':
        $query = $connection->select('queue', 'q');
        $query->condition('name', 'google_analytics_counter_worker', '=');
        break;

      default:
        $query = $connection->select($table, 't');
        break;
    }
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Sets the expiry timestamp for cached queries. Default is 1 day.
   *
   * @return int
   *   The UNIX timestamp to expire the query at.
   */
  public static function cacheTime() {
    $config = \Drupal::config('google_analytics_counter.settings');
    return time() + $config->get('general_settings.cache_length');
  }

  /**
   * Delete stored state values.
   */
  public static function gacDeleteState() {
    \Drupal::state()->deleteMultiple([
      'google_analytics_counter.access_token',
      'google_analytics_counter.expires_at',
      'google_analytics_counter.refresh_token',
      'google_analytics_counter.total_nodes',
      'google_analytics_counter.data_last_refreshed',
      'google_analytics_counter.profile_ids',
      'google_analytics_counter.data_step',
      'google_analytics_counter.most_recent_query',
      'google_analytics_counter.total_pageviews',
      'google_analytics_counter.total_paths',
    ]);
  }

  /**
   * Return proper query dates using settings from config.
   *
   * @param string $dateFormat
   *   PHP date format.
   * @param int $currentTimestamp
   *   Current timestamp that is used for generating dynamic date ranges.
   *
   * @return array
   *   Result date ranges in this format:
   *   [
   *     'start' => $startDate,
   *     'end' => $endDate,
   *   ];
   */
  public static function buildQueryDates($dateFormat = 'Y-m-d', $currentTimestamp = NULL) {
    $config = \Drupal::config('google_analytics_counter.settings');

    if (empty($currentTimestamp)) {
      $currentTimestamp = \Drupal::time()->getRequestTime();
    }

    $startDateSetting = $config->get('general_settings.start_date');

    $start = 0;
    $end = 0;

    if ($startDateSetting === 'custom_day') {
      $startSetting = $config->get('general_settings.custom_start_day');
      $endSetting = $config->get('general_settings.custom_end_day');

      if ($startSetting === 0 || $startSetting === '0') {
        $start = strtotime('today', $currentTimestamp);
      }
      elseif (!isset($startSetting) || $startSetting === '') {
        $start = strtotime('14 November 2005');
      }
      else {
        $start = strtotime($startSetting . 'days ago', $currentTimestamp);
      }

      if ($endSetting === 0 || $endSetting === '0') {
        $end = strtotime('today', $currentTimestamp);
      }
      elseif (!isset($endSetting) || $endSetting === '') {
        $end = strtotime('yesterday', $currentTimestamp);
      }
      else {
        $end = strtotime($endSetting . 'days ago', $currentTimestamp);
      }

    }
    elseif ($startDateSetting === 'custom') {
      $startSetting = $config->get('general_settings.custom_start_date');
      $endSetting = $config->get('general_settings.custom_end_date');

      $startDateTime = \DateTime::createFromFormat('Y-m-d', $startSetting);
      $endDateTime = \DateTime::createFromFormat('Y-m-d', $endSetting);

      $start = $startDateTime ? $startDateTime->format('U') : 0;
      $end = $endDateTime ? $endDateTime->format('U') : 0;

      if (empty($start)) {
        $start = strtotime('14 November 2005');
      }

      if (empty($end)) {
        $end = strtotime('yesterday', $currentTimestamp);
      }

    }
    else {
      $startSetting = $config->get('general_settings.start_date');
      $endSetting = $config->get('general_settings.end_date');

      $start = strtotime($startSetting, $currentTimestamp);
      $end = strtotime($endSetting, $currentTimestamp);

      if (empty($start)) {
        $start = strtotime('14 November 2005');
      }

      if (empty($end)) {
        $end = strtotime('yesterday', $currentTimestamp);
      }
    }

    $startDate = date($dateFormat, $start);
    $endDate = date($dateFormat, $end);

    return [
      'start' => $startDate,
      'end' => $endDate,
    ];
  }

  /**
   * Returns the next Node ID to process based on the one passed.
   *
   * @param int $currentNid
   *   Current Node ID that was processed. It'll be used to calculate the next.
   * @param int $requestTime
   *   Current timestamp that is used for generating dynamic date ranges.
   *
   * @return array|null
   *   Array with the next node nid, vid, type, or NULL if there's no next.
   */
  public static function queryNextNodeToProcess($currentNid = NULL, $requestTime = NULL) {
    if (empty($requestTime)) {
      $currentTimestamp = \Drupal::time()->getRequestTime();
    }
    else {
      $currentTimestamp = $requestTime;
    }
    $config = \Drupal::config('google_analytics_counter.settings');
    $conditionOnlyLastXDays = $config->get('general_settings.node_last_x_days');
    $conditionNotNewerThanXDays = $config->get('general_settings.node_not_newer_than_x_days');

    $query = self::getBaseQuery();

    if ($currentNid) {
      $query->condition('nid', $currentNid, '<');
    }

    if (isset($conditionOnlyLastXDays) && $conditionOnlyLastXDays !== '') {
      $query->condition('created', strtotime("{$conditionOnlyLastXDays} days ago 00:00:00", $currentTimestamp), '>=');
    }

    if (isset($conditionNotNewerThanXDays) && $conditionNotNewerThanXDays !== '') {
      $query->condition('created', strtotime("{$conditionNotNewerThanXDays} days ago 00:00:00", $currentTimestamp), '<');
    }

    $query->range(0, 1);
    $result = $query->execute()->fetchAssoc();
    return $result ?? NULL;
  }

  /**
   * For next node ID generating and other similair queries gives a base query.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Base query that can be extended by other functions.
   */
  public static function getBaseQuery() {
    $query = \Drupal::database()->select('node_field_data', 'nfd');
    $query->fields('nfd', ['nid', 'type', 'vid']);
    $query->condition('status', NodeInterface::PUBLISHED);
    $query->orderBy('nid', 'DESC');
    $query->addTag('google_analytics_counter');
    return $query;
  }

  /**
   * Adds a new count queue element that will write page view for a Node.
   *
   * @param array $nextNodeData
   *   Next node data returned by queryNextNodeToProcess().
   * @param int $processed
   *   Already processed Node count. Used for setting
   *   "Update pageviews for the last X content" later in other functions.
   * @param int $requestTime
   *   Current timestamp that is used for generating dynamic date ranges.
   */
  public static function addToQueue($nextNodeData, $processed = 1, $requestTime = NULL) {
    $queue = \Drupal::queue('google_analytics_counter_worker');

    $queue->createItem([
      'type' => 'count',
      'nid' => $nextNodeData['nid'],
      'bundle' => $nextNodeData['type'],
      'vid' => $nextNodeData['vid'],
      'create_next_item' => TRUE,
      'processed' => $processed,
      'request_time' => $requestTime,
    ]);
  }

}
