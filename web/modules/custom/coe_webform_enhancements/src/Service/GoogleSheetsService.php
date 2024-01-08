<?php

namespace Drupal\coe_webform_enhancements\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\google_api_client\Entity\GoogleApiServiceClient;
use Drupal\google_api_client\Entity\GoogleApiClient;
use Drupal\google_api_client\Service\GoogleApiServiceClientService;
use Drupal\google_api_client\Service\GoogleApiClientService;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Sheets;
use Google\Exception as GoogleException;
use Google\Service\Sheets\AddNamedRangeRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\DeveloperMetadata;
use Google\Service\Sheets\CreateDeveloperMetadataRequest;
use Google\Service\Sheets\DataFilter;
use Google\Service\Sheets\DeveloperMetadataLookup;
use Google\Service\Sheets\NamedRange;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\SearchDeveloperMetadataRequest;
use Google\Service\Sheets\ValueRange;

/**
 * Provides a service that simplifies Google Sheets API operations.
 */
class GoogleSheetsService {

   use StringTranslationTrait;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Google API service client service.
   *
   * @var \Drupal\google_api_client\Service\GoogleApiServiceClientService
   */
  protected $googleApiServiceClientService;

  /**
   * Google API client service.
   *
   * @var \Drupal\google_api_client\Service\GoogleApiClientService
   */
  protected $googleApiClientService;

  /**
   * Google Sheets service.
   *
   * @var \Google\Service\Sheets
   */
  protected $sheetsService;

