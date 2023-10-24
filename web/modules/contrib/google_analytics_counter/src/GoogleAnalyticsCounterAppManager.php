<?php

namespace Drupal\google_analytics_counter;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\google_analytics_counter\Event\GoogleAnalyticsCounterEvents;
use Drupal\google_analytics_counter\Event\GoogleAnalyticsCounterQueryAlterEvent;
use Drupal\path_alias\AliasManagerInterface;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Psr\Log\LoggerInterface;

/**
 * Google Analytics counter app manager plugin.
 *
 * @package Drupal\google_analytics_counter
 */
class GoogleAnalyticsCounterAppManager implements GoogleAnalyticsCounterAppManagerInterface {

  use StringTranslationTrait;

  /**
   * The table for the node__field_google_analytics_counter storage.
   */
  const TABLE = 'node__field_google_analytics_counter';

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The state where all the tokens are saved.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The language manager to get all languages for to get all aliases.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Retrieves the currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * GAC result processor.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginManager
   */
  protected $gacResultProcessor;

  /**
   * Default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Entity cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $entityCache;

  /**
   * Event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * Constructs a Google Analytics Counter object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state of the drupal site.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager to find aliased resources.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   Brings tha paths on cache.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Retrieves the currently active route match object.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginManager $gacResultProcessor
   *   GAC result processor.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Default cache bin.
   * @param \Drupal\Core\Cache\CacheBackendInterface $entityCache
   *   Entity cache bin.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   Event dispatcher.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Connection $connection,
    StateInterface $state,
    AliasManagerInterface $alias_manager,
    PathMatcherInterface $path_matcher,
    LanguageManagerInterface $language,
    LoggerInterface $logger,
    MessengerInterface $messenger,
    CurrentPathStack $current_path,
    RouteMatchInterface $route_match,
    GoogleAnalyticsCounterResultProcessorPluginManager $gacResultProcessor,
    CacheBackendInterface $cache,
    CacheBackendInterface $entityCache,
    ContainerAwareEventDispatcher $eventDispatcher
  ) {
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->connection = $connection;
    $this->state = $state;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->languageManager = $language;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->currentPath = $current_path;
    $this->routeMatch = $route_match;
    $this->gacResultProcessor = $gacResultProcessor;
    $this->cache = $cache;
    $this->entityCache = $entityCache;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Request report data.
   *
   * @param int $step
   *   Current chunk index for paginated queries starting from 0.
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required [default='ga:profile_id']
   *   - dimensions: optional [ga:pagePath]
   *   - metrics: required [ga:pageviews]
   *   - sort: optional [ga:pageviews]
   *   - start-date: [default=-1 week]
   *   - end_date: optional [default=today]
   *   - start_index: [default=1]
   *   - max_results: optional [default=10,000].
   *   - filters: optional [default=none]
   *   - segment: optional [default=none].
   * @param array $cache_options
   *   An optional associative array containing:
   *   - cid: optional [default=md5 hash]
   *   - expire: optional [default=CACHE_TEMPORARY]
   *   - refresh: optional [default=FALSE].
   * @param int $currentTimestamp
   *   Current timestamp that can be used for generating custom date ranges.
   *
   * @return \Google\Analytics\Data\V1beta\RunReportResponse
   *   A new RunReportResponse object
   */
  public function reportData($step, array $parameters = [], array $cache_options = [], $currentTimestamp = NULL) {
    $feed = $this->buildQuery($step, $parameters, $cache_options, NULL, NULL, $currentTimestamp);

    // Set the total number of pagePaths for this profile
    // from start_date to end_date.
    $rowCount = $feed->getRows()->count();

    // The number of results from Google Analytics in one request.
    $chunk = $this->config->get('general_settings.chunk_to_fetch');

    // Which node to look for first. Must be between 1 - infinity.
    $pointer = $step * $chunk + 1;

    // Set the pointer equal to the pointer plus the chunk.
    $pointer += $chunk;

    $t_args = [
      '@size_of' => $rowCount,
      '@first' => ($pointer - $chunk),
      '@second' => ($pointer - $chunk - 1 + $rowCount),
    ];
    $this->logger->info('Retrieved @size_of items from Google Analytics data for paths @first - @second.', $t_args);

    return $feed;
  }

