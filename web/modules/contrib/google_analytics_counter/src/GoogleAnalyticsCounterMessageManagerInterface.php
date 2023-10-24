<?php

namespace Drupal\google_analytics_counter;

/**
 * Defines the Google Analytics Counter message manager.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterMessageManagerInterface {

  /**
   * Get the the top twenty results for pageviews and pageview_totals.
   *
   * @param string $table
   *   The table from which the results are selected.
   *
   * @return mixed
   *   The top twenty results.
   */
  public function getTopTwentyResults($table);

  /**
   * Sets the start and end dates in configuration.
   *
   * @return array
   *   Start and end dates.
   */
  public function setStartDateEndDate();

}
