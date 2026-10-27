<?php

/**
 * @file
 * Functions to support Seven theme settings.
 */

declare(strict_types=1);

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for system_theme_settings.
 */
function seven_form_system_theme_settings_alter(&$form, FormStateInterface $form_state): void {
  $setting_name = 'enable_block_contextual_links';

  $form['seven_settings'] = [
    '#type' => 'fieldset',
    '#title' => t('Seven utilities'),
  ];

  $form['seven_settings'][$setting_name] = [
    '#type' => 'checkbox',
    '#title' => t('Enable contextual links for blocks'),
    '#default_value' => DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.3.0',
      fn() => \Drupal::service('Drupal\Core\Extension\ThemeSettingsProvider')->getSetting($setting_name),
      fn() => theme_get_setting($setting_name),
    ),
  ];
}
