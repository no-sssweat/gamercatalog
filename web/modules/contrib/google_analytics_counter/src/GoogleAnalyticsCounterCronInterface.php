<?php

namespace Drupal\google_analytics_counter;

/**
 * Interface form gac cron job, so it could be swapper easily on demand.
 */
interface GoogleAnalyticsCounterCronInterface {

  /**
   * Google analytics counter cron function.
   */
  public function googleAnalyticsCounterCron();

}
