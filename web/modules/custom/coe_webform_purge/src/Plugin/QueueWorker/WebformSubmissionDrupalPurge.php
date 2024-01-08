<?php

namespace Drupal\coe_webform_purge\Plugin\QueueWorker;

use Drupal\coe_webform_purge\Service\WebformSubmissionPurge;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
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
   * The webform submission purge service.
   *
   * @var \Drupal\coe_webform_purge\Service\WebformSubmissionPurge
   */
  protected $webformSubmissionPurge;

  /**
   * Constructs a WebformSubmissionCleaner worker.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   * @param \Drupal\coe_webform_purge\Service\WebformSubmissionPurge $webform_submission_purge
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              QueueFactory $queue_factory,
                              WebformSubmissionPurge $webform_submission_purge) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queueFactory = $queue_factory;
    $this->webformSubmissionPurge = $webform_submission_purge;
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
      $container->get('coe_webform_purge.submission')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $wsid = isset($data->wsid) && $data->wsid ? $data->wsid : NULL;
    if (!$wsid) {
      throw new \Exception('Missing Webform Submission ID');
    }

    $webform_id = $data->webform_id;
    $needs_purging = $this->webformSubmissionPurge->needsPurging($webform_id);
    if ($needs_purging) {
      $config = $this->webformSubmissionPurge->getConfig($webform_id);
      $freq_number = $config->get('frequency_number');
      $freq_type = $config->get('frequency_type');
      // Check if the item is scheduled for purging.
      $current_time = time();
      $webform_created_date = $data->created;
      $webform_deletion_date = strtotime("+$freq_number $freq_type", $webform_created_date);
      // Delete webform when it's time.
      if ($current_time >= $webform_deletion_date) {
        $this->webformSubmissionPurge->deleteWebformSubmissionFromDrupal($wsid);
      }
      else {
        // Not time to delete yet, re-add to the queue.
        $queue = $this->queueFactory->get('webform_submission_drupal_purge');
        $queue->createItem($data);
      }
    }
  }

}
