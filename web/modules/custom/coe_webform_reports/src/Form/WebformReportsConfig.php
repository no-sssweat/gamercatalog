<?php

namespace Drupal\coe_webform_reports\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form.
 */
class WebformReportsConfig extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['coe_webform_reports.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'coe_webform_reports_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('coe_webform_reports.settings');
    // Your form elements go here.
    $form['ga4_property_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GA4 Property ID'),
      '#description' => $this->t('This is a numeric ID. Ex: 413264721'),
      '#default_value' => $config->get('ga4_property_id'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save configuration values.
    $this->config('coe_webform_reports.settings')
      ->set('ga4_property_id', $form_state->getValue('ga4_property_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
