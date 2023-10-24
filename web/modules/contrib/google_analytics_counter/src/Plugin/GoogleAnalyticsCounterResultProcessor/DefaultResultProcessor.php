<?php

namespace Drupal\google_analytics_counter\Plugin\GoogleAnalyticsCounterResultProcessor;

use Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginBase;
use Drupal\node\NodeInterface;

/**
 * Plugin implementation of the google_analytics_counter_result_processor.
 *
 * @GoogleAnalyticsCounterResultProcessor(
 *   id = "default",
 *   label = @Translation("NID"),
 *   description = @Translation("NID")
 * )
 */
class DefaultResultProcessor extends GoogleAnalyticsCounterResultProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processPagePathResultRows($feed) {
    $cleanPaths = [];

    foreach ($feed->getRows() as $value) {
      // Nid can be with leading zeroes. Remove it for easier mapping.
      $nidDimension = $value->getDimensionValues()[0]->getValue();
      $nid = $this->removeLeadingZeroes($nidDimension);
      $cleanValue = (int) $value->getMetricValues()[0]->getValue();
      if (!empty($nid) && $cleanValue >= 1) {
        $cleanPaths[$nid] = $cleanValue;
      }
    }

    return $cleanPaths;
  }

  /**
   * {@inheritdoc}
   */
  public function gacDisplayCount() {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      // You can get nid and anything else you need from the node object.
      $nid = $node->id();
    }
    if (empty($nid)) {
      return '';
    }
    $sum_pageviews = $this->sumPageviews([$nid]);
    return number_format($sum_pageviews);
  }

  /**
   * Removes leading zeroes from a string number.
   *
   * For example: 005 becomes 5, 0 stays 0.
   *
   * @param string $strNumber
   *   Number where zeroes should be stripped from left.
   *
   * @return string
   *   Fixed number that can be converted to int.
   */
  protected function removeLeadingZeroes($strNumber) {
    $str = trim('' . $strNumber);
    if ($str === "0") {
      return $str;
    }
    return ltrim($str, "0");
  }

}
