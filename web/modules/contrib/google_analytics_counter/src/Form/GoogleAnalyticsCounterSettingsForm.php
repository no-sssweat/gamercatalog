<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterHelper;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Google Analytics Counter Settings Form.
 *
 * @package Drupal\google_analytics_counter\Form
 */
class GoogleAnalyticsCounterSettingsForm extends ConfigFormBase {

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface.
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
   * The Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Result processor.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginManager
   */
  protected $resultProcessorPluginManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs an instance of GoogleAnalyticsCounterSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface $app_manager
   *   Google Analytics Counter App Manager object.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface $message_manager
   *   Google Analytics Counter Message Manager object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginManager $resultProcessorPluginManager
   *   Result processor.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, GoogleAnalyticsCounterAppManagerInterface $app_manager, GoogleAnalyticsCounterMessageManagerInterface $message_manager, MessengerInterface $messenger, GoogleAnalyticsCounterResultProcessorPluginManager $resultProcessorPluginManager, Connection $database) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->appManager = $app_manager;
    $this->messageManager = $message_manager;
    $this->messenger = $messenger;
    $this->resultProcessorPluginManager = $resultProcessorPluginManager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('google_analytics_counter.app_manager'),
      $container->get('google_analytics_counter.message_manager'),
      $container->get('messenger'),
      $container->get('plugin.manager.google_analytics_counter_result_processor'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_analytics_counter_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['google_analytics_counter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $t_args = [
      ':href' => Url::fromUri('https://developers.google.com/analytics/devguides/reporting/data/v1/quotas')->toString(),
      '@href' => 'Limits and Quotas on API Requests',
    ];
    $form['cron_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum time to wait before fetching Google Analytics data (in minutes)'),
      '#default_value' => $config->get('general_settings.cron_interval'),
      '#min' => 0,
      '#max' => 10000,
      '#description' => $this->t('Google Analytics data is fetched and processed during cron. On the largest systems, cron may run every minute which could result in exceeding Google\'s quota policies. See <a href=:href target="_blank">@href</a> for more information. To bypass the minimum time to wait, set this value to 0.', $t_args),
      '#required' => TRUE,
    ];

    $form['chunk_to_fetch'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items to fetch from Google Analytics in one request'),
      '#default_value' => $config->get('general_settings.chunk_to_fetch'),
      '#min' => 1,
      '#max' => 10000,
      '#description' => $this->t('The number of items to be fetched from Google Analytics in one request.'),
      '#required' => TRUE,
    ];

    $form['cache_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Google Analytics query cache (in hours)'),
      '#description' => $this->t('Limit the time in hours before getting fresh data with the same query to Google Analytics. Minimum: 0 hours. Maximum: 730 hours (approx. one month).'),
      '#default_value' => $config->get('general_settings.cache_length') / 3600,
      '#min' => 0,
      '#max' => 730,
      '#required' => TRUE,
    ];

    $get_count = GoogleAnalyticsCounterHelper::getCount('queue');
    $t_arg = [
      '%queue_count' => $get_count,
    ];
    $form['queue_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue Time (in seconds)'),
      '#default_value' => $config->get('general_settings.queue_time'),
      '#min' => 1,
      '#max' => 10000,
      '#required' => TRUE,
      '#description' => $this->t('%queue_count items are in the queue. The number of items in the queue should be 0 after cron runs.', $t_arg) .
      '<br />' . $this->t('Having 0 items in the queue confirms that pageview counts are up to date. Increase Queue Time to process all the queued items during a single cron run. Default: 120 seconds.') .
      '<br /><strong>' . $this->t('Note:') . '</strong>' . $this->t('Changing the Queue Time will require that the cache to be cleared, which may take a minute after submission.'),
    ];

    $form['node_last_x_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Update pageviews for content created in the last X days'),
      '#default_value' => $config->get('general_settings.node_last_x_days'),
      '#min' => 0,
      '#description' => $this->t("If the processing queue is empty, then by default every published content will be refreshed for pageviews. If there's a huge amount of content, the constant refresh for thousands of content could take a while and even lead to timeouts and performance issues. With this setting, you can limit the process to update pageview count only for content that was created the provided day ago.<br>- Set empty to process every content for every cron run.<br>- Set 0 for lower limit to today 00:00,<br>- Set 1 for lower limit to yesterday 00:00,<br>- Set 2 for 2 days ago 00:00 etc."),
    ];

    $form['node_not_newer_than_x_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Update pageviews for content not newer than X days'),
      '#default_value' => $config->get('general_settings.node_not_newer_than_x_days'),
      '#min' => 0,
      '#description' => $this->t("Google Analytics 4 data processing can take up to 24 hours to finish for one day. Until the processing finishes, you could get 0 pageviews or even stale data which will be lower than the real viewcount. With this setting, you can limit saving view counts for new nodes to avoid saving stale data.<br>- Set empty to process every content.<br>- Set 0 to skip updating viewcount for nodes created today.<br>- Set 1 to skip updating viewcount for nodes created yesterday and today,<br>- Set 2 for skip updating viewcount for nodes created 2 days ago, yesterday and today etc."),
    ];

    $form['node_last_x_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('Update pageviews for the last X content'),
      '#default_value' => $config->get('general_settings.node_last_x_nid'),
      '#min' => 0,
      '#description' => $this->t("If the processing queue is empty, then by default every published content will be refreshed for pageviews. If there's a huge amount of content, the constant refresh for thousands of content could take a while and even lead to timeouts and performance issues. With this setting, you can limit the process to update pageview count only for the last few nodes.<br>- Set empty or zero to process every content for every cron run."),
    ];

    $form['query_data'] = [
      '#type' => 'details',
      '#title' => $this->t('Query data'),
      '#open' => TRUE,
    ];

    $form['query_data']['metric'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Metric'),
      '#description' => $this->t('The metric to include in the request. Single value only. <a href="@link" target="_blank">See metrics.</a><br><a href="@devtoollink" target="_blank">You can try it out and see result on the following page.</a>', [
        '@link' => 'https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema#metrics',
        '@devtoollink' => 'https://ga-dev-tools.google/ga4/query-explorer/',
      ]),
      '#default_value' => !empty($config->get('general_settings.metric')) ? $config->get('general_settings.metric') : 'screenPageViews',
    ];
    $form['query_data']['dimension'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dimension'),
      '#description' => $this->t('The dimension to include in the request. Single value only. For custom event dimension use customEvent:DIMENSIONID <a href="@link" target="_blank">See dimensions.</a><br><a href="@devtoollink" target="_blank">You can try it out and see result on the following page.</a>', [
        '@link' => 'https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema#dimensions',
        '@devtoollink' => 'https://ga-dev-tools.google/ga4/query-explorer/',
      ]),
      '#default_value' => !empty($config->get('general_settings.dimension')) ? $config->get('general_settings.dimension') : 'pagePath',
    ];
    $form['query_data']['result_processor'] = [
      '#type' => 'select',
      '#title' => $this->t('Result processor'),
      '#description' => $this->t('Queried results from Google Analytics may not be mapped 1:1 to nodes. Depending on the dimension or metric, extra logic needs to be executed to calcute which row of the result table maps to which content ID on this site. By default, pageviews are queired for each URLs, so you need to use the "URL Alias" processor to aggregate content pageviews for every URL alias and every language. Note that when you change it, you need to clear the pagepath - pageview table manually.'),
      '#default_value' => !empty($config->get('general_settings.result_processor')) ? $config->get('general_settings.result_processor') : 'url_alias',
      '#options' => $this->resultProcessorPluginManager->getAllPluginsLabels(),
    ];

    $start_date = [
      'custom' => $this->t('Custom dates'),
      'custom_day' => $this->t('Custom days ago'),
      'today' => $this->t('Today'),
      'yesterday' => $this->t('Yesterday'),
      '-1 week last sunday midnight' => $this->t('Last week'),
      'first day of previous month' => $this->t('Last month'),
      '7 days ago' => $this->t('Last 7 days'),
      '30 days ago' => $this->t('Last 30 days'),
      '3 months ago' => $this->t('Last 3 months'),
      '6 months ago' => $this->t('Last 6 months'),
      '1 year ago' => $this->t('Last 365 days'),
      'first day of last year' => $this->t('Last year'),
      '14 November 2005' => $this->t('Since Launch'),
    ];

    $form['query_data']['start_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Date range'),
      '#description' => $this->t('<a href="@link" target="_blank">Note that with GA4, the time you can store data has decreased to 2 or 14 months (based on your settings). See: GA4 data retention.</a>', [
        '@link' => 'https://support.google.com/analytics/answer/7667196?hl=en',
      ]),
      '#default_value' => !empty($config->get('general_settings.start_date')) ? $config->get('general_settings.start_date') : '30 days ago',
      '#required' => TRUE,
      '#options' => $start_date,
    ];

    $form['query_data']['custom_start_day'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom start day'),
      '#description' => $this->t('Set a custom start day for Google Analytics queries. 0 = today, 1 = yesterday, 2 = 2 days ago etc. If you leave it empty, November 2005 will be used.'),
      '#default_value' => $config->get('general_settings.custom_start_day'),
      '#states' => [
        'visible' => [
          ':input[name="start_date"]' => ['value' => 'custom_day'],
        ],
      ],
      '#min' => 0,
    ];

    $form['query_data']['custom_end_day'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom end day'),
      '#description' => $this->t('Set a custom end day for Google Analytics queries. 0 = today, 1 = yesterday, 2 = 2 days ago etc. If you leave it empty, yesterday will be used.'),
      '#default_value' => $config->get('general_settings.custom_end_day'),
      '#states' => [
        'visible' => [
          ':input[name="start_date"]' => ['value' => 'custom_day'],
        ],
      ],
      '#min' => 0,
    ];

    $form['query_data']['custom_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Custom start date'),
      '#description' => $this->t('Set a custom start date for Google Analytics queries. If you leave it empty, November 2005 will be used.'),
      '#default_value' => $config->get('general_settings.custom_start_date'),
      '#states' => [
        'visible' => [
          ':input[name="start_date"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['query_data']['custom_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Custom end date'),
      '#description' => $this->t('Set a custom end date for Google Analytics queries. If you leave it empty, yesterday will be used.'),
      '#default_value' => $config->get('general_settings.custom_end_date'),
      '#states' => [
        'visible' => [
          ':input[name="start_date"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['actions']['clear_queue'] = [
      '#type' => 'link',
      '#title' => $this->t('Clear queue'),
      '#url' => Url::fromRoute('google_analytics_counter.confirm_clear_queue'),
      '#attributes' => [
        'data-dialog-type' => 'modal',
        'data-dialog-options' => '{"width":800}',
        'class' => [
          'use-ajax',
          'button',
          'button--danger',
        ],
      ],
      '#weight' => 99,
    ];

    $form['actions']['clear_pageview_table'] = [
      '#type' => 'link',
      '#title' => $this->t('Clear pagepath - pageview table'),
      '#url' => Url::fromRoute('google_analytics_counter.confirm_clear_page_path_delete'),
      '#attributes' => [
        'data-dialog-type' => 'modal',
        'data-dialog-options' => '{"width":800}',
        'class' => [
          'use-ajax',
          'button',
          'button--danger',
        ],
      ],
      '#weight' => 100,
    ];

    // Attach the library for pop-up dialogs/modals.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $current_queue_time = $config->get('general_settings.queue_time');
    $current_chunk_to_fetch = $config->get('general_settings.chunk_to_fetch');

    $values = $form_state->getValues();

    $end_date = $this->setEndDate($values);

    $config
      ->set('general_settings.cron_interval', $values['cron_interval'])
      ->set('general_settings.chunk_to_fetch', $values['chunk_to_fetch'])
      ->set('general_settings.cache_length', $values['cache_length'] * 3600)
      ->set('general_settings.queue_time', $values['queue_time'])
      ->set('general_settings.node_last_x_days', $values['node_last_x_days'])
      ->set('general_settings.node_not_newer_than_x_days', $values['node_not_newer_than_x_days'])
      ->set('general_settings.node_last_x_nid', $values['node_last_x_nid'])
      ->set('general_settings.start_date', $values['start_date'])
      ->set('general_settings.end_date', $end_date)
      ->set('general_settings.custom_start_date', $values['custom_start_date'])
      ->set('general_settings.custom_end_date', $values['custom_end_date'])
      ->set('general_settings.custom_start_day', $values['custom_start_day'])
      ->set('general_settings.custom_end_day', $values['custom_end_day'])
      ->set('general_settings.metric', $values['metric'])
      ->set('general_settings.dimension', $values['dimension'])
      ->set('general_settings.result_processor', $values['result_processor'])
      ->save();

    // If the queue time has change the cache needs to be cleared.
    if ($current_queue_time != $values['queue_time']) {
      drupal_flush_all_caches();
      $this->messenger->addMessage($this->t('Caches cleared.'));
    }

    if ($current_chunk_to_fetch !== (int) $values['chunk_to_fetch']) {
      $this->database->truncate('google_analytics_counter')->execute();
      $this->messenger->addMessage($this->t('Queue cleared.'));
    }

    Cache::invalidateTags(['google_analytics_counter_data']);
    parent::submitForm($form, $form_state);
  }

  /**
   * Sets the end date into configuration.
   *
   * @param array $values
   *   The array of values.
   *
   * @return string
   *   The return date.
   */
  protected function setEndDate(array $values) {

    $end_date = '';
    switch ($values['start_date']) {
      case 'today':
        $end_date = 'today';
        break;

      case 'yesterday':
      case '14 November 2005':
        $end_date = 'yesterday';
        break;

      case '-1 week last sunday midnight':
        $end_date = '-1 week next saturday';
        break;

      case 'first day of previous month':
        $end_date = 'last day of previous month';
        break;

      case '7 days ago':
        $end_date = '7 days ago +6 days';
        break;

      case '30 days ago':
        $end_date = '30 days ago +30 days -1 day';
        break;

      case '3 months ago':
        $end_date = '3 months ago +3 months -1 day';
        break;

      case '6 months ago':
        $end_date = '6 months ago +6 months - 1 day';
        break;

      case 'first day of last year':
        $end_date = 'last day of last year';
        break;

      case '1 year ago':
        $end_date = '1 year ago +1 year -1 day';
        break;

      default:
        break;
    }

    return $end_date;
  }

}
