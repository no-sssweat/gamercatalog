<?php

namespace Drupal\google_analytics_counter;

/**
 * Class GoogleAnalyticsCounterAppManager.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterAppManagerInterface {

  /**
   * Request report data.
   *
   * @param int $step
   *   In case of chunked requests the current chunk index starting from 0.
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required [default='ga:profile_id']
   *   - dimensions: optional [ga:pagePath]
   *   - metrics: required [ga:pageviews]
   *   - sort: optional [ga:pageviews]
   *   - start-date: [default=-1 week]
   *   - end_date: optional [default=today]
   *   - start_index: [default=1]
   *   - max_results: optional [default=10,000].
   *   - filters: optional [default=none]
   *   - segment: optional [default=none].
   * @param array $cache_options
   *   An optional associative array containing:
   *   - cid: optional [default=md5 hash]
   *   - expire: optional [default=CACHE_TEMPORARY]
   *   - refresh: optional [default=FALSE].
   * @param int $currentTimestamp
   *   Current timestamp that can be used for generating custom date ranges.
   *
   * @return \Google\Analytics\Data\V1beta\RunReportResponse
   *   A new RunReportResponse object
   */
  public function reportData($step, array $parameters = [], array $cache_options = [], $currentTimestamp = NULL);

  /**
   * Update the path counts.
   *
   * @param int $index
   *   The index of the chunk to fetch and update.
   * @param int $currentTimestamp
   *   Current timestamp that can be used for generating custom date ranges.
   *
   *   This function is triggered by hook_cron().
   *
   * @throws \Exception
   */
  public function gacUpdatePathCounts($index = 0, $currentTimestamp = NULL);

  /**
   * Save the pageview count for a given node.
   *
   * @param int $nid
   *   The node id.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   *
   * @throws \Exception
   */
  public function gacUpdateStorage($nid, $bundle, $vid);

  /**
   * Get the count of pageviews for a path.
   *
   * @return string
   *   Count of page views.
   */
  public function gacDisplayCount();

}
