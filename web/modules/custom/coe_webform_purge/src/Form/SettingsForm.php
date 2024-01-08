<?php

namespace Drupal\coe_webform_purge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure coe_webform_purge settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'coe_webform_purge_settings';
  }

  public function getWebformConfigName() {
    $route_match = \Drupal::routeMatch();
    return 'coe_webform_purge.' . $route_match->getParameter('webform');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    $webform_config_name = $this->getWebformConfigName();
    return [$webform_config_name];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $webform_config_name = $this->getWebformConfigName();
    $config = $this->config($webform_config_name);

    $form['purging_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow for purging webform submissions'),
      '#default_value' => $config->get('purging_enabled'),
    ];

    $form['frequency'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Purging Frequency'),
      '#states' => [
        'visible' => [
          ':input[name="purging_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['frequency']['frequency_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Number'),
      '#default_value' => $config->get('frequency_number'),
      '#min' => 1,
      '#ajax' => [
        'callback' => [$this, 'updateTypePlural'],
        'wrapper' => 'type-wrapper',
        'event' => 'change',
      ],
      '#required' => [
        'visible' => [':input[name="purging_enabled"]' => ['checked' => TRUE]],
      ],
    ];
    $form['frequency']['frequency_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'seconds' => $this->t('Second'),
        'minutes' => $this->t('Minute'),
        'days' => $this->t('Day'),
        'weeks' => $this->t('Week'),
        'months' => $this->t('Month'),
      ],
      '#default_value' => $config->get('frequency_type'),
      '#prefix' => '<div id="type-wrapper">',
      '#suffix' => '</div>',
      '#required' => [
        'visible' => [':input[name="purging_enabled"]' => ['checked' => TRUE]],
      ],
    ];
    // Update to Plural if greater than 1
    if ($config->get('frequency_number') > 1) {
      $form['frequency']['frequency_type']['#options'] = [
        'seconds' => $this->t('Seconds'),
        'minutes' => $this->t('Minutes'),
        'days' => t('Days'),
        'weeks' => t('Weeks'),
        'months' => t('Months'),
      ];
    }
    // Don't use field set parent on $form_state
    $form['#tree'] = FALSE;

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to update the email field.
   */
  public function updateTypePlural(array &$form, FormStateInterface $form_state) {
    $webform_config_name = $this->getWebformConfigName();
    $config = $this->config($webform_config_name);
    if ($form_state->getValue('frequency_number') == 1) {
      $form['frequency']['frequency_type']['#options'] = [
        'seconds' => $this->t('Second'),
        'minutes' => $this->t('Minute'),
        'days' => t('Day'),
        'weeks' => t('Week'),
        'months' => t('Month'),
      ];
    }
    else {
      $form['frequency']['frequency_type']['#options'] = [
        'seconds' => t('Seconds'),
        'minutes' => t('Minutes'),
        'days' => t('Days'),
        'weeks' => t('Weeks'),
        'months' => t('Months'),
      ];
    }

    return $form['frequency']['frequency_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $webform_config_name = $this->getWebformConfigName();
    $config = $this->config($webform_config_name);
    $stored_value = $config->get('purging_enabled');
    $current_value = $form_state->getValue('purging_enabled');
    // Purging has been enabled, run batch.
    if ($stored_value != $current_value && $current_value) {
      $this->runBatch($form_state);
    }

    $webform_config_name = $this->getWebformConfigName();
    $freqency_type = $form_state->getValue('frequency_type');
    $this->config($webform_config_name)
      ->set('purging_enabled', $form_state->getValue('purging_enabled'))
      ->set('frequency_number', $form_state->getValue('frequency_number'))
      ->set('frequency_type', $form_state->getValue('frequency_type'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /*
   * Runs the batch
   */
  private function runBatch(FormStateInterface $form_state) {
    // Get the current route match.
    $current_route_match = \Drupal::routeMatch();
    // Get the route parameters.
    $webform_id = $current_route_match->getParameters()->get('webform');

    // Create a batch to process the webform submissions.
    $batch = [
      'title' => t('Loading Webform Submissions'),
      'operations' => [
        ['coe_webform_purge_load_webform_submissions', [$webform_id]],
      ],
      'finished' => 'custom_module_batch_finished',
      'progress_message' => t('Processed @current out of @total submissions.'),
    ];

    batch_set($batch);
  }

}