  /**
   * Update the path counts.
   *
   * @param int $index
   *   The index of the chunk to fetch and update.
   * @param int $currentTimestamp
   *   Current timestamp that can be used for generating custom date ranges.
   *
   *   This function is triggered by hook_cron().
   *
   * @throws \Exception
   */
  public function gacUpdatePathCounts($index = 0, $currentTimestamp = NULL) {
    $feed = $this->reportData($index, [], [], $currentTimestamp);

    $defaultProcessorPlugin = $this->config->get('general_settings.result_processor') ?? 'url_alias';

    $currentProcessor = $this->gacResultProcessor->getPlugin($defaultProcessorPlugin);
    $cleanPaths = $currentProcessor->processPagePathResultRows($feed);

    if (!$currentProcessor->isUpdatePathTable()) {
      return;
    }

    $count = count($cleanPaths);

    if ($count > 0) {
      foreach ($cleanPaths as $page_path => $value) {
        // Update the Google Analytics Counter. Merging with any previous
        // values since we the truncate before running.
        $this->connection->merge('google_analytics_counter')
          ->key('pagepath_hash', md5($page_path))
          ->fields([
            'pagepath' => $page_path,
            'pageviews' => $value,
          ])
          ->execute();
      }
    }

    // Log the results.
    $this->logger->info($this->t('Merged @count paths from Google Analytics into the database.', ['@count' => $count]));
  }

  /**
   * Save the pageview count for a given node.
   *
   * @param int $nid
   *   The node id.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   *
   * @throws \Exception
   */
  public function gacUpdateStorage($nid, $bundle, $vid) {
    $defaultProcessorPlugin = $this->config->get('general_settings.result_processor') ?? 'url_alias';

    $currentProcessor = $this->gacResultProcessor->getPlugin($defaultProcessorPlugin);

    $sum_pageviews = $currentProcessor->processGacUpdateStorage($nid, $bundle, $vid);

    if ($currentProcessor->isUpdateGacStorage()) {
      $this->updateCounterStorage($nid, $sum_pageviews, $bundle, $vid);
    }
  }

  /**
   * Merge the sum of pageviews into google_analytics_counter_storage.
   *
   * @param int $nid
   *   Node id value.
   * @param int $sum_pageviews
   *   Count of page views.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   *
   * @throws \Exception
   */
  protected function updateCounterStorage($nid, $sum_pageviews, $bundle, $vid) {
    $this->connection->merge('google_analytics_counter_storage')
      ->key('nid', $nid)
      ->fields([
        'pageview_total' => $sum_pageviews,
      ])
      ->execute();

    // Update the Google Analytics Counter field if it exists.
    if (!$this->connection->schema()->tableExists(static::TABLE)) {
      return;
    }

    // @todo This can be more performant by adding only the bundles that have been selected.
    $this->connection->upsert('node__field_google_analytics_counter')
      ->key('revision_id')
      ->fields([
        'bundle',
        'deleted',
        'entity_id',
        'revision_id',
        'langcode',
        'delta',
        'field_google_analytics_counter_value',
      ])
      ->values([
        'bundle' => $bundle,
        'deleted' => 0,
        'entity_id' => $nid,
        'revision_id' => $vid,
        'langcode' => $this->languageManager->getDefaultLanguage()->getId(),
        'delta' => 0,
        'field_google_analytics_counter_value' => $sum_pageviews,
      ])
      ->execute();

    // Possible fix for a use case where field doesn't show updated value
    // in views after cron runs.
    $this->entityCache->invalidate('values:node:' . $nid);
  }

  /**
   * Instantiate a new RunReportResponse object and query Google.
   *
   * @param array $parameters
   *   The array of parameters.
   * @param array $cache_options
   *   The array of cache options.
   *
   * @return \Google\Analytics\Data\V1beta\RunReportResponse
   *   Return new RunReportResponse object and query Google.
   */
  protected function gacGetFeed(array $parameters, array $cache_options) {
    if ($cache = $this->cache->get($cache_options['cid'])) {
      return $cache->data;
    }

    $api_credentials_path = $this->config->get('general_settings.credentials_json_path') ?? '';

    // Adds a variable to the server environment.
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $api_credentials_path);

