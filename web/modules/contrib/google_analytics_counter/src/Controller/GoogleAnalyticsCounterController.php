<?php

namespace Drupal\google_analytics_counter\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterHelper;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide Google Analytics counter controller.
 *
 * @package Drupal\google_analytics_counter\Controller
 */
class GoogleAnalyticsCounterController extends ControllerBase {

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The state keyvalue collection.
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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Google Analytics counter ppp manager definition.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface
   */
  protected $appManager;

  /**
   * The Google Analytics Counter message manager.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface
   */
  protected $messageManager;

  /**
   * Provides a redirect destinations.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * Constructs a GoogleAnalyticsCounterController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The environment time.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface $app_manager
   *   Google Analytics Counter App Manager object.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface $message_manager
   *   Google Analytics Counter Manager object.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   Provides a redirect destinations.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    DateFormatter $date_formatter,
    TimeInterface $time,
    GoogleAnalyticsCounterAppManagerInterface $app_manager,
    GoogleAnalyticsCounterMessageManagerInterface $message_manager,
    RedirectDestinationInterface $destination
    ) {
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->appManager = $app_manager;
    $this->messageManager = $message_manager;
    $this->destination = $destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('google_analytics_counter.app_manager'),
      $container->get('google_analytics_counter.message_manager'),
      $container->get('redirect.destination'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function dashboard() {
    $build = [];
    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
      '#value' => $this->t('Information on this page is updated during cron runs.') . '</h4>',
    ];

    // Information from Google.
    $build['google_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information from Google Analytics API'),
      '#open' => TRUE,
    ];

    // Get and format total pageviews.
    $t_args = $this->messageManager->setStartDateEndDate();
    $totalViews = $this->state->get('google_analytics_counter.total_pageviews') ?? 0;
    $t_args += ['%total_pageviews' => number_format($totalViews)];
    $build['google_info']['total_pageviews'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%total_pageviews pageviews were recorded by Google Analytics for this view between %start_date - %end_date.', $t_args),
    ];

    // Get and format total paths.
    $t_args = $this->messageManager->setStartDateEndDate();
    $totalPaths = $this->state->get('google_analytics_counter.total_paths') ?? 0;
    $t_args += [
      '%total_paths' => number_format($totalPaths),
    ];
    $build['google_info']['total_paths'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%total_paths paths were recorded by Google Analytics for this view between %start_date - %end_date.', $t_args),
    ];

    // Information from Drupal.
    $build['drupal_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Information from this site'),
      '#open' => TRUE,
    ];

    $last_check = $this->state->get('google_analytics_counter.last_fetch', 0);
    $build['drupal_info']['last_check'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
    ];
    if ($last_check) {
      $build['drupal_info']['last_check']['#value'] = $this->t('Last checked: @time ago.', ['@time' => $this->dateFormatter->formatTimeDiffSince($last_check)]);
    }
    else {
      $build['drupal_info']['last_check']['#value'] = $this->t('Last checked: never.');
    }

    $build['drupal_info']['number_paths_stored'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%num_of_results paths are currently stored in the local database table.', ['%num_of_results' => number_format(GoogleAnalyticsCounterHelper::getCount('google_analytics_counter'))]),
    ];

    $totalNodes = $this->state->get('google_analytics_counter.total_nodes') ?? 0;

    $build['drupal_info']['total_nodes'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%totalnodes nodes are published on this site.', ['%totalnodes' => number_format($totalNodes)]),
    ];

    $build['drupal_info']['total_nodes_with_pageviews'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%num_of_results nodes on this site have pageview counts <em>greater than zero</em>.', ['%num_of_results' => number_format(GoogleAnalyticsCounterHelper::getCount('google_analytics_counter_storage'))]),
    ];

    $t_args = [
      '%num_of_results' => number_format(GoogleAnalyticsCounterHelper::getCount('google_analytics_counter_storage_all_nodes')),
    ];
    $build['drupal_info']['total_nodes_equal_zero'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%num_of_results nodes on this site have pageview counts.<br /><strong>Note:</strong> The nodes on this site that have pageview counts should equal the number of published nodes.', $t_args),
    ];

    $t_args = [
      '%queue_count' => number_format(GoogleAnalyticsCounterHelper::getCount('queue')),
      ':href' => Url::fromRoute('google_analytics_counter.admin_settings_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'settings form',
    ];
    $build['drupal_info']['queue_count'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%queue_count items are in the queue. The number of items in the queue should be 0 after cron runs.<br />Having 0 items in the queue confirms that pageview counts are up to date. Increase Queue Time on the <a href=:href>@href</a> to process all the queued items.', $t_args),
    ];

    // Top Twenty Results.
    $build['drupal_info']['top_twenty_results'] = [
      '#type' => 'details',
      '#title' => $this->t('Top Twenty Results'),
      '#open' => TRUE,
    ];

    // Top Twenty Results for Google Analytics Counter table.
    $build['drupal_info']['top_twenty_results']['counter'] = [
      '#type' => 'details',
      '#title' => $this->t('The pages visited'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['google-analytics-counter-counter'],
      ],
    ];

    $build['drupal_info']['top_twenty_results']['counter']['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("The pages visited, listed by URI. The URI is the portion of a page's URL following the domain name; for example, the URI portion of www.example.com/contact.html is /contact.html."),
    ];

    $rows = $this->messageManager->getTopTwentyResults('google_analytics_counter');
    // Display table.
    $build['drupal_info']['top_twenty_results']['counter']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Pagepath'),
        $this->t('Pageviews'),
      ],
      '#rows' => $rows,
    ];

    // Top Twenty Results for Google Analytics Counter Storage table.
    $build['drupal_info']['top_twenty_results']['storage'] = [
      '#type' => 'details',
      '#title' => $this->t('Pageviews'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['google-analytics-counter-storage'],
      ],
    ];

    $build['drupal_info']['top_twenty_results']['storage']['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Pageviews is the total number of pages viewed. Pageviews include node/id, aliases, international, and redirects, amongst other pages Google has determined belong to the pageview.'),
    ];

    $rows = $this->messageManager->getTopTwentyResults('google_analytics_counter_storage');
    // Display table.
    $build['drupal_info']['top_twenty_results']['storage']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Nid'),
        $this->t('Pageview Total'),
      ],
      '#rows' => $rows,
    ];

    // Cron Information.
    $build['cron_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron Information'),
      '#open' => TRUE,
    ];

    $systemLastCronRun = $this->state->get('system.cron_last');
    $build['cron_information']['last_cron_run'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => (!empty($systemLastCronRun)) ?
      $this->t("Last run: %time ago.", ['%time' => $this->dateFormatter->formatTimeDiffSince($systemLastCronRun)]) :
      $this->t("Last run: never."),
    ];

    // Run cron immediately.
    $destination = $this->destination->getAsArray();
    $t_args = [
      ':href' => Url::fromRoute('system.run_cron', [], [
        'absolute' => TRUE,
        'query' => $destination,
      ])->toString(),
      '@href' => 'Run cron immediately.',
    ];
    $build['cron_information']['run_cron'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<a href=:href>@href</a>', $t_args),
    ];

    return $build;
  }

}
