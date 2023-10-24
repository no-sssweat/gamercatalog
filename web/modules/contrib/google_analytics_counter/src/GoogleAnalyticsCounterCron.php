<?php

namespace Drupal\google_analytics_counter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Class to implement cron job, so it could be swapper easily on demand.
 */
class GoogleAnalyticsCounterCron implements GoogleAnalyticsCounterCronInterface {

  /**
   * State property.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Database property.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Time property.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $requestTime;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * GAC App Manager.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface
   */
  protected $appManager;

  /**
   * GAC config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Construct a googleAnalyticsCounterCron object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The Database property.
   * @param \Drupal\Component\Datetime\TimeInterface $requestTime
   *   The Time property.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State property.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface $appManager
   *   GAC App Manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   GAC config.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue factory.
   */
  public function __construct(Connection $database, TimeInterface $requestTime, StateInterface $state, LoggerChannelFactoryInterface $logger, GoogleAnalyticsCounterAppManagerInterface $appManager, ConfigFactoryInterface $configFactory, $queue) {
    $this->database = $database;
    $this->requestTime = $requestTime;
    $this->state = $state;
    $this->logger = $logger->get('google_analytics_counter');
    $this->appManager = $appManager;
    $this->config = $configFactory->get('google_analytics_counter.settings');
    $this->queue = $queue->get('google_analytics_counter_worker');
  }

  /**
   * Google analytics counter cron function.
   */
  public function googleAnalyticsCounterCron() {
    $requestTime = $this->requestTime->getRequestTime();

    // $interval must be a value in seconds.
    $interval = 60 * $this->config->get('general_settings.cron_interval');

    // Set the total number of published nodes.
    $query = GoogleAnalyticsCounterHelper::getBaseQuery();
    $total_nodes = $query->countQuery()->execute()->fetchField() ?? 0;
    $this->state->set('google_analytics_counter.total_nodes', $total_nodes);

    // On some systems, cron could be every minute. Throttle updating with the
    // cron_interval on the settings form.
    // To avoid this interval, set cron_interval to 0.
    if (!($requestTime >= $this->state->get('google_analytics_counter.last_fetch', 0) + $interval)) {
      return FALSE;
    }

    // Wait until the queue is completely empty.
    if ($this->queue->numberOfItems() > 0) {
      $this->logger->alert('Google Analytics Counter is still processing previously queued items. Skipped adding new items to queue to prevent duplicates. Consider running cron more often or increase "Number of items to fetch from Google Analytics in one request" to decrease queue.');
      return FALSE;
    }

    try {
      $conditionOnlyLastXDays = $this->config->get('general_settings.node_last_x_days');
      $conditionOnlyLastXNid = $this->config->get('general_settings.node_last_x_nid');
      $conditionOnlyNotNewerThanXDays = $this->config->get('general_settings.node_not_newer_than_x_days');

      if (!isset($conditionOnlyLastXDays) &&
        !isset($conditionOnlyLastXNid) &&
        !isset($conditionOnlyNotNewerThanXDays)
      ) {
        // Wipe the local database of saved paths, so we get fresh data
        // and multiple paths can be merged together into a final total.
        // Don't wipe out if we do not update all the content every time.
        $this->database->truncate('google_analytics_counter')->execute();
      }

      // Fetch the total results from Google first.
      $total_results = $this->appManager->queryTotalPaths();
      $this->state->set('google_analytics_counter.last_fetch', $requestTime);

      // Create queue fetch items from the total results divided into chunks.
      for ($index = 0; $index < $total_results / $this->config->get('general_settings.chunk_to_fetch'); $index++) {
        // Add a queue item to fetch for all chunks.
        $this->queue->createItem([
          'type' => 'fetch',
          'index' => $index,
          'request_time' => $requestTime,
        ]);
      }

      // Create the first queue item where Node pageviews will be
      // saved to node fields.
      $nextNodeData = GoogleAnalyticsCounterHelper::queryNextNodeToProcess(NULL, $requestTime);
      if (!empty($nextNodeData)) {
        GoogleAnalyticsCounterHelper::addToQueue($nextNodeData, 0, $requestTime);
      }

    }
    catch (\Exception $e) {
      $this->logger->error('Cron experienced a problem: ' . $e->getMessage());
    }
  }

}
