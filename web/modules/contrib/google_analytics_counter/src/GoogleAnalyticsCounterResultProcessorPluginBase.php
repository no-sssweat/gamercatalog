<?php

namespace Drupal\google_analytics_counter;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for google_analytics_counter_result_processor plugins.
 */
abstract class GoogleAnalyticsCounterResultProcessorPluginBase extends PluginBase implements GoogleAnalyticsCounterResultProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * Custom logic for processing received pageview data rows from API.
   *
   * @param \Google\Analytics\Data\V1beta\RunReportResponse $feed
   *   API result with rows.
   *
   * @return array
   *   An array, where key should be the URL or other dimension.
   *   The value should be the view count.
   *   This will be saved to the database.
   */
  abstract public function processPagePathResultRows($feed);

  /**
   * Before saving viewcount for each node, you can alter the count.
   *
   * Useful if you need to sum viewcount for example multiple alias, domain,
   * language etc.
   *
   * @param int $nid
   *   Node ID.
   * @param string $bundle
   *   Node type.
   * @param int $vid
   *   Node revision ID.
   *
   * @return int
   *   Summarized viewcount that will be saved to the database.
   */
  public function processGacUpdateStorage($nid, $bundle, $vid) {
    // Get all the aliases for a given node id.
    $aliases = [$nid];
    $sum_pageviews = $this->sumPageviews($aliases);
    return $sum_pageviews;
  }

  /**
   * Should the query result be saved to the path mapping table.
   *
   * Maybe you wouldn't need this if you query Node ID dimension with
   * pageview metric, as that result can be saved to Node ID's
   * view count table right away.
   *
   * @return bool
   *   True if yes, false to skip this save.
   */
  public function isUpdatePathTable() {
    return TRUE;
  }

  /**
   * Should the Node ID view count be saved to the NID - viewcount map table.
   *
   * Maybe you wouldn't need this if you want to write to the node's
   * gac field right away.
   *
   * @return bool
   *   True if yes, false to skip this save.
   */
  public function isUpdateGacStorage() {
    return TRUE;
  }

  /**
   * Gets view count for a node.
   *
   * They may need different logic to detect the view count.
   * For example the need for aggregating views for every alias and language.
   *
   * @return string
   *   Formatter numeric view count.
   */
  abstract public function gacDisplayCount();

  /**
   * Look up the count via the hash of the paths.
   *
   * @param mixed $aliases
   *   The provided aliases.
   *
   * @return string
   *   Count of views.
   */
  protected function sumPageviews($aliases) {
    // $aliases can make pageview_total greater than pageviews
    // because $aliases can include page aliases, node/id, and node/id/,
    // translations redirects and other URIs which are all the same node.
    $hashes = array_map('md5', $aliases);
    $path_counts = \Drupal::database()->select('google_analytics_counter', 'gac')
      ->fields('gac', ['pageviews'])
      ->condition('pagepath_hash', $hashes, 'IN')
      ->execute();
    $sum_pageviews = 0;
    foreach ($path_counts as $path_count) {
      $sum_pageviews += $path_count->pageviews;
    }

    return $sum_pageviews;
  }

}
