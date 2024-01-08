<?php

namespace Drupal\coe_webform_reports\Plugin\WebformElement;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\Element\WebformCompositeFormElementTrait;
use Drupal\webform\EntityStorage\WebformEntityStorageTrait;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\Plugin\WebformEntityInjectionTrait;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom webform element.
 *
 * @WebformElement(
 *   id = "completion_time_element",
 *   label = @Translation("Completion Time"),
 *   category = @Translation("Custom"),
 * )
 */
class CompletionTimeWebformElement extends WebformElementBase {

  use StringTranslationTrait;
  use MessengerTrait;
  use WebformCompositeFormElementTrait;
  use WebformEntityInjectionTrait;
  use WebformEntityStorageTrait;

  /**
   * A logger instance.
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The webform libraries manager.
   *
   * @var \Drupal\webform\WebformLibrariesManagerInterface
   */
  protected $librariesManager;

  /**
   * An associative array of an element's default properties names and values.
   *
   * @var array
   */
  protected $defaultProperties;

  /**
   * An indexed array of an element's translated properties.
   *
   * @var array
   */
  protected $translatableProperties;

  /**
   * The view count service.
   *
   * @var Drupal\coe_webform_reports\Service\ViewCount
   */
  protected $viewCountService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    $instance->logger = $container->get('logger.factory')->get('webform');
    $instance->configFactory = $container->get('config.factory');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->elementInfo = $container->get('plugin.manager.element_info');
    $instance->elementManager = $container->get('plugin.manager.webform.element');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->librariesManager = $container->get('webform.libraries_manager');
    $instance->viewCountService = $container->get('coe_webform_reports.view_count');

    return $instance;
  }

  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $format = $this->getItemFormat($element);
    $value = $this->formatTextItem($element, $webform_submission, ['prefixing' => FALSE] + $options);

    if ($format === 'raw') {
      return $value;
    }

    // Format the value
    $value = $this->viewCountService->convertSecondsToTime($value);

    // Build a render that used #plain_text so that HTML characters are escaped.
    // @see \Drupal\Core\Render\Renderer::ensureMarkupIsSafe
    $build = ['#plain_text' => $value];

    $options += ['prefixing' => TRUE];
    if ($options['prefixing']) {
      if (isset($element['#field_prefix'])) {
        $build['#prefix'] = $element['#field_prefix'];
      }
      if (isset($element['#field_suffix'])) {
        $build['#suffix'] = $element['#field_suffix'];
      }
    }

    return $build;
  }

}
