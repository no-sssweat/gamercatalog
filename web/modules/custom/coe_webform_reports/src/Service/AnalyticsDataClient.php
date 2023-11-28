<?php

// modules/custom/your_module/src/Service/AnalyticsDataClient.php

namespace Drupal\coe_webform_reports\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\google_api_service_client\ClientInterface;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\InListFilter;
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
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Google API Service Client.
   *
   * @var ClientInterface
   */
  protected $googleApiClient;

  /**
   * The logger.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AnalyticsDataClient object.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param $google_api_client
   *   The Google API Service Client.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $google_api_client, LoggerInterface $logger) {
    $this->googleApiClientStorage = $entity_type_manager->getStorage('google_api_service_client');
    $this->googleApiClient = $google_api_client;
    $this->logger = $logger;
  }

  /**
   * Example service method.
   *
   * @return integer
   *   A sample result.
   */
  public function getViewCount($webform_path) {

      $google_api_service_client = $this->googleApiClientStorage->load('ga4');
      // Set the account.
      $this->googleApiClient->setGoogleApiClient($google_api_service_client);
      $creds = $this->googleApiClient->googleApiServiceClient->getAuthConfig();

      // Replace with your view ID, for example XXXX.
      $propertyId = "413264721";

      $client = new BetaAnalyticsDataClient(['credentials' => $creds]);

      // Create the GA4 property ID.
      $property = "properties/$propertyId";

      $dateRange = new DateRange();
      $dateRange->setStartDate('2023-10-01');
      $dateRange->setEndDate('today');

      $dimensions = [new Dimension(['name' => 'contentId'])];
      $metrics = [new Metric(['name' => 'screenPageViews'])];

      // Set the specific contentId you want to filter by.
      $specificContentId = 'webform-submission-contact-add-form';

//      // Will return the sum of those two
//      $dimensionFilter = new FilterExpression([
//        'filter' => new Filter([
//          'field_name' => 'pagePath',
//          'in_list_filter' => new inListFilter([
//            'values' => ['/form/test', '/form/contact']
//          ])
//        ])
//      ]);

//      $dimensionFilter = new FilterExpression([
//        'filter' => new Filter([
//          'field_name' => 'contentId',
//          'string_filter' => new stringFilter([
//            'value' => 'webform-submission-contact-add-form'
//          ])
//        ])
//      ]);

      $dimensionFilter = new FilterExpression([
        'filter' => new Filter([
          'field_name' => 'pagePath',
          'string_filter' => new stringFilter([
            'value' => $webform_path,
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
        return $count;
      }

  }

}
