<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Defines the Google Analytics Counter message manager.
 *
 * @package Drupal\google_analytics_counter
 */
class GoogleAnalyticsCounterMessageManager implements GoogleAnalyticsCounterMessageManagerInterface {

  use StringTranslationTrait;

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
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

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
   * Constructs a GoogleAnalyticsCounterMessageManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, StateInterface $state, DateFormatter $date_formatter, LoggerInterface $logger, MessengerInterface $messenger) {
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->connection = $connection;
    $this->dateFormatter = $date_formatter;
    $this->state = $state;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * Get the the top twenty results for pageviews and pageview_totals.
   *
   * @param string $table
   *   The table from which the results are selected.
   *
   * @return mixed
   *   Top twenty results
   */
  public function getTopTwentyResults($table) {
    $query = $this->connection->select($table, 't');
    $query->range(0, 20);
    $rows = [];
    switch ($table) {
      case 'google_analytics_counter':
        $query->fields('t', ['pagepath', 'pageviews']);
        $query->orderBy('pageviews', 'DESC');
        $result = $query->execute()->fetchAll();
        $rows = [];
        foreach ($result as $value) {
          $rows[] = [
            $value->pagepath,
            $value->pageviews,
          ];
        }
        break;

      case 'google_analytics_counter_storage':
        $query->fields('t', ['nid', 'pageview_total']);
        $query->orderBy('pageview_total', 'DESC');
        $result = $query->execute()->fetchAll();
        foreach ($result as $value) {
          $rows[] = [
            $value->nid,
            $value->pageview_total,
          ];
        }
        break;

      default:
        break;
    }

    return $rows;
  }

  /**
   * Sets the start and end dates in configuration.
   *
   * @return array
   *   Start and end dates.
   */
  public function setStartDateEndDate() {
    $queryDates = GoogleAnalyticsCounterHelper::buildQueryDates('M j, Y');
    return [
      '%start_date' => $queryDates['start'],
      '%end_date' => $queryDates['end'],
    ];
  }

}
