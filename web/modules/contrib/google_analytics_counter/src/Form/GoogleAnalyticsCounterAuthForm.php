<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\State\StateInterface;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Google Analytics Counter Auth Form.
 *
 * @package Drupal\google_analytics_counter\Form
 */
class GoogleAnalyticsCounterAuthForm extends ConfigFormBase {

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface
   */
  protected $appManager;

  /**
   * The Google Analytics Counter message manager.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface
   */
  protected $messageManager;

  /**
   * The current path for the current request.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Find an alias for a path and vice versa.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a new SiteMaintenanceModeForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterAppManagerInterface $app_manager
   *   Google Analytics Counter Auth Manager object.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterMessageManagerInterface $message_manager
   *   Google Analytics Counter Message Manager object.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path_stack
   *   The current path for the current request.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   Find an alias for a path and vice versa.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, GoogleAnalyticsCounterAppManagerInterface $app_manager, GoogleAnalyticsCounterMessageManagerInterface $message_manager, CurrentPathStack $current_path_stack, AliasManagerInterface $alias_manager) {
    parent::__construct($config_factory);
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->state = $state;
    $this->appManager = $app_manager;
    $this->messageManager = $message_manager;
    $this->currentPath = $current_path_stack;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('google_analytics_counter.app_manager'),
      $container->get('google_analytics_counter.message_manager'),
      $container->get('path.current'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_analytics_counter_admin_auth';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['google_analytics_counter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $form['help_text'] = [
      '#markup' => $this->t("<h3>Google Analytics Data API (GA4)</h3><a href='@link1' target='_blank'>Step 1. Enable the API</a><br><a href='@link2' target='_blank'>Step 2. Add service account to the Google Analytics 4 property</a>", [
        '@link1' => 'https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries#step_1_enable_the_api',
        '@link2' => 'https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries#step_2_add_service_account_to_the_google_analytics_4_property',
      ]),
    ];

    $form['ga4_property_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Google Analytics 4 Property ID"),
      '#default_value' => $config->get('general_settings.ga4_property_id'),
      '#description' => $this->t("<a href='@link' target='_blank'>What is my property ID?</a>", [
        '@link' => 'https://developers.google.com/analytics/devguides/reporting/data/v1/property-id#what_is_my_property_id',
      ]),
    ];

    $form['credentials_json_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Path to the service account credentials.json"),
      '#default_value' => $config->get('general_settings.credentials_json_path'),
      '#description' => $this->t('Specify relative filepath to the credentials.json file starting from the folder where your index.php is. If you installed your site using the drupal/recommended-project template, put the file outside of web root folder, and specify for example like this: <em>../credentials.json</em>.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $config
      ->set('general_settings.ga4_property_id', $form_state->getValue('ga4_property_id'))
      ->set('general_settings.credentials_json_path', $form_state->getValue('credentials_json_path'))

      ->save();

    parent::submitForm($form, $form_state);
  }

}
