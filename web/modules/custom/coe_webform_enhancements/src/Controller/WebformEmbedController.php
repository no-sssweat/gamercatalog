<?php

namespace Drupal\coe_webform_enhancements\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\Entity\File;
use Psr\Container\ContainerInterface;

/**
 * Provides a bare page with a rendered Webform for embeding (i.e. without header/footer, menus, etc).
 */
class WebformEmbedController extends ControllerBase {

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The attachment processor service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  protected $attachmentsProcessor;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * WebformEmbedController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type manager.
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $attachments_processor
   *   The attachment processor service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AttachmentsResponseProcessorInterface $attachments_processor, Renderer $renderer, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->attachmentsProcessor = $attachments_processor;
    $this->renderer = $renderer;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('html_response.attachments_processor'),
      $container->get('renderer'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * Renders Webform in a render context, maintains bubbleable cache metadata.
   * Includes css, js, libraries, attachements, etc in response.
   *
   * @param string $webform_id
   *   The machine name of the webform.
   *
   * @return \Drupal\Core\Render\HtmlResponse|array
   *   A rendered HTML response or an unrendered render array.
   */
  public function getRenderedWebform($webform_id) {
    $webform_id = str_replace('-', '_', $webform_id);

    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);

    if ($webform) {
      $context = new RenderContext();

      // Render the response inside a render context so metadata and attachments are preserved.
      $response = $this->renderer->executeInRenderContext($context, function () use ($webform, $context) {
        // Build the render array for the Webform.
        $webform_output = $this->entityTypeManager->getViewBuilder('webform')->view($webform);

        // This is a render array for the response page.
        $response_page = [
          '#type' => 'html',
          'page' => [
            '#type' => 'page',
            '#theme' => 'page__webform_embed',
            '#webform_output' => $webform_output,
          ],
          '#attached' => [
            'library' => [
              'coe_webform_enhancements/webform_embed',
            ],
          ],
        ];

        // Load the logo file and generate a URL for it, add it to the render array.
        $logo_fid = $webform->getThirdPartySetting('coe_webform_enhancements', 'logo');
        if (isset($logo_fid[0])) {
          $logo_file = File::load($logo_fid[0]);
          if ($logo_file) {
            $uri = $logo_file->getFileUri();
            $response_page['page']['#logo_url'] = $this->streamWrapperManager->getViaUri($uri)->getExternalUrl();
          }
        }

        // Add the color hex to the render array.
        $color_hex = $webform->getThirdPartySetting('coe_webform_enhancements', 'color_hex');
        if (isset($color_hex) && $color_hex !== '') {
          $response_page['page']['#color_hex'] = $color_hex;
        }

        // Add the footer to the render array.
        $footer = $webform->getThirdPartySetting('coe_webform_enhancements', 'footer');
        $footer_format = $webform->getThirdPartySetting('coe_webform_enhancements', 'footer_format');
        if (isset($footer) && $footer !== '') {
          $response_page['page']['#footer'] = [
            '#type' => 'processed_text',
            '#text' => $footer,
            '#format' => $footer_format,
          ];
        }

        // Add default system attachments to the render array for the response page.
        system_page_attachments($response_page['page']);

        // Render the response page and create a new HtmlResponse with the resulting markup.
        $rendered_reponse_page = $this->renderer->render($response_page);
        $htmlResponse = new HtmlResponse($rendered_reponse_page);

        // Bubble cache metadata and attachments from render context into this response.
        if (!$context->isEmpty()) {
          $metadata = $context->pop();
          $htmlResponse->addCacheableDependency($metadata);
          $htmlResponse->addAttachments($metadata->getAttachments());
        }

        // Process attachments in this response (i.e. replace placeholders, etc).
        $htmlResponse = $this->attachmentsProcessor->processAttachments($htmlResponse);

        // Return the rendered response from this render context.
        return $htmlResponse;
      });
    }
    else {
      // Respond with an 'Invalid Webform ID' page.
      $content = [
        '#markup' => $this->t('Invalid Webform ID.')
      ];

      $response = $content;
    }

    return $response;

  }

}
