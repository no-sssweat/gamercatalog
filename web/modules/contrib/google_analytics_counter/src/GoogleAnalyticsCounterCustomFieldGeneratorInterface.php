<?php

namespace Drupal\google_analytics_counter;

use Drupal\node\NodeTypeInterface;

/**
 * Defines the Google Analytics Counter custom field generator.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterCustomFieldGeneratorInterface {

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
  public function gacPreAddField(NodeTypeInterface $type, $key, $value);

  /**
   * Adds the checked the fields.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   * @param string $label
   *   The formatter label display setting.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\field\Entity\FieldConfig|null
   *   Adds field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gacAddField(NodeTypeInterface $type, $label = 'Google Analytics Counter');

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
  public function gacPreDeleteField(NodeTypeInterface $type, $key);

  /**
   * Deletes the unchecked field configurations.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   *
   * @return null|void
   *   Deletes the field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see GoogleAnalyticsCounterConfigureTypesForm
   */
  public function gacDeleteField(NodeTypeInterface $type);

  /**
   * Deletes the field storage configurations.
   *
   * @return null|void
   *   Deletes the field storage.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see GoogleAnalyticsCounterConfigureTypesForm
   */
  public function gacDeleteFieldStorage();

  /**
   * Creates the gac_type_{content_type} configuration.
   *
   * On installation or update.
   */
  public function gacChangeConfigToNull();

}
