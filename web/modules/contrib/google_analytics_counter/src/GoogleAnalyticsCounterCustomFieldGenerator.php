<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeTypeInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines the Google Analytics Counter custom field generator.
 *
 * @package Drupal\google_analytics_counter
 */
class GoogleAnalyticsCounterCustomFieldGenerator implements GoogleAnalyticsCounterCustomFieldGeneratorInterface {

  use StringTranslationTrait;

  /**
   * The table for the node__field_google_analytics_counter storage.
   */
  const TABLE = 'node__field_google_analytics_counter';

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterCustomFieldGeneratorInterface.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterCustomFieldGeneratorInterface
   */
  protected $customField;

  /**
   * Implements EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Implements EntityDisplayRepositoryInterface.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a GoogleAnalyticsCounterCustomFieldGenerator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Implements entity manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   Implements EntityDisplayRepositoryInterface.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, LoggerInterface $logger, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    $this->configFactory = $config_factory;
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->connection = $connection;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * Prepares to add the custom field and saves the configuration.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   * @param mixed $key
   *   The setting key.
   * @param mixed $value
   *   The setting value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gacPreAddField(NodeTypeInterface $type, $key, $value) {
    // Add the field.
    $this->gacAddField($type);

    // Update the gac_type_{content_type} configuration.
    $this->configFactory
      ->getEditable('google_analytics_counter.settings')
      ->set("general_settings.$key", $value)
      ->save();
  }

  /**
   * Adds the checked the fields.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   * @param string $label
   *   The formatter label display setting.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gacAddField(NodeTypeInterface $type, $label = 'Google Analytics Counter') {

    // Check if field storage exists.
    $config = FieldStorageConfig::loadByName('node', 'field_google_analytics_counter');
    if (!isset($config)) {
      // Obtain configuration from yaml files.
      $config_path = 'modules/contrib/google_analytics_counter/config/optional';
      $source = new FileStorage($config_path);

      // Obtain the storage manager for field storage bases.
      // Create the new field configuration from
      // the yaml configuration and save.
      $this->entityTypeManager->getStorage('field_storage_config')
        ->create($source->read('field.storage.node.field_google_analytics_counter'))
        ->save();
    }

    // Add the checked fields.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_google_analytics_counter');
    $field = FieldConfig::loadByName('node', $type->id(), 'field_google_analytics_counter');
    if (empty($field)) {
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $type->id(),
        'label' => $label,
        'description' => $this->t('This field stores Google Analytics pageviews.'),
        'field_name' => 'field_google_analytics_counter',
        'entity_type' => 'node',
        'settings' => ['display_summary' => TRUE],
      ]);
      $field->save();

      // Assign widget settings for the 'default' form mode.
      $this->entityDisplayRepository
        ->getFormDisplay('node', $type->id(), 'default')
        ->setComponent('google_analytics_counter', [
          'type' => 'number',
          '#maxlength' => 255,
          '#default_value' => 0,
          '#description' => $this->t('This field stores Google Analytics pageviews.'),
        ])
        ->save();

      // Assign display settings for the 'default' and 'teaser' view modes.
      $this->entityDisplayRepository
        ->getViewDisplay('node', $type->id(), 'default')
        ->setComponent('google_analytics_counter', [
          'label' => 'hidden',
          'type' => 'number',
        ])
        ->save();

      // The teaser view mode is created by the Standard profile and therefore
      // might not exist.
      $view_modes = $this->entityDisplayRepository->getViewModes('node');
      if (isset($view_modes['teaser'])) {
        $this->entityDisplayRepository
          ->getViewDisplay('node', $type->id(), 'teaser')
          ->setComponent('google_analytics_counter', [
            'label' => 'hidden',
            'type' => 'textfield',
          ])
          ->save();
      }
    }

    return $field;
  }

  /**
   * Prepares to delete the custom field and saves the configuration.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   * @param mixed $key
   *   The setting key.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gacPreDeleteField(NodeTypeInterface $type, $key) {
    // Delete the field.
    // @todo Remove this method.
    $this->gacDeleteField($type);
  }

  /**
   * Deletes the unchecked field configurations.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see GoogleAnalyticsCounterConfigureTypesForm
   */
  public function gacDeleteField(NodeTypeInterface $type) {
    // Check if field exists on the content type.
    $content_type = $type->id();
    $config = FieldConfig::loadByName('node', $content_type, 'field_google_analytics_counter');
    if (!isset($config)) {
      return NULL;
    }
    // Delete the field from the content type.
    FieldConfig::loadByName('node', $content_type, 'field_google_analytics_counter')->delete();
  }

  /**
   * Deletes the field storage configurations.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see GoogleAnalyticsCounterConfigureTypesForm
   */
  public function gacDeleteFieldStorage() {
    $field_storage = FieldStorageConfig::loadByName('node', 'field_google_analytics_counter');
    if (!empty($field_storage)) {
      $field_storage->delete();
    }
  }

  /**
   * Creates the gac_type_{content_type} configuration.
   *
   * On installation or update.
   */
  public function gacChangeConfigToNull() {
    $content_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($content_types as $machine_name => $content_type) {
      $this->configFactory->getEditable('google_analytics_counter.settings')
        ->set("general_settings.gac_type_$machine_name", NULL)
        ->save();
    }
  }

}
