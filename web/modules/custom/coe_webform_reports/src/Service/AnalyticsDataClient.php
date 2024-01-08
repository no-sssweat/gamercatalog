<?php

namespace Drupal\coe_webform_reports\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
//use Google\Analytics\Data\V1beta\Filter\InListFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Psr\Log\LoggerInterface;

/**
 * Provides an AnalyticsDataClient service.
 */
class AnalyticsDataClient {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Google API Service Client.
   *
   * @var \Drupal\google_api_service_client\ClientInterface
   */
  protected $googleApiClient;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AnalyticsDataClient object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param $google_api_client
   *   The Google API Service Client.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    $google_api_client,
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory
  ) {
    $this->googleApiClientStorage = $entity_type_manager->getStorage('google_api_service_client');
    $this->googleApiClient = $google_api_client;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
  }

  /**
   * Get webform view count from Google Analytics 4.
   *
   * @return integer
   *   A sample result.
   */
  public function getViewCount($webform_id) {

    $google_api_service_client = $this->getGA4ServiceClient();

    if ($google_api_service_client) {
      // Set the account.
      $this->googleApiClient->setGoogleApiClient($google_api_service_client);
      $creds = $this->googleApiClient->googleApiServiceClient->getAuthConfig();
      $propertyId = $this->configFactory->get('coe_webform_reports.settings')->get('ga4_property_id');

      $client = new BetaAnalyticsDataClient(['credentials' => $creds]);

      // Create the GA4 property ID.
      $property = "properties/$propertyId";

      $dateRange = new DateRange();
      $dateRange->setStartDate('2023-10-01');
      $dateRange->setEndDate('today');

      $dimensions = [new Dimension(['name' => 'contentId'])];
      $metrics = [new Metric(['name' => 'eventCount'])];

  //      // Will return the sum of those two
  //      $dimensionFilter = new FilterExpression([
  //        'filter' => new Filter([
  //          'field_name' => 'pagePath',
  //          'in_list_filter' => new inListFilter([
  //            'values' => ['/form/test', '/form/contact']
  //          ])
  //        ])
  //      ]);

      $dimensionFilter = new FilterExpression([
        'filter' => new Filter([
          'field_name' => 'contentId',
          'string_filter' => new stringFilter([
            'value' => $webform_id,
          ])
        ])
      ]);

      try {
        $response = $client->runReport([
          'property' => 'properties/' . $propertyId,
          'dateRanges' => [$dateRange],
  //    'dimensions' => $dimensions,
          'metrics' => $metrics,
          'dimensionFilter' => $dimensionFilter,
        ]);
      }
      catch (\Exception $e) {
        $client->close();
        $this->logger->error('Error retrieving view count from GA4 API: @message', [
          '@message' => $e->getMessage(),
        ]);
        return;
      }

      if ($response) {
        foreach ($response->getRows() as $row) {
          $count = $row->getMetricValues()[0]->getValue();
        }
        $client->close();
        return $count ?? 0;
      }
    }

  }

  /**
   * Gets the Google Analytics 4 (GA4) service client entity.
   *
   * This method retrieves entities from the google_api_service_client
   * storage and returns the first entity that has the 'analyticsdata' service
   * enabled.
   *
   * @return \Drupal\google_api_service_client\Entity\GoogleApiServiceClientEntity|NULL
   *   The GA4 service client entity if found; otherwise, NULL.
   */
  public function getGA4ServiceClient() {
    $entities = $this->googleApiClientStorage->loadMultiple();

    foreach ($entities as $entity) {
      // Check if the entity has 'analyticsdata' service enabled.
      if (!empty($entity->getServices()['analyticsdata'])) {
        // Return the first entity that matches the criteria.
        return $entity;
      }
    }

    // Return NULL if no matching entity is found.
    return NULL;
  }

}