  /**
   * Google Drive service.
   *
   * @var \Google\Service\Drive
   */
  protected $driveService;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * GoogleSheetsService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type manager.
   * @param \Drupal\google_api_client\Service\GoogleApiServiceClientService
   *   The Google API client for use with service account.
   * @param \Drupal\google_api_client\Service\GoogleApiClientService
   *   The Google API client for use with OAuth.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GoogleApiServiceClientService $google_api_service_client, GoogleApiClientService $google_api_client_service, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->googleApiServiceClientService = $google_api_service_client;
    $this->googleApiClientService = $google_api_client_service;
    $this->logger = $logger->get('coe_webform_enhancements');
    $this->messenger = $messenger;
  }

  /**
   * Sets the GoogleApiServiceClient for the Sheets and Drive services.
   *
   * @param string $service_client_id
   *   The ID of a GoogleApiServiceClient.
   */
  public function setGoogleApiServiceClient($service_client_id) {
    $this->googleApiServiceClientService->setGoogleApiClient(GoogleApiServiceClient::load($service_client_id));
    $this->sheetsService = new Sheets($this->googleApiServiceClientService->googleClient);
    $this->driveService = new Drive($this->googleApiServiceClientService->googleClient);
  }

  /**
   * Sets the GoogleApiClient for the Sheets and Drive services.
   *
   * @param string $client_id
   *   The ID of a GoogleApiClient.
   */
  public function setGoogleApiClient($client_id) {
    $this->googleApiClientService->setGoogleApiClient(GoogleApiClient::load($client_id));
    $this->sheetsService = new Sheets($this->googleApiClientService->googleClient);
    $this->driveService = new Drive($this->googleApiClientService->googleClient);
  }

  /**
   * Add a new Google Spreadsheet.
   *
   * @param array $properties
   *   An array of properties for the Spreadsheet.
   * @param array $options
   *   An optional array of options for creating the Spreadsheet.
   * @param array $metadata
   *   An optional array of metadata to attach to the Spreadsheet.
   *
   * @return \Google\Service\Sheets\Spreadsheet|null
   *   The newly created Spreadsheet resource.
   */
  public function addSpreadsheet($properties, $options = [], $metadata = []) {
    try {
      // Define the Spreadsheet.
      $spreadsheet = new Spreadsheet([
        'properties' => new SpreadsheetProperties($properties),
      ]);

      // Create the new Shreadsheet.
      $spreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet, $options);

      // Add metadata to the Spreadsheet.
      $batch_update_response = $this->addSpreadsheetMetadata($spreadsheet->getSpreadsheetId(), $metadata);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $spreadsheet ?? NULL;
  }

  /**
   * Get a Spreadsheet by ID.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   *
   * @return \Google\Service\Sheets\Spreadsheet|null
   *   The requested Spreadsheet resource.
   */
  public function getSpreadsheet($spreadsheet_id) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $spreadsheet ?? NULL;
  }

  /**
   * Delete a Spreadsheet by ID.
   *
   * @return mixed|null
   */
  public function deleteSpreadsheet($spreadsheet_id) {
    try {
      $result = $this->driveService->files->delete($spreadsheet_id);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Shares a Drive file with specified permissions and parameters.
   *
   * @param string $file_id
   *   The ID of the drive file to be shared.
   * @param array $permission
   *   An array of permissions to be set for the shared spreadsheet.
   * @param array $parameters
   *   Additional parameters for sharing the spreadsheet.
   *
   * @return \Google\Service\Drive\Permission|null
   */
  public function createPermission($file_id, $permission = [], $parameters = []) {
    try {
      $permission = new Permission($permission);
      $updated_permission = $this->driveService->permissions->create($file_id, $permission, $parameters);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $updated_permission ?? NULL;
  }

  /**
   * Ungrant permission to access this Drive file.
   *
   * @param string $file_id
   *   The ID of the drive file.
   * @param string $permission_id
   *   The ID of the permission to be removed.
   *
   * @return mixed|null
   */
  public function deletePermission($file_id, $permission_id) {
    try {
      $result = $this->driveService->permissions->delete($file_id, $permission_id);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Ungrant permission to access this Drive file by email address.
   *
   * @param string $file_id
   *   The ID of the drive file.
   * @param string $email
   *   The email address of the user to be removed.
   * @param string $role
   *   The role of the user to be removed.
   *
   * @return mixed|null
   */
  public function deletePermissionByEmail($file_id, $email, $role) {
    try {
      // Get all permissions for the spreadsheet.
      $permissions = $this->getDrivePermissions($file_id);

      // Find the permission with the matching email address.
      foreach ($permissions as $permission) {
        if ($permission->getEmailAddress() == $email && $permission->getRole() == $role) {
          // Delete the permission.
          $result = $this->deletePermission($file_id, $permission->getId());
          break;
        }
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Get permissions for a Drive file or directory.
   *
   * @param string $file_id
   *   The ID of the file or directory.
   *
   * @return array|null
   */
  public function getDrivePermissions($file_id) {
    try {
      $permissions = $this->driveService->permissions->listPermissions($file_id)->getPermissions();
      $permissions_array = array_map(function ($permission) use ($file_id) {
        return $this->driveService->permissions->get($file_id, $permission->getId(), ['fields' => 'id, emailAddress, role, type']);
      }, $permissions);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $permissions_array ?? NULL;
  }

  /**
   * Move a spreadsheet to a Google Drive directory.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param string $folder_id
   *   The ID of the folder to move the Spreadsheet to.
   *
   * @return \Google\Service\Drive\DriveFile|null
   *  The File resource that was moved.
   */
  public function moveSpreadsheetToDirectory($spreadsheet_id, $folder_id) {
    try {
      $file = $this->driveService->files->get($spreadsheet_id, ['fields' => 'parents']);
      $file_existing_parents = implode(',', $file->parents);
      $file->setParents($folder_id);

      $updated_file = $this->driveService->files->update($spreadsheet_id, $file, [
        'addParents' => $folder_id,
        'removeParents' => $file_existing_parents,
        'fields' => 'id, parents',
      ]);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $updated_file ?? NULL;
  }

  /**
   * Get a list of all Spreadsheet IDs.
   *
   * @return array|null
   *   An array of spreadsheet_ids.
   */
  public function getSpreadsheetIds() {
    try {
      // We need to query for all Spreadsheets through the Google Drive Service, for some reason.
      $files_list = $this->driveService->files->listFiles([
        'q' => "mimeType='application/vnd.google-apps.spreadsheet'",
        'fields' => 'files(id, name)',
      ]);

      // Prepare the array of file/Spreadsheet IDs.
      $spreadsheet_id_array = array_map(function ($file) {
        return $file->getId();
      }, $files_list->getFiles());
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $spreadsheet_id_array ?? NULL;
  }

  /**
   * Attach metadata to a Spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param array $metadata
   *   An array of metadata as key => value.
   *
   * @return array|null
   *   An array of BatchUpdateSpreadsheetResponse for each CreateDeveloperMetadataRequest.
   */
  public function addSpreadsheetMetadata($spreadsheet_id, $metadata = []) {
    try {
      $create_developer_metadata_response_array = array_map(function ($metadata_key, $metadata_value) use ($spreadsheet_id) {
        // Define the DeveloperMetadata.
        $developer_metadata = new DeveloperMetadata([
          'location' => [
            'spreadsheet' => TRUE,
          ],
          'metadataKey' => $metadata_key,
          'metadataValue' => $metadata_value,
          'visibility' => 'DOCUMENT',
        ]);

        // Define the BatchUpdateSpreadsheet request.
        $batch_update_request = new BatchUpdateSpreadsheetRequest([
          'requests' => [
            new Request([
              'createDeveloperMetadata' => new CreateDeveloperMetadataRequest([
                'developerMetadata' => $developer_metadata,
              ]),
            ]),
          ],
        ]);

        // Execute batchUpdate for the CreateDeveloperMetadataRequests.
        return $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batch_update_request);
      }, array_keys($metadata), array_values($metadata));
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $create_developer_metadata_response_array ?? NULL;
  }

  /**
   * Get a list of metadata for a Spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   *
   * @return array|null
   *   An array of metadata, includes: metadata_id, metadata_key, metadata_value
   */
  public function getSpreadsheetMetadata($spreadsheet_id) {
    try {
      // DeveloperMetadataLookup data filter with no criteria.
      $data_filter = new DataFilter([
        'developerMetadataLookup' => new DeveloperMetadataLookup(),
      ]);

      // Prepare a SearchDeveloperMetadataRequest.
      $search_developer_metadata_request = new SearchDeveloperMetadataRequest([
        'dataFilters' => [$data_filter],
      ]);

      // Get SearchDeveloperMetaRequest response.
      $search_developer_metadata_response = $this->sheetsService->spreadsheets_developerMetadata->search($spreadsheet_id, $search_developer_metadata_request);

      // Prepare the array of all metadata for this Spreadsheet.
      $metadata_array = array_map(function ($matched_metadata) {
        return [
          'metadata_id' => $matched_metadata->getDeveloperMetadata()->getMetadataId(),
          'metadata_key' => $matched_metadata->getDeveloperMetadata()->getMetadataKey(),
          'metadata_value' => $matched_metadata->getDeveloperMetadata()->getMetadataValue(),
        ];
      }, $search_developer_metadata_response->getMatchedDeveloperMetadata());
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $metadata_array ?? NULL;
  }

  /**
   * Append a new row to a Google Spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param array $values
   *   The values to append as a new row.
   *
   * @return \Google\Service\Sheets\AppendValuesResponse|null
   *   The response from the append request.
   */
  public function appendRow($spreadsheet_id, $values = [], $metadata = []) {
    try {
      $value_range = new ValueRange([
        'values' => [$values],
      ]);

      $params = [
        'valueInputOption' => 'RAW',
      ];

      $append_values_response = $this->sheetsService->spreadsheets_values->append($spreadsheet_id, 'Sheet1', $value_range, $params);

      $row_index = $append_values_response->getUpdates()->getUpdatedRange();
      $batch_update_response = $this->addRowMetadata($spreadsheet_id, 0, $row_index, $metadata);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $append_values_response ?? NULL;
  }

  /**
   * Attach metadata to a row.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param int $sheet_id
   *   The ID of the sheet.
   * @param int $row_index
   *   The index of the row.
   * @param array $metadata
   *  An array of metadata as key => value.
   *
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse[]|null
   *   The responses from the batch update requests.
   */
  public function addRowMetadata($spreadsheet_id, $sheet_id, $row_index, $metadata = []) {
    try {
      $create_developer_metadata_response_array = array_map(function ($metadata_key, $metadata_value) use ($spreadsheet_id, $sheet_id, $row_index) {
        // Define the DeveloperMetadata.
        $developer_metadata = new DeveloperMetadata([
          'location' => [
            'dimensionRange' => [
              'sheetId' => $sheet_id,
              'dimension' => 'ROWS',
              'startIndex' => $row_index - 1,
              'endIndex' => $row_index
            ],
          ],
          'metadataKey' => $metadata_key,
          'metadataValue' => $metadata_value,
          'visibility' => 'DOCUMENT',
        ]);

        // Define the BatchUpdateSpreadsheet request.
        $batch_update_request = new BatchUpdateSpreadsheetRequest([
          'requests' => [
            new Request([
              'createDeveloperMetadata' => new CreateDeveloperMetadataRequest([
                'developerMetadata' => $developer_metadata,
              ]),
            ]),
          ],
        ]);

        // Execute batchUpdate for the CreateDeveloperMetadataRequests.
        $batch_update_response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batch_update_request);

        return $batch_update_response;
      }, array_keys($metadata), array_values($metadata));
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $create_developer_metadata_response_array ?? NULL;
  }

  /**
   * Retrieves metadata values by key from all rows in a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param string $metadata_key
   *   The key of the metadata to search for.
   *
   * @return array|null
   *   The metadata values matching the given key, or NULL if an error occurs.
   */
  public function getMetadataValuesByKey($spreadsheet_id, $metadata_key) {
    try {
      // Define the DeveloperMetadataLookup.
      $data_filter = new DataFilter([
        'developerMetadataLookup' => new DeveloperMetadataLookup([
          'metadataKey' => $metadata_key,
          'locationType' => 'ROW',
          'visibility' => 'DOCUMENT'
        ]),
      ]);

      // Prepare a SearchDeveloperMetadataRequest.
      $search_request = new SearchDeveloperMetadataRequest([
        'dataFilters' => [$data_filter],
      ]);

      // Execute the search request.
      $search_response = $this->sheetsService->spreadsheets_developerMetadata->search($spreadsheet_id, $search_request);

      // Extract the matched metadata.
      $matched_metadata = $search_response->getMatchedDeveloperMetadata();

      // Extract the metadata values from the matched metadata.
      $metadata_values = array_map(function ($metadata) {
        return $metadata->getDeveloperMetadata()->getMetadataValue();
      }, $matched_metadata);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $metadata_values ?? NULL;
  }

  /**
   * Retrieves the row index based on the specified metadata key and value in a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the Google Sheets spreadsheet.
   * @param string $metadata_key
   *   The key of the metadata to search for.
   * @param string $metadata_value
   *   The value of the metadata to search for.
   *
   * @return int|null
   *   The row index if a match is found, or NULL if no match is found or an error occurs.
   */
  public function getRowIndexByMetadata($spreadsheet_id, $metadata_key, $metadata_value) {
    try {
      // Define the DeveloperMetadataLookup.
      $data_filter = new DataFilter([
        'developerMetadataLookup' => new DeveloperMetadataLookup([
          'metadataKey' => (string) $metadata_key,
          'metadataValue' => (string) $metadata_value,
          'locationType' => 'ROW',
          'visibility' => 'DOCUMENT'
        ]),
      ]);

      // Prepare a SearchDeveloperMetadataRequest.
      $search_request = new SearchDeveloperMetadataRequest([
        'dataFilters' => [$data_filter],
      ]);

      // Execute the search request.
      $search_response = $this->sheetsService->spreadsheets_developerMetadata->search($spreadsheet_id, $search_request);

      // Extract the matched metadata.
      $matched_metadata = $search_response->getMatchedDeveloperMetadata();

      // Extract the row index from the first matched metadata.
      if (!empty($matched_metadata[0])) {
        $row_index = $matched_metadata[0]->getDeveloperMetadata()->getLocation()->getDimensionRange()->getStartIndex();
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $row_index ?? NULL;
  }

  /**
   * Retrieves the values of a specific row in a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param int $row_index
   *   The index of the row to retrieve.
   *
   * @return array|null
   *   The values of the row, or NULL if an error occurred.
   */
  public function getRowValues($spreadsheet_id, $row_index) {
    try {
      // Need to increase by one to get the correct row index value.
      $row_index++;
      // Define the range of the row.
      $range = 'Sheet1!' . $row_index . ':' . $row_index;

      // Get the values of the row.
      $response = $this->sheetsService->spreadsheets_values->get($spreadsheet_id, $range);

      // Extract the values.
      $values = $response->getValues();
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $values ?? NULL;
  }

  /**
   * Retrieves the values of a specific row in a Google Sheets spreadsheet using named ranges.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param int $row_index
   *   The index of the row to retrieve.
   *
   * @return array|null
   *   An associative array mapping named ranges to their corresponding values in the row, or NULL if an error occurs.
   */
  public function getRowNamedRangeValues($spreadsheet_id, $row_index) {
    try {
      // Get all named ranges in the spreadsheet.
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $named_ranges = $spreadsheet->getNamedRanges();

      // Get the values of the row.
      $range = 'Sheet1!' . $row_index . ':' . $row_index;
      $response = $this->sheetsService->spreadsheets_values->get($spreadsheet_id, $range);
      $values = $response->getValues()[0];

      // Map named ranges to values.
      $result = [];
      foreach ($named_ranges as $named_range) {
        $column_index = $named_range->getRange()->getStartColumnIndex();
        $result[$named_range->getName()] = $values[$column_index];
      }

    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Remove a row from first sheet of a Google Spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param int $row_number
   *   The row number.
   *
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse|null
   *   The response from the batch update request.
   */
  public function removeRow($spreadsheet_id, $row_index) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $sheets = $spreadsheet->getSheets();
      $sheet_id = $sheets[0]->getProperties()->getSheetId();

      // Remove a row using a batch update request.
      $batch_update_request = new BatchUpdateSpreadsheetRequest([
        'requests' => [
          new Request([
            'deleteDimension' => [
              'range' => [
                'sheetId' => $sheet_id,
                'dimension' => 'ROWS',
                'startIndex' => $row_index,
                'endIndex' => $row_index + 1,
              ],
            ],
          ]),
        ],
      ]);

      $response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batch_update_request);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Retrieves the metadata values for a specific row in a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param int $row_index
   *   The index of the row to retrieve metadata for.
   *
   * @return array|null
   *   The metadata values for the specified row, or NULL if an error occurs.
   */
  public function getRowMetadata($spreadsheet_id, $row_index) {
    try {
      // Define the DeveloperMetadataLookup.
      $data_filter = new DataFilter([
        'developerMetadataLookup' => new DeveloperMetadataLookup([
          'locationType' => 'ROW',
          'locationMatchingStrategy' => 'INTERSECTING_LOCATION',
          'metadataLocation' => [
            'dimensionRange' => [
              'sheetId' => 0,
              'dimension' => 'ROWS',
              'startIndex' => $row_index,
              'endIndex' => $row_index + 1
            ]
          ]
        ]),
      ]);

      // Prepare a SearchDeveloperMetadataRequest.
      $search_request = new SearchDeveloperMetadataRequest([
        'dataFilters' => [$data_filter],
      ]);

      // Execute the search request.
      $search_response = $this->sheetsService->spreadsheets_developerMetadata->search($spreadsheet_id, $search_request);

      // Extract the matched metadata.
      $matched_metadata = $search_response->getMatchedDeveloperMetadata();

      // Extract the metadata values from the matched metadata.
      $metadata_values = [];
      foreach ($matched_metadata as $metadata) {
          $metadata_values[$metadata->getDeveloperMetadata()->getMetadataKey()] = $metadata->getDeveloperMetadata()->getMetadataValue();
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $metadata_values ?? NULL;
  }

  /**
   * Retrieves the values of all rows in a Google Sheets spreadsheet,
   * mapping them to named ranges and optionally including metadata for each row.
   *
   * @param string $spreadsheet_id
   *   The ID of the Google Sheets spreadsheet.
   * @param bool $include_metadata
   *   (optional) Whether to include metadata for each row. Default is FALSE.
   *
   * @return array|null
   *   An array containing the values of all rows mapped to named ranges,
   *   and optionally including metadata for each row, or NULL if an error occurs.
   */
  public function getAllRowsNamedRangeValues($spreadsheet_id, $include_metadata = FALSE) {
    try {
      // Get all named ranges in the spreadsheet.
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $named_ranges = $spreadsheet->getNamedRanges();

      // Get the values of all rows.
      $range = 'Sheet1';
      $response = $this->sheetsService->spreadsheets_values->get($spreadsheet_id, $range);
      $all_rows_values = $response->getValues();

      // Map named ranges to values for each row.
      $result = [];
      foreach ($all_rows_values as $row_index => $row_values) {
        $named_values = [];
        foreach ($named_ranges as $named_range) {
          $column_index = $named_range->getRange()->getStartColumnIndex();
          $named_values[$named_range->getName()] = $row_values[$column_index];
        }
        $result[$row_index]['values'] = $named_values;

        // If include_metadata is TRUE, get metadata for the row.
        if ($include_metadata) {
          $metadata = $this->getRowMetadata($spreadsheet_id, $row_index + 1);
          $result[$row_index]['metadata'] = $metadata;
        }
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Inserts a new column into a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   *
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse|null
   */
  public function addColumn($spreadsheet_id, $column_index = NULL) {
    try {
      // Get the sheet ID of the first sheet in the spreadsheet.
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $sheetId = $spreadsheet->getSheets()[0]->getProperties()->getSheetId();
      $column_index = $column_index ?? $spreadsheet->getSheets()[0]->getProperties()->getGridProperties()->getColumnCount();

      // Define the InsertDimensionRequest.
      $requests = [
        new Request([
          'insertDimension' => [
            'range' => [
              'sheetId' => $sheetId,
              'dimension' => 'COLUMNS',
              'startIndex' => $column_index,
              'endIndex' => $column_index + 1
            ],
            'inheritFromBefore' => TRUE,
          ]
        ])
      ];

      // Define the BatchUpdateSpreadsheet request.
      $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
        'requests' => $requests
      ]);

      // Execute the batchUpdate request.
      $response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batchUpdateRequest);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Get the column count of a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   *
   * @return int|null
   *   The column count of the spreadsheet, or NULL if an error occurs.
   */
  public function getColumnCount($spreadsheet_id) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $column_count = $spreadsheet->getSheets()[0]->getProperties()->getGridProperties()->getColumnCount();
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $column_count ?? NULL;
  }

  /**
   * Adds a column as a named range.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param string $range_name
   *   The name of the range.
   * @param int $column_index
   *   The index of the column to add as a named range.
   *
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse|null
   *   The response from the batch update request.
   */
  public function addColumnNamedRange($spreadsheet_id, $range_name, $column_index) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $sheets = $spreadsheet->getSheets();
      $sheet_id = $sheets[0]->getProperties()->getSheetId();

      // Define the NamedRange.
      $named_range = new NamedRange([
        'name' => $range_name,
        'range' => [
          'sheetId' => $sheet_id,
          'startColumnIndex' => $column_index,
          'endColumnIndex' => $column_index + 1,
        ],
      ]);

      // Define the BatchUpdateSpreadsheet request.
      $batch_update_request = new BatchUpdateSpreadsheetRequest([
        'requests' => [
          new Request([
            'addNamedRange' => new AddNamedRangeRequest([
              'namedRange' => $named_range,
            ]),
          ]),
        ],
      ]);

      // Execute batchUpdate for the AddNamedRangeRequest.
      $response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batch_update_request);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Removes a column from a Google Sheets spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param int $column_index
   *   The index of the column to remove.
   *
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse|null
   *   The response from the batch update request.
   */
  public function removeColumn($spreadsheet_id, $column_index) {
    try {
        // Get the sheet ID of the first sheet in the spreadsheet.
        $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
        $sheetId = $spreadsheet->getSheets()[0]->getProperties()->getSheetId();

        // Define the DeleteDimensionRequest.
        $requests = [
          new Request([
            'deleteDimension' => [
              'range' => [
                'sheetId' => $sheetId,
                'dimension' => 'COLUMNS',
                'startIndex' => $column_index,
                'endIndex' => $column_index + 1
              ]
            ]
          ])
        ];

        // Define the BatchUpdateSpreadsheet request.
        $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        // Execute the batchUpdate request.
        $response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batchUpdateRequest);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Removes a named range.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param string $named_range_id
   *   The ID of the named range.
   *
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse|null
   *   The response from the batch update request.
   */
  public function removeNamedRange($spreadsheet_id, $named_range_id) {
    try {
      // Define the DeleteNamedRangeRequest.
      $requests = [
        new Request([
          'deleteNamedRange' => [
            'namedRangeId' => $named_range_id,
          ]
        ])
      ];

      // Define the BatchUpdateSpreadsheet request.
      $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
        'requests' => $requests
      ]);

      // Execute the batchUpdate request.
      $response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batchUpdateRequest);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Appends values to named range columns on a new row of a spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param array $named_range_values
   *   An associative array where the array key is the named range and the array value is the value.
   *
   * @return \Google\Service\Sheets\BatchUpdateValuesResponse|null
   *   The response from the append values request.
   */
  public function appendNamedRangeValues($spreadsheet_id, $named_range_values, $metadata = []) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $sheets = $spreadsheet->getSheets();
      $sheet_id = $sheets[0]->getProperties()->getSheetId();
      $sheet_title = $sheets[0]->getProperties()->getTitle();

      $sheet_values = $this->sheetsService->spreadsheets_values->get($spreadsheet_id, $sheet_title);
      $row_index = count($sheet_values->getValues()) + 1;

      // Prepare array of [column, row and value] for updated cell values.
      foreach ($named_range_values as $named_range => $value) {
        $column_index = $this->getNamedRangeColumnIndex($spreadsheet_id, $named_range);
        if ($column_index !== FALSE) {
          $cell_values[] = [
            'column' => $column_index,
            'row' => $row_index,
            'value' => $value,
          ];
        }
      }

      $response = $this->updateCellValues($spreadsheet_id, $cell_values);

      $add_metadata_response = $this->addRowMetadata($spreadsheet_id, $sheet_id, $row_index, $metadata);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Gets a named range.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param string $range_name
   *   The name of the range.
   *
   * @return int|false
   *   The index of the column, or FALSE if the named range was not found.
   */
  public function getNamedRange($spreadsheet_id, $range_name) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $named_ranges = $spreadsheet->getNamedRanges();
      foreach ($named_ranges as $named_range) {
        if ($named_range->getName() === $range_name) {
          $range = $named_range->getRange();
          return $range;
        }
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return FALSE;
  }

  /**
   * Gets the ID of a named range.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   * @param string $named_range_name
   *   The name of the named range.
   *
   * @return string|null
   *   The ID of the named range, or NULL if an error occurs.
   */
  public function getNamedRangeId($spreadsheet_id, $named_range_name) {
    try {
      // Fetch the spreadsheet metadata
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);

      // Get the named ranges
      $named_ranges = $spreadsheet->getNamedRanges();

      // Find the named range with the given name and return its ID
      foreach ($named_ranges as $named_range) {
        if ($named_range->getName() === $named_range_name) {
          return $named_range->getNamedRangeId();
        }
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return NULL;
  }

  /**
   * Gets the column index of a named range.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param string $range_name
   *   The name of the range.
   *
   * @return int|false
   *   The index of the column, or FALSE if the named range was not found.
   */
  public function getNamedRangeColumnIndex($spreadsheet_id, $range_name) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $named_ranges = $spreadsheet->getNamedRanges();
      foreach ($named_ranges as $named_range) {
        if ($named_range->getName() === $range_name) {
          $range = $named_range->getRange();
          return $range->getStartColumnIndex();
        }
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return FALSE;
  }

  /**
   * Gets the named ranges in a spreadsheet.
   *
   * @param string $spreadsheet_id
   *   The ID of the spreadsheet.
   *
   * @return array|null
   *   An array of named ranges, or NULL if an error occurs.
   */
  public function getNamedRanges($spreadsheet_id) {
    try {
        // Fetch the spreadsheet metadata
        $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);

        // Get the named ranges
        $named_ranges = $spreadsheet->getNamedRanges();

        // Extract the names of the named ranges
        $names = [];
        foreach ($named_ranges as $named_range) {
            $names[] = $named_range->getName();
        }

    }
    catch (GoogleException $e) {
        $this->logger->error($e->getMessage());
    }

    return $names ?? NULL;
  }

  /**
   * Gets the letter name for a column number.
   *
   * @param int $number
   *   The column number.
   *
   * @return string
   *   The letter name for the column number.
   */
  public function columnNumberToName($number) {
    $numeric = $number % 26;
    $letter = chr(65 + $numeric);
    $number2 = intval($number / 26);
    if ($number2 > 0) {
        return $this->columnNumberToName($number2 - 1) . $letter;
    }
    else {
        return $letter;
    }
  }

  /**
   * Updates the values of individual cells at row and column indicies.
   *
   * @param string $spreadsheet_id
   *   The ID of the Spreadsheet.
   * @param array $cell_values
   *   An array of 'column', 'row' and 'value'
   *
   * @return \Google\Service\Sheets\BatchUpdateValuesResponse|null
   *   The response from the update values request.
   */
  public function updateCellValues($spreadsheet_id, $cell_values) {
    try {
      $values_ranges = array_map(fn ($cell_value) => new ValueRange([
        'range' => 'Sheet1!' . $this->columnNumberToName($cell_value['column']) . $cell_value['row'],
        'majorDimension' => 'ROWS',
        'values' => [[$cell_value['value']]],
      ]), $cell_values);

      $batch_update_values_request = new BatchUpdateValuesRequest([
        'valueInputOption' => 'USER_ENTERED',
        'data' => $values_ranges,
      ]);

      $response = $this->sheetsService->spreadsheets_values->batchUpdate($spreadsheet_id, $batch_update_values_request);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Freeze the first row of a Spreadsheet.
   *
   * @param string $spreadsheet_id
   *  The ID of the Spreadsheet.
   * @return \Google\Service\Sheets\BatchUpdateSpreadsheetResponse|null
   *   The response from the batch update request.
   */
  public function freezeFirstRow($spreadsheet_id) {
    try {
      $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
      $sheets = $spreadsheet->getSheets();
      $sheet_id = $sheets[0]->getProperties()->getSheetId();

      // Freeze the first row using a batch update request.
      $batch_update_request = new BatchUpdateSpreadsheetRequest([
        'requests' => [
          new Request([
            'updateSheetProperties' => [
              'properties' => [
                'sheetId' => $sheet_id,
                'gridProperties' => [
                  'frozenRowCount' => 1,
                ],
              ],
              'fields' => 'gridProperties.frozenRowCount',
            ],
          ]),
        ],
      ]);

      $response = $this->sheetsService->spreadsheets->batchUpdate($spreadsheet_id, $batch_update_request);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $response ?? NULL;
  }

  /**
   * Get a Google Drive folder ID by its name.
   *
   * @param string $folder_name
   *   The name of the folder.
   * @param string|null $parent_folder_id
   *  The ID of the parent folder. Defaults to NULL.
   *
   * @return string|null
   *   The ID of the folder, or NULL if the folder was not found.
   */
  public function getFolderId($folder_name, $parent_folder_id = NULL) {
    try {
      $params = [
        'q' => "mimeType='application/vnd.google-apps.folder' and name='{$folder_name}'",
        'fields' => 'files(id, name)',
      ];

      if ($parent_folder_id) {
        $params['q'] .= " and '{$parent_folder_id}' in parents";
      }

      $response = $this->driveService->files->listFiles($params);

      $files = $response->getFiles();
      if (count($files) === 0) {
        return NULL;
      }

      // Return the ID of the first folder found with the given name.
      return $files[0]->getId();
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return NULL;
  }

  /**
   * Creates a subdirectory in the specified directory.
   *
   * @param string $directory_id
   *   The ID of the directory where the subdirectory will be created.
   * @param string $subdirectory_name
   *   The name of the subdirectory to be created.
   *
   * @return string|null
   *   The ID of the created subdirectory.
   */
  public function createSubdirectory($directory_id, $subdirectory_name) {
    try {
      $file_metadata = new DriveFile([
        'name' => $subdirectory_name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents' => [$directory_id]
      ]);

      $file = $this->driveService->files->create($file_metadata, ['fields' => 'id']);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $file ? $file->id : NULL;
  }

  /**
   * Moves a subdirectory from one directory to another.
   *
   * @param string $old_directory_id
   *   The ID of the directory where the subdirectory currently resides.
   * @param string $new_directory_id
   *   The ID of the directory where the subdirectory will be moved.
   * @param string $subdirectory_name
   *   The name of the subdirectory to be moved.
   *
   * @return \Google\Service\Drive\DriveFile|null
   */
  public function moveSubdirectory($old_directory_id, $new_directory_id, $subdirectory_name) {
    try {
      // Get the ID of the subdirectory in the old directory.
      $subdirectory_id = $this->getFolderId($subdirectory_name, $old_directory_id);

      // If the subdirectory exists, move it to the new directory.
      if ($subdirectory_id) {
        $result = $this->driveService->files->update($subdirectory_id, new DriveFile(), [
          'addParents' => $new_directory_id,
          'removeParents' => $old_directory_id,
          'fields' => 'id, parents',
        ]);
      }
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Checks if a directory exists.
   *
   * @param string $directory_id
   *   The ID of the directory to check.
   *
   * @return bool
   */
  public function checkDirectoryExists($directory_id) {
    try {
      // Use the Google Drive API to try to get the directory.
      $directory = $this->driveService->files->get($directory_id);

      // If the directory exists and is a folder, return TRUE.
      $result = $directory && $directory->getMimeType() == 'application/vnd.google-apps.folder';
    }
    catch (GoogleException $e) {
      // If an exception is thrown, the ID is not valid.
      $this->logger->error($e->getMessage());
    }

    return $result ?? FALSE;
  }

  /**
   * Uploads a file to Google Drive.
   *
   * @param string $file_path
   *   The path of the file to be uploaded.
   * @param string $file_name
   *   The name of the file to be uploaded.
   * @param string|null $folder_id
   *   The ID of the folder where the file should be uploaded. Defaults to NULL.
   *
   * @return \Google\Service\Drive\DriveFile|null
   *   The new DriveFile instance that was created.
   */
  public function uploadFile($file_path, $file_name, $folder_id = NULL) {
    try {
      $file = new DriveFile();
      $file->setName($file_name);

      if ($folder_id) {
        $file->setParents([$folder_id]);
      }

      $result = $this->driveService->files->create($file, [
        'data' => file_get_contents($file_path),
        'uploadType' => 'multipart',
        'fields' => 'id, webViewLink',
      ]);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Deletes a file or directory including all its descendants.
   *
   * @param string $file_id
   *   The ID of the file or directory to be deleted.
   *
   * @return mixed|null
   */
  public function deleteFile($file_id) {
    try {
      // Retrieve the file metadata.
      $file = $this->driveService->files->get($file_id);

      // If the file is a folder, delete its contents first.
      if ($file->getMimeType() == 'application/vnd.google-apps.folder') {
        $children = $this->driveService->files->listFiles([
          'q' => "'$file_id' in parents"
        ]);

        foreach ($children->getFiles() as $child) {
          $this->deleteFile($child->getId());
        }
      }

      // Delete the file.
      $result = $this->driveService->files->delete($file_id);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Returns a Google Drive file.
   *
   * @param string $file_id
   *   The ID of the file to be retrieved.
   *
   * @return \Google\Service\Drive\DriveFile|null
   *   The DriveFile instance that was retrieved.
   */
  public function getFile($file_id) {
    try {
      $result = $this->driveService->files->get($file_id, ['fields' => 'webViewLink']);
    }
    catch (GoogleException $e) {
      $this->logger->error($e->getMessage());
    }

    return $result ?? NULL;
  }

  /**
   * Remove the Google Sheet row and Google Drive submission directory associated with a Webform submission id.
   * Expects the row to have a metadata key of 'submission_id' and a value matching the given id.
   * Expects the Google Drive submission subdirectory in the format: [webform_id]/submissions/[submission_id].
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The Webform submission.
   *
   * @return true|null
   *   Returns true if the row and directory were successfully removed, or NULL if an error occurs.
   */
  public function removeWebformSubmissionRowAndFiles($webform_submission) {
    if ($webform_submission) {
      $webform_submission_id = $webform_submission->id();
      // Get the webform and it's handlers from the Webform submission.
      $webform = $webform_submission->getWebform();
      $webform_handlers = $webform->getHandlers();

      // Find the googlesheets_submissions handler.
      foreach ($webform_handlers as $handler) {
        if ($handler->getPluginId() == 'googlesheets_submissions') {
          // Get the spreadsheet id from the googlesheets_submissions handler.
          $spreadsheet_id = $handler->getSetting('spreadsheet_id');
          if (empty($spreadsheet_id)) {
            $this->logger->error('No spreadsheet ID found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
            return NULL;
          }

          // Get the directory id where webforms are stored from the googlesheets_submissions handler.
          $spreadsheet_drive_directory_id = $handler->getSetting('spreadsheet_drive_directory_id');
          if (empty($spreadsheet_drive_directory_id)) {
            $this->logger->error('No spreadsheet drive directory ID found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
            return NULL;
          }

          try {
            // Get the row index for this webform submission.
            $row_index = $this->getRowIndexByMetadata($spreadsheet_id, 'submission_id', $webform_submission_id);
            if ($row_index != NULL) {
              // Remove the row from the spreadsheet.
              $delete_row_result = $this->removeRow($spreadsheet_id, $row_index);
              if (!$delete_row_result) {
                $this->logger->error('Failed to delete row for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
                return NULL;
              }
            }
            else {
              $this->logger->error('No row index found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
            }

            // Get the subdirectory for the webform on Google Drive.
            $webform_subdirectory_id = $this->getFolderId($webform->id(), $spreadsheet_drive_directory_id);

            // Get the subdirectory for this webform's submissions on Google Drive
            $webform_submissions_directory_id = $this->getFolderId('submissions', $webform_subdirectory_id);

            // Get the subdirectory for this webform submission on Google Drive.
            $webform_submission_directory_id = $this->getFolderId($webform_submission_id, $webform_submissions_directory_id);

            if (!is_null($webform_subdirectory_id) && !is_null($webform_submissions_directory_id) && !is_null($webform_submission_directory_id)) {
              // Remove the webform submission directory from Google Drive.
              $delete_files_result = $this->deleteFile($webform_submission_directory_id);
              if (!$delete_files_result) {
                $this->logger->error('Failed to delete webform submission directory for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
                return NULL;
              }
            }
            else {
              $this->logger->error('No submission directory found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
            }

            $result = $delete_row_result;
          }
          catch (GoogleException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to a Google Spreadsheet where submissions are stored for a webform.
   *
   * @param int $webform_id
   *   The ID of the Webform.
   *
   * @return string|null
   *   The URL to the Google Spreadsheet, or NULL if an error occurs.
   */
  public function getWebformSpreadsheetUrl($webform_id) {
    /** @var \Drupal\webform\WebformInterface */
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);

    if ($webform) {
      $webform_handlers = $webform->getHandlers();

      // Find the googlesheets_submissions handler.
      foreach ($webform_handlers as $handler) {
        if ($handler->getPluginId() == 'googlesheets_submissions') {
          // Get the spreadsheet id from the googlesheets_submissions handler.
          $spreadsheet_id = $handler->getSetting('spreadsheet_id');
          if (empty($spreadsheet_id)) {
            $this->logger->error('No spreadsheet ID found for webform @webform_id.', ['@webform_id' => webform_id]);
            return NULL;
          }

          try {
            // Load the spreadsheet and get its URL.
            $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
            $result = $spreadsheet->getSpreadsheetUrl();
          }
          catch (GoogleException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to the Google Drive directory where the submissions spreadsheet is stored for a webform.
   *
   * @param int $webform_id
   *   The ID of the Webform.
   *
   * @return string|null
   *   The URL of the Google Drive directory, or NULL if an error occurs.
   */
  public function getWebformSpreadsheetDirectoryUrl($webform_id) {
    /** @var \Drupal\webform\WebformInterface */
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);

    if ($webform) {
      $webform_handlers = $webform->getHandlers();

      // Find the googlesheets_submissions handler.
      foreach ($webform_handlers as $handler) {
        if ($handler->getPluginId() == 'googlesheets_submissions') {
          // Get the Google Drive directory id from the googlesheets_submissions handler.
          $spreadsheet_drive_directory_id = $handler->getSetting('spreadsheet_drive_directory_id');
          if (empty($spreadsheet_drive_directory_id)) {
            $this->logger->error('No spreadsheet drive directory ID found for webform @webform_id.', ['@webform_id' => $webform_id]);
            return NULL;
          }

          try {
            // Get the subdirectory for the webform on Google Drive.
            $webform_subdirectory_id = $this->getFolderId($webform->id(), $spreadsheet_drive_directory_id);
            if ($webform_subdirectory_id == NULL) {
              $this->logger->error('No webform subdirectory found for webform @webform_submission_id.', ['@webform_id' => $webform_id]);
              return NULL;
            }

            $webform_subdirectory = $this->getFile($webform_subdirectory_id);

            $result = $webform_subdirectory->getWebViewLink();
          }
          catch (GoogleException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to the Google Drive directory where submission files are stored for a webform.
   *
   * @param int $webform_id
   *   The ID of the Webform.
   *
   * @return string|null
   *   The URL of the Google Drive directory, or NULL if an error occurs.
   */
  public function getWebformSubmissionsDirectoryUrl($webform_id) {
    /** @var \Drupal\webform\WebformInterface */
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);

    if ($webform) {
      $webform_handlers = $webform->getHandlers();

      // Find the googlesheets_submissions handler.
      foreach ($webform_handlers as $handler) {
        if ($handler->getPluginId() == 'googlesheets_submissions') {
          // Get the Google Drive directory id from the googlesheets_submissions handler.
          $spreadsheet_drive_directory_id = $handler->getSetting('spreadsheet_drive_directory_id');
          if (empty($spreadsheet_drive_directory_id)) {
            $this->logger->error('No spreadsheet drive directory ID found for webform @webform_id.', ['@webform_id' => $webform_id]);
            return NULL;
          }

          try {
            // Get the subdirectory for the webform on Google Drive.
            $webform_subdirectory_id = $this->getFolderId($webform->id(), $spreadsheet_drive_directory_id);
            if ($webform_subdirectory_id == NULL) {
              $this->logger->error('No webform subdirectory found for webform @webform_id.', ['@webform_id' => $webform_id]);
              return NULL;
            }

            // Get the subdirectory for this webform's submissions on Google Drive
            $webform_submissions_directory_id = $this->getFolderId('submissions', $webform_subdirectory_id);
            if ($webform_submissions_directory_id == NULL) {
              $this->logger->error('No webform submissions subdirectory found for webform @webform_id.', ['@webform_id' => $webform_id]);
              return NULL;
            }

            $webform_subdirectory = $this->getFile($webform_subdirectory_id);

            $result = $webform_subdirectory->getWebViewLink();
          }
          catch (GoogleException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to a Google Spreadsheet where the webform submission is stored.
   *
   * @param int $webform_submission_id
   *   The ID of the Webform submission.
   *
   * @return string|null
   *   The URL to the Google Spreadsheet, or NULL if an error occurs.
   */
  public function getWebformSubmissionSpreadsheetUrl($webform_submission_id) {
    // Load the Webform submission.
    /** @var \Drupal\webform\WebformSubmissionInterface */
    $webform_submission = $this->entityTypeManager->getStorage('webform_submission')->load($webform_submission_id);

    if ($webform_submission) {
      // Get the webform and it's handlers from the Webform submission.
      $webform = $webform_submission->getWebform();

      $result = $this->getWebformSpreadsheetUrl($webform->id());
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to the Google Drive directory where the webform submission's spreadsheet is stored.
   *
   * @param int $webform_submission_id
   *   The ID of the Webform submission.
   *
   * @return string|null
   *   The URL of the Google Drive directory, or NULL if an error occurs.
   */
  public function getWebformSubmissionSpreadsheetDirectoryUrl($webform_submission_id) {
    // Load the Webform submission.
    /** @var \Drupal\webform\WebformSubmissionInterface */
    $webform_submission = $this->entityTypeManager->getStorage('webform_submission')->load($webform_submission_id);

    if ($webform_submission) {
      // Get the webform and it's handlers from the Webform submission.
      $webform = $webform_submission->getWebform();
      $result = $this->getWebformSpreadsheetDirectoryUrl($webform->id());
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to the range in a Google Spreadsheet where the webform submission is stored.
   *
   * @param int $webform_submission_id
   *   The ID of the Webform submission.
   *
   * @return string|null
   *   The URL to the range in the Google Spreadsheet, or NULL if an error occurs.
   */
  public function getWebformSubmissionSpreadsheetRangeUrl($webform_submission_id) {
    // Load the Webform submission.
    /** @var \Drupal\webform\WebformSubmissionInterface */
    $webform_submission = $this->entityTypeManager->getStorage('webform_submission')->load($webform_submission_id);

    if ($webform_submission) {
      // Get the webform and it's handlers from the Webform submission.
      $webform = $webform_submission->getWebform();
      $webform_handlers = $webform->getHandlers();

      // Find the googlesheets_submissions handler.
      foreach ($webform_handlers as $handler) {
        if ($handler->getPluginId() == 'googlesheets_submissions') {
          // Get the spreadsheet id from the googlesheets_submissions handler.
          $spreadsheet_id = $handler->getSetting('spreadsheet_id');
          if (empty($spreadsheet_id)) {
            $this->logger->error('No spreadsheet ID found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
            return NULL;
          }

          try {
            // Load the spreadsheet and get its URL.
            $spreadsheet = $this->sheetsService->spreadsheets->get($spreadsheet_id);
            $spreadsheet_url = $spreadsheet->getSpreadsheetUrl();

            // Append the range to the spreadsheet URL.
            $row_index = $this->getRowIndexByMetadata($spreadsheet_id, 'submission_id', $webform_submission_id);
            if ($row_index != NULL) {
              $row_index = $row_index + 1;
              $range = "{$row_index}:{$row_index}";
              $result = $spreadsheet_url . '#gid=0&range=' . $range;
            }
          }
          catch (GoogleException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result ?? NULL;
  }

  /**
   * Returns the URL to the Google Drive directory where the webform submission's files are stored.
   *
   * @param int $webform_submission_id
   *   The ID of the Webform submission.
   *
   * @return string|null
   *   The URL of the Google Drive directory, or NULL if an error occurs.
   */
  public function getWebformSubmissionFilesDirectoryUrl($webform_submission_id) {
    // Load the Webform submission.
    /** @var \Drupal\webform\WebformSubmissionInterface */
    $webform_submission = $this->entityTypeManager->getStorage('webform_submission')->load($webform_submission_id);

    if ($webform_submission) {
      // Get the webform and it's handlers from the Webform submission.
      $webform = $webform_submission->getWebform();
      $webform_handlers = $webform->getHandlers();

      // Find the googlesheets_submissions handler.
      foreach ($webform_handlers as $handler) {
        if ($handler->getPluginId() == 'googlesheets_submissions') {
          // Get the Google Drive directory id from the googlesheets_submissions handler.
          $spreadsheet_drive_directory_id = $handler->getSetting('spreadsheet_drive_directory_id');
          if (empty($spreadsheet_drive_directory_id)) {
            $this->logger->error('No spreadsheet drive directory ID found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
            return NULL;
          }

          try {
            // Get the subdirectory for the webform on Google Drive.
            $webform_subdirectory_id = $this->getFolderId($webform->id(), $spreadsheet_drive_directory_id);
            if ($webform_subdirectory_id == NULL) {
              $this->logger->error('No webform subdirectory found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
              return NULL;
            }

            // Get the subdirectory for this webform's submissions on Google Drive
            $webform_submissions_directory_id = $this->getFolderId('submissions', $webform_subdirectory_id);
            if ($webform_submissions_directory_id == NULL) {
              $this->logger->error('No webform submissions subdirectory found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
              return NULL;
            }

            // Get the directory for this webform submission on Google Drive.
            $webform_submission_directory_id = $this->getFolderId($webform_submission_id, $webform_submissions_directory_id);
            if ($webform_submission_directory_id == NULL) {
              $this->logger->error('No webform submission subdirectory found for webform submission @webform_submission_id.', ['@webform_submission_id' => $webform_submission_id]);
              return NULL;
            }

            $webform_submission_directory = $this->getFile($webform_submission_directory_id);

            $result = $webform_submission_directory->getWebViewLink();
          }
          catch (GoogleException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result ?? NULL;
  }

  /**
   * Retrieves the spreadsheet ID configured for a Webform with Google Sheets submissions.
   *
   * @param int $webform_id
   *   The ID of the Webform to retrieve the spreadsheet ID from.
   *
   * @return string|NULL
   *   The spreadsheet ID if configured, NULL otherwise.
   */
  public function getSpreadsheetID($webform_id) {
    /** @var \Drupal\webform\WebformInterface */
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    // Iterate through each handler configured for the Webform.
    foreach ($webform->getHandlers() as $handler) {
      // Check if the handler is the Google Sheets submissions handler.
      if ($handler->getPluginId() == 'googlesheets_submissions') {
        // Check if the spreadsheet_id is configured in the handler settings.
        if (!empty($handler->getConfiguration()['settings']['spreadsheet_id'])) {
          return $handler->getConfiguration()['settings']['spreadsheet_id'];
        }
      }
    }

    return NULL;
  }

  /**
   * Retrieves the webform handler settings configured for a Webform with Google Sheets submissions.
   *
   * @param string $webform_id
   *   The ID of the Webform to retrieve the spreadsheet ID from.
   *
   * @return array|NULL
   *   The webform handler settings, NULL otherwise.
   */
  public function getWebformHandlerSettings($webform_id) {
    /** @var \Drupal\webform\WebformInterface */
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    // Iterate through each handler configured for the Webform.
    foreach ($webform->getHandlers() as $handler) {
      // Check if the handler is the Google Sheets submissions handler.
      if ($handler->getPluginId() == 'googlesheets_submissions') {
        // Check if the spreadsheet_id is configured in the handler settings.
        if (!empty($handler->getConfiguration()['settings'])) {
          return $handler->getConfiguration()['settings'];
        }
      }
    }

    return NULL;
  }

  /**
   * Authenticate with Google Sheets based on the specified authentication method.
   *
   * This method handles authentication for interacting with Google Sheets.
   * It supports two authentication methods: 'google_api_service_client'
   * and 'google_api_client'. The appropriate method is determined by the
   * 'google_api_authentication_method' setting for the given webform.
   *
   * @param string $webform_id
   *   The ID of the webform for which authentication is needed.
   *
   * @return bool
   *   Returns TRUE if authentication is successful, otherwise FALSE.
   */
  public function googleSheetsAuth($webform_id) {
    $google_api_authentication_method = $this->getWebformHandlerSettings($webform_id)['google_api_authentication_method'];

    if (isset($google_api_authentication_method) && $google_api_authentication_method != '') {
      switch ($google_api_authentication_method) {
        case 'google_api_service_client':
          $google_api_service_client_id = $this->getWebformHandlerSettings($webform_id)['google_api_service_client_id'];
          if (isset($google_api_service_client_id) && $google_api_service_client_id != '') {
            // Check that the Google API Service Account exists.
            $google_api_service_client = GoogleApiServiceClient::load($google_api_service_client_id);
            if ($google_api_service_client) {
              // Set the Google API Client for use.
              $this->setGoogleApiServiceClient($google_api_service_client_id);
              return TRUE;
            }
            else {
              $this->messenger->addError($this->t('The Google Service Account @service_account does not exist.', ['@service_account' => $google_api_service_client]));
              $this->logger->error($this->t('The Google Service Account @service_account does not exist.', ['@service_account' => $google_api_service_client]));
            }
          }
          break;

        case 'google_api_client':
          $google_api_client_id = $this->getWebformHandlerSettings($webform_id)['google_api_client_id'];
          if (isset($google_api_client_id) && $google_api_client_id != '') {
            // Check that the Google API Client exists.
            $google_api_client = GoogleApiClient::load($google_api_client_id);
            if ($google_api_client) {
              // Set the Google API Client for use.
              $this->setGoogleApiClient($google_api_client_id);
              return TRUE;
            }
            else {
              $this->messenger->addError($this->t('The Google API Client @api_client does not exist.', ['@api_client' => $google_api_client_id]));
              $this->logger->error($this->t('The Google API Client @api_client does not exist.', ['@api_client' => $google_api_client_id]));
            }
          }
          break;
      }
    }

    return NULL;
  }

}
