<?php

namespace Drupal\google_analytics_counter;

/**
 * Interface for google_analytics_counter_result_processor plugins.
 */
interface GoogleAnalyticsCounterResultProcessorInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

}
