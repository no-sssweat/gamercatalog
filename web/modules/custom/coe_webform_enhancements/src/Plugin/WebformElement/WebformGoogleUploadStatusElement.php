<?php

namespace Drupal\coe_webform_enhancements\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform element to track Google upload status of a webform submission.
 *
 * @WebformElement(
 *   id = "google_upload_status_element",
 *   label = @Translation("Google Upload Status"),
 *   category = @Translation("Google Sheets Submissions"),
 * )
 */
class WebformGoogleUploadStatusElement extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
      '#access' => FALSE,
    ] + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function formatHtml(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->formatText($element, $webform_submission, $options);
    return [
      '#markup' => $value ? $this->t('Complete') : $this->t('Incomplete'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formatText(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->getRawValue($element, $webform_submission, $options) ? '1' : '0';
  }

}
