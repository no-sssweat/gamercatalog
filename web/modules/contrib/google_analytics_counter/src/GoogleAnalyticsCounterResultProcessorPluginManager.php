<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * GoogleAnalyticsCounterResultProcessor plugin manager.
 */
class GoogleAnalyticsCounterResultProcessorPluginManager extends DefaultPluginManager {

  /**
   * Constructs GoogleAnalyticsCounterResultProcessorPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/GoogleAnalyticsCounterResultProcessor',
      $namespaces,
      $module_handler,
      'Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorInterface',
      'Drupal\google_analytics_counter\Annotation\GoogleAnalyticsCounterResultProcessor'
    );
    $this->alterInfo('google_analytics_counter_result_processor_info');
    $this->setCacheBackend($cache_backend, 'google_analytics_counter_result_processor_plugins');
  }

  /**
   * Returns a plugin instance.
   *
   * @param string $pluginId
   *   Plugin ID.
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginBase|null
   *   Returns the instance if valid plugin ID is provided.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Throws exception if plugin was not found.
   */
  public function getPlugin($pluginId) {
    return $this->createInstance($pluginId);
  }

  /**
   * Returns all available plugin labels with IDs.
   *
   * Useful for select dropdowns in forms.
   *
   * @return array
   *   Array format used by select form element. Key: Plugin ID, Value: label.
   */
  public function getAllPluginsLabels() {
    $handlers = [];

    foreach ($this->getDefinitions() as $plugin_id => $plugin_def) {
      $handlers[$plugin_id] = $plugin_def['label'];
    }

    return $handlers;
  }

  /**
   * Returns all plugin definitions.
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterResultProcessorPluginBase[]
   *   Plugin definition objects as array.
   */
  public function getAllPlugins() {
    return $this->getDefinitions();
  }

}
