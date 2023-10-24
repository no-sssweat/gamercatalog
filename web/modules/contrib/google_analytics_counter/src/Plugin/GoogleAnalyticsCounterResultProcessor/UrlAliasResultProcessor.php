<?php

namespace Drupal\google_analytics_counter\Plugin\GoogleAnalyticsCounterResultProcessor;

use Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginBase;
use Drupal\node\NodeInterface;

/**
 * Plugin implementation of the google_analytics_counter_result_processor.
 *
 * @GoogleAnalyticsCounterResultProcessor(
 *   id = "url_alias",
 *   label = @Translation("URL Alias"),
 *   description = @Translation("URL Alias")
 * )
 */
class UrlAliasResultProcessor extends GoogleAnalyticsCounterResultProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processPagePathResultRows($feed) {
    $cleanPaths = [];

    // We need to cleanup the results as much as possible.
    foreach ($feed->getRows() as $value) {
      // Convert to lower since drupal allows mixed case aliases.
      $page_path = $value->getDimensionValues()[0]->getValue();
      // Remove query parameters since we just want to store the path
      // to match it against Drupal paths.
      // Could be FALSE on really mangled URLs.
      $path_parsed = parse_url($page_path, PHP_URL_PATH);
      // If parse_url() fails fallback to the original path.
      $cleanPath = $path_parsed ? $path_parsed : $page_path;
      // Use only the first 2047 characters of the pagePath.
      // This is extremely long but Google does store everything
      // and bots can make URIs that exceed that length.
      $cleanPath = (strlen($cleanPath) > 2047) ? substr($cleanPath, 0, 2047) : $cleanPath;
      $cleanValue = (int) $value->getMetricValues()[0]->getValue();
      if (!empty($cleanPath) && $cleanValue >= 1) {
        // Only save items with paths and values.
        // Analytics could have multiple rows for a single URL after cleaning
        // so we need to merge and add them with an pervious clean results.
        $cleanPaths[$cleanPath] = $cleanValue + ($cleanPaths[$cleanPath] ?? 0);
      }
    }

    return $cleanPaths;
  }

  /**
   * {@inheritdoc}
   */
  public function processGacUpdateStorage($nid, $bundle, $vid) {
    // Get all the aliases for a given node id.
    $aliases = [];
    $path = '/node/' . $nid;
    $aliases[] = $path;
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path, $language->getId());
      $aliases[] = $alias;
      if ($language->getId()) {
        $aliases[] = '/' . $language->getId() . $path;
        $aliases[] = '/' . $language->getId() . $alias;
      }
    }

    // Add also all versions with a trailing slash.
    $aliases = array_merge($aliases, array_map(function ($path) {
      return $path . '/';
    }, $aliases));

    // Drupal aliases allow mixed case so convert to lowercase
    // for better matching against google analytics page urls.
    $aliases = array_map('strtolower', $aliases);
    // Drupal can also have special characters like & in paths
    // that would be escaped in analytics,
    // so we have to escape them as well before matching.
    $aliasesEscaped = array_map(['\Drupal\Component\Utility\Html', 'escape'], $aliases);

    // It's the front page.
    if (\Drupal::service('path.matcher')->isFrontPage()) {
      $sum_pageviews = $this->sumPageviews(['/']);
    }
    else {
      $sum_pageviews = $this->sumPageviews(array_unique($aliasesEscaped));
    }

    return $sum_pageviews;
  }

  /**
   * {@inheritdoc}
   */
  public function gacDisplayCount() {
    // Make sure the path starts with a slash.
    $path = \Drupal::service('path.current')->getPath();
    $path = '/' . trim($path, ' /');

    $sum_pageviews = 0;
    // It's the front page.
    if (\Drupal::service('path.matcher')->isFrontPage()) {
      $aliases = ['/'];
      $sum_pageviews = $this->sumPageviews($aliases);
    }

    // It's a node.
    elseif ($node = \Drupal::routeMatch()->getParameter('node')) {
      if ($node instanceof NodeInterface) {
        $query = \Drupal::database()->select('google_analytics_counter_storage', 'gacs');
        $query->fields('gacs', ['pageview_total']);
        $query->condition('nid', $node->id());
        $sum_pageviews = $query->execute()->fetchField();
      }
    }

    // It's a path.
    else {
      // Look up the alias, with, and without trailing slash.
      // @todo The array is an accommodation to sumPageViews()
      $aliases = [\Drupal::service('path_alias.manager')->getAliasByPath($path)];
      $sum_pageviews = $this->sumPageviews($aliases);
    }

    return number_format($sum_pageviews);
  }

}
