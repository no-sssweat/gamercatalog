<?php

namespace Drupal\google_analytics_counter\Event;

/**
 * Defines events for the google_analytics_counter module.
 */
final class GoogleAnalyticsCounterEvents {

  /**
   * Event before sending the query to GA4.
   *
   * @Event
   *
   * @see \Drupal\google_analytics_counter\Event\GoogleAnalyticsCounterQueryAlterEvent
   */
  const QUERY_ALTER = 'google_analytics_counter.query_alter';

}
