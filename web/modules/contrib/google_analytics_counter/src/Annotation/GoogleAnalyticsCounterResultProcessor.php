<?php

namespace Drupal\google_analytics_counter\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines google_analytics_counter_result_processor annotation object.
 *
 * @Annotation
 */
class GoogleAnalyticsCounterResultProcessor extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
