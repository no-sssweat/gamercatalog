<?php

namespace Drupal\google_analytics_counter\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event before sending the query to GA4.
 */
class GoogleAnalyticsCounterQueryAlterEvent extends Event {

  /**
   * Current chunk index for paginated queries starting from 0.
   *
   * @var int
   */
  protected $step;

  /**
   * An associative array with query parameters.
   *
   * Containing:
   * - profile_id: required [default='ga:profile_id']
   * - dimensions: optional [ga:pagePath]
   * - metrics: required [ga:pageviews]
   * - sort: optional [ga:pageviews]
   * - start-date: [default=-1 week]
   * - end_date: optional [default=today]
   * - start_index: [default=1]
   * - max_results: optional [default=10,000].
   * - filters: optional [default=none]
   * - segment: optional [default=none].
   *
   * @var array
   */
  protected $parameters;

  /**
   * Query cache data.
   *
   * An optional associative array containing:
   * - cid: optional [default=md5 hash]
   * - expire: optional [default=CACHE_TEMPORARY]
   * - refresh: optional [default=FALSE].
   *
   * @var array
   */
  protected $cacheOptions;

  /**
   * Query result limit.
   *
   * @var int
   */
  protected $limit;

  /**
   * Paginated query result offset.
   *
   * @var int
   */
  protected $offset;

  /**
   * Current timestamp that can be used for generating custom date ranges.
   *
   * @var int
   */
  protected $currentTimestamp;

  /**
   * GoogleAnalyticsCounterQueryAlterEvent constructor.
   *
   * @param int $step
   *   Current chunk index for paginated queries starting from 0.
   * @param array $parameters
   *   An associative array with query parameters.
   * @param array $cache_options
   *   Query cache data.
   * @param int $limit
   *   Query result limit.
   * @param int $offset
   *   Paginated query result offset.
   * @param int $currentTimestamp
   *   Current timestamp that can be used for generating custom date ranges.
   */
  public function __construct($step, $parameters, $cache_options, $limit, $offset, $currentTimestamp) {
    $this->step = $step;
    $this->parameters = $parameters;
    $this->cacheOptions = $cache_options;
    $this->limit = $limit;
    $this->offset = $offset;
    $this->currentTimestamp = $currentTimestamp;
  }

  /**
   * Gets current chunk index for paginated queries starting from 0.
   *
   * @return int
   *   Current chunk index.
   */
  public function getStep() {
    return $this->step;
  }

  /**
   * Sets current chunk index for paginated queries starting from 0.
   *
   * @param int $step
   *   New index.
   *
   * @return GoogleAnalyticsCounterQueryAlterEvent
   *   Event.
   */
  public function setStep($step) {
    $this->step = $step;
    return $this;
  }

  /**
   * Gets an associative array with query parameters.
   *
   * @return array
   *   Query parameters.
   */
  public function getParameters() {
    return $this->parameters;
  }

  /**
   * Sets an associative array with query parameters.
   *
   * @param array $parameters
   *   New query parameters.
   *
   * @return GoogleAnalyticsCounterQueryAlterEvent
   *   Event.
   */
  public function setParameters($parameters) {
    $this->parameters = $parameters;
    return $this;
  }

  /**
   * Gets query cache data.
   *
   * @return array
   *   Query cache data.
   */
  public function getCacheOptions() {
    return $this->cacheOptions;
  }

  /**
   * Sets query cache data.
   *
   * @param array $cacheOptions
   *   New query cache data.
   *
   * @return GoogleAnalyticsCounterQueryAlterEvent
   *   Event.
   */
  public function setCacheOptions($cacheOptions) {
    $this->cacheOptions = $cacheOptions;
    return $this;
  }

  /**
   * Gets query result limit.
   *
   * @return int
   *   Query result limit.
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * Sets query result limit.
   *
   * @param int $limit
   *   New query result limit.
   *
   * @return GoogleAnalyticsCounterQueryAlterEvent
   *   Event.
   */
  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  /**
   * Gets paginated query result offset.
   *
   * @return int
   *   Paginated query result offset.
   */
  public function getOffset() {
    return $this->offset;
  }

  /**
   * Sets paginated query result offset.
   *
   * @param int $offset
   *   New paginated query result offset.
   *
   * @return GoogleAnalyticsCounterQueryAlterEvent
   *   Event.
   */
  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  /**
   * Gets current timestamp.
   *
   * @return int
   *   Current timestamp.
   */
  public function getCurrentTimestamp() {
    return $this->currentTimestamp;
  }

  /**
   * Sets current timestamp.
   *
   * @param int $currentTimestamp
   *   Current timestamp.
   *
   * @return GoogleAnalyticsCounterQueryAlterEvent
   *   Event.
   */
  public function setCurrentTimestamp($currentTimestamp) {
    $this->currentTimestamp = $currentTimestamp;
    return $this;
  }

}
