<?php

namespace Drupal\coe_webform_reports\Plugin\QueueWorker;

use Drupal\coe_webform_reports\Service\AnalyticsDataClient;
use Drupal\coe_webform_reports\Service\ViewCountService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A worker plugin that removes a Webform Submission from the database.
 *
 * @QueueWorker(
 *   id = "coe_webform_reports_view_count",
 *   title = @Translation("Fetch view count from GA4 and save"),
 *   cron = {"time" = 10}
 * )
 */
class WebformViewCount extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The view count service.
   *
   * @var Drupal\coe_webform_reports\Service\ViewCountService
   */
  protected $viewCountService;

  /**
   * The view count service.
   *
   * @var Drupal\coe_webform_reports\Service\AnalyticsDataClient
   */
  protected $analyticsDataClient;

  /**
   * Constructs a WebformSubmissionCleaner worker.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param QueueFactory $queue_factory
   * @param ViewCountService $view_count_service
   * @param AnalyticsDataClient $analytics_data_client
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              QueueFactory $queue_factory, EntityTypeManagerInterface $entity_type_manager,
                              ConfigFactoryInterface $config_factory, ViewCountService $view_count_service,
                              AnalyticsDataClient $analytics_data_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queueFactory = $queue_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->viewCountService = $view_count_service;
    $this->analyticsDataClient = $analytics_data_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('queue'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('coe_webform_reports.view_count'),
      $container->get('coe_webform_reports.analytics_data_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $wid = isset($data->id) && $data->id ? $data->id : NULL;
    if (!$wid) {
      throw new \Exception('Missing Webform Submission ID');
      return;
    }
    $webform_path = $data->path;
    $view_count = $this->analyticsDataClient->getViewCount($webform_path);
    if ($view_count) {
      $this->viewCountService->setViewCount($wid, $view_count);
    }
    else {
      // re-schedule
    }
  }
}
