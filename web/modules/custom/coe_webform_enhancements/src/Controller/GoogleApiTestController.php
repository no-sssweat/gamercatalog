<?php

namespace Drupal\coe_webform_enhancements\Controller;

use Drupal\coe_webform_enhancements\Service\GoogleSheetsService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * Testing Google API integrations.
 */
class GoogleApiTestController extends ControllerBase {

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Google API service client.
   */
  protected $googleSheetsService;

  /**
   * WebformEmbedController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type manager.
   * @param \Drupal\coe_webform_enhancements\Service\GoogleSheetsService $google_sheets_service
   *   The google api service client service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GoogleSheetsService $google_sheets_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->googleSheetsService = $google_sheets_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('coe_webform_enhancements.google_sheets')
    );
  }

  /**
   * Testing Google API integrations.
   */
  public function build($row_id) {
    $this->googleSheetsService->setGoogleApiServiceClient('coe_wfo_development');

    // Remove a row from a spreadsheet.
    $spreadsheet_id = '1yRi64oF6mPCGhwwnGTe7-Pv9NVoLKvUl9ISSIJCxBa8';
    $spreadsheet = $this->googleSheetsService->getSpreadsheet($spreadsheet_id);
    $this->googleSheetsService->removeRow($spreadsheet_id, $row_id);

    $build = [
      '#markup' => 'Google API Testing',
    ];

    return $build;
  }

}