    // Using a default constructor instructs the client to use the credentials
    // specified in GOOGLE_APPLICATION_CREDENTIALS environment variable.
    $client = new BetaAnalyticsDataClient();

    // Make an API call to the Data API.
    $feed = $client->runReport($parameters);

    $this->cache->set($cache_options['cid'], $feed, $cache_options['expire'], ['google_analytics_counter_data']);

    return $feed;
  }

  /**
   * Get the count of pageviews for a path.
   *
   * @return string
   *   Count of page views.
   */
  public function gacDisplayCount() {
    $defaultProcessorPlugin = $this->config->get('general_settings.result_processor') ?? 'url_alias';
    $currentProcessor = $this->gacResultProcessor->getPlugin($defaultProcessorPlugin);
    return $currentProcessor->gacDisplayCount();
  }

  /**
   * Queries how many paths are recorded in Google Analytics.
   *
   * Also saves the number to state for later reports.
   *
   * @return int
   *   Total path count. Useful for detecting how many chunks to use
   *   for paginated results.
   */
  public function queryTotalPaths() {
    $feed = $this->buildQuery(0, [], [], 1, 0);
    $totals = $feed->getRowCount();
    $this->state->set('google_analytics_counter.total_paths', $totals);
    return $totals;
  }

  /**
   * Builds and runs a GA query.
   *
   * @param int $step
   *   Current chunk index for paginated queries starting from 0.
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required [default='ga:profile_id']
   *   - dimensions: optional [ga:pagePath]
   *   - metrics: required [ga:pageviews]
   *   - sort: optional [ga:pageviews]
   *   - start-date: [default=-1 week]
   *   - end_date: optional [default=today]
   *   - start_index: [default=1]
   *   - max_results: optional [default=10,000].
   *   - filters: optional [default=none]
   *   - segment: optional [default=none].
   * @param array $cache_options
   *   An optional associative array containing:
   *   - cid: optional [default=md5 hash]
   *   - expire: optional [default=CACHE_TEMPORARY]
   *   - refresh: optional [default=FALSE].
   * @param int $limit
   *   Custom result limit parameter.
   * @param int $offset
   *   Custom result offset parameter.
   * @param int $currentTimestamp
   *   Current timestamp that can be used for generating custom date ranges.
   *
   * @return \Google\Analytics\Data\V1beta\RunReportResponse
   *   A new RunReportResponse object
   */
  protected function buildQuery($step, array $parameters = [], array $cache_options = [], $limit = NULL, $offset = NULL, $currentTimestamp = NULL) {
    $config = $this->config;
    $chunk = (!empty($limit)) ? $limit : $config->get('general_settings.chunk_to_fetch');

    // Initialize the pointer.
    $pointer = (!empty($offset)) ? $offset : $step * $chunk;

    $queryDates = GoogleAnalyticsCounterHelper::buildQueryDates('Y-m-d', $currentTimestamp);
    $startDate = $queryDates['start'];
    $endDate = $queryDates['end'];

    $property_id = $config->get('general_settings.ga4_property_id') ?? '';

    $parameters = [
      'property' => 'properties/' . $property_id,
      'dateRanges' => [
        new DateRange([
          'start_date' => $startDate,
          'end_date' => $endDate,
        ]),
      ],
      'dimensions' => [new Dimension(
        [
          'name' => $this->config->get('general_settings.dimension') ?? 'pagePath',
        ]
        ),
      ],
      'metrics' => [new Metric(
        [
          'name' => $this->config->get('general_settings.metric') ?? 'screenPageViews',
        ]
        ),
      ],
      'offset' => $pointer,
      'limit' => $chunk,
    ];

    $cache_options = [
      'cid' => 'google_analytics_counter_' . md5(serialize($parameters)),
      'expire' => GoogleAnalyticsCounterHelper::cacheTime(),
      'refresh' => FALSE,
    ];

    $event = new GoogleAnalyticsCounterQueryAlterEvent($step, $parameters, $cache_options, $limit, $offset, $currentTimestamp);
    $this->eventDispatcher->dispatch($event, GoogleAnalyticsCounterEvents::QUERY_ALTER);

    $parameters = $event->getParameters();
    $cache_options = $event->getCacheOptions();

    // Instantiate a new RunReportResponse object.
    $feed = $this->gacGetFeed($parameters, $cache_options);

    return $feed;
  }

}
