<?php

namespace Drupal\coe_webform_purge\Plugin\QueueWorker;

use Drupal\coe_webform_enhancements\Service\GoogleSheetsService;
use Drupal\coe_webform_purge\Service\WebformSubmissionPurge;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A worker plugin that removes a Webform Submission from the database.
 *
 * @QueueWorker(
 *   id = "webform_submission_sheets_purge",
 *   title = @Translation("Webform Submission Sheets Purge"),
 *   cron = {"time" = 10}
 * )
 */
class WebformSubmissionSheetsPurge extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The webform submission purge service.
   *
   * @var \Drupal\coe_webform_purge\Service\WebformSubmissionPurge
   */
  protected $webformSubmissionPurge;

  /**
   * The Google API Service Client.
   *
   * @var \Drupal\google_api_service_client\ClientInterface
   */
  protected $googleApiClient;

  /**
   * The google sheets service.
   *
   * @var \Drupal\coe_webform_enhancements\Service\GoogleSheetsService
   */
  protected $googleSheetsService;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs a WebformSubmissionCleaner worker.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   * @param \Drupal\coe_webform_purge\Service\WebformSubmissionPurge $webform_submission_purge
   * @param \Drupal\coe_webform_enhancements\Service\GoogleSheetsService $google_sheets_service
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    QueueFactory $queue_factory,
    WebformSubmissionPurge $webform_submission_purge,
    GoogleSheetsService $google_sheets_service,
    LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queueFactory = $queue_factory;
    $this->webformSubmissionPurge = $webform_submission_purge;
    $this->googleSheetsService = $google_sheets_service;
    $this->logger = $logger;
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
      $container->get('coe_webform_purge.submission'),
      $container->get('coe_webform_enhancements.google_sheets'),
      $container->get('logger.factory')->get('coe_webform_purge')
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
        $spreadsheet_id = $data->spreadsheet_id;
        $google_sheets_authenticated = $this->googleSheetsService->googleSheetsAuth($webform_id);
        if ($google_sheets_authenticated) {
          // Try to delete the row
          $row_index = $this->googleSheetsService->getRowIndexByMetadata($spreadsheet_id, 'submission_id', $wsid);
          if (!is_null($row_index)) {
            $response = $this->googleSheetsService->removeRow($spreadsheet_id, $row_index);
            if ($response) {
              $this->logger->notice($this->t('@wsid was successfully deleted from Google Sheet with ID of: @id', [
                '@wsid' => $wsid,
                '@id' => $spreadsheet_id,
              ]));
              // Also delete from Drupal
              $this->webformSubmissionPurge->deleteWebformSubmissionFromDrupal($wsid);
            }
            else {
              // something went wrong with the Google Sheets API, reschedule by re-adding to queue
              $queue = $this->queueFactory->get('webform_submission_sheets_purge');
              $queue->createItem($data);
              $this->logger->notice($this->t('@wsid was failed to deleted from Google Sheet with ID of: @id', [
                '@wsid' => $wsid,
                '@id' => $spreadsheet_id,
              ]));
            }
          }
        }
        else {
          // Failed to authenticate, re-add to queue to try again
          $queue = $this->queueFactory->get('webform_submission_sheets_purge');
          $queue->createItem($data);
        }
      }
      else {
        // Not time to delete yet, re-add to the queue.
        $queue = $this->queueFactory->get('webform_submission_sheets_purge');
        $queue->createItem($data);
      }
    }
  }

}
