<?php

namespace Drupal\coe_webform_purge\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A worker plugin that removes a Webform Submission from the database.
 *
 * @QueueWorker(
 *   id = "webform_submission_drupal_purge",
 *   title = @Translation("Webform Submission Drupal Purge"),
 *   cron = {"time" = 10}
 * )
 */
class WebformSubmissionDrupalPurge extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * Constructs a WebformSubmissionCleaner worker.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              QueueFactory $queue_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queueFactory = $queue_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
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
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $sid = isset($data->id) && $data->id ? $data->id : NULL;
    if (!$sid) {
      throw new \Exception('Missing Webform Submission ID');
    }

    // Check if the item is scheduled for execution.
    $current_time = time();
    $webform_created_date = $data->created;
    $config_name = 'coe_webform_purge.' . $data->webform_id;
    $config = $this->configFactory->get($config_name);
    if ($config->get('purging_enabled')) {
      $freq_number = $config->get('frequency_number');
      $freq_type = $config->get('frequency_type');
      $webform_deletion_date = strtotime("+$freq_number $freq_type", $webform_created_date);
      // Delete webform when it's time.
      if ($current_time >= $webform_deletion_date) {
        $webform_submission = $this->entityTypeManager
          ->getStorage('webform_submission')
          ->load($sid);
        if ($webform_submission instanceof WebformSubmissionInterface) {
          $webform_submission->delete();
        }
      }
      else {
        // Not time to delete yet, re-add to the queue.
        $queue = $this->queueFactory->get('webform_submission_drupal_purge');
        $queue->createItem($data);
      }
    }
  }

}
