<?php

namespace Drupal\google_analytics_counter\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterHelper;

/**
 * Provides base functionality for Google Analytics Counter workers.
 *
 * @see See https://www.drupal.org/forum/support/module-development-and-code-questions/2017-03-20/queue-items-not-processed
 * @see https://drupal.stackexchange.com/questions/206838/documentation-or-tutorial-on-using-batch-or-queue-services-api-programmatically
 */
abstract class GoogleAnalyticsCounterQueueBase extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManager $appManager **/
    $appManager = \Drupal::service('google_analytics_counter.app_manager');
    $currentTimestamp = $data['request_time'] ?? \Drupal::time()->getRequestTime();

    try {
      if ($data['type'] == 'fetch') {
        $appManager->gacUpdatePathCounts($data['index'], $currentTimestamp);
      }
      elseif ($data['type'] == 'count') {
        $appManager->gacUpdateStorage($data['nid'], $data['bundle'], $data['vid']);
        if (!empty($data['create_next_item'])) {
          $processed = $data['processed'] ?? 0;
          $processed += 1;

          $config = \Drupal::config('google_analytics_counter.settings');
          $conditionOnlyLastXNid = $config->get('general_settings.node_last_x_nid');

          $isConditionOnlyLastXNidEnabled = !empty($conditionOnlyLastXNid);

          if (!$isConditionOnlyLastXNidEnabled ||
            ($isConditionOnlyLastXNidEnabled && $processed < $conditionOnlyLastXNid)
          ) {
            $nextNodeData = GoogleAnalyticsCounterHelper::queryNextNodeToProcess($data['nid'], $currentTimestamp);
            if (!empty($nextNodeData)) {
              GoogleAnalyticsCounterHelper::addToQueue($nextNodeData, $processed, $currentTimestamp);
            }
          }

        }

      }
    }
    catch (\Exception $e) {
      // In case something went wrong, abort processing the queue.
      // Otherwise incomplete results could be written to the database
      // if the "fetch" failed, and "count" processing starts.
      \Drupal::logger('google_analytics_counter')
        ->error('There was a problem while updating data, queue processing has been aborted. Error: ' . $e->getMessage());
      throw new SuspendQueueException($e->getMessage());
    }

  }

}
