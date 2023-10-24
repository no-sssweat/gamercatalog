<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form before clearing out the examples.
 */
class ConfirmClearPagePathTableForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gac_clear_page_path_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear pagepath - pageview table?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $itemsCount = \Drupal::database()->select('google_analytics_counter')->countQuery()->execute()->fetchField() ?? 0;
    return $this->t('There are @count items in that table. The clear process cannot be undone, the next cron run will save new data to it later.', [
      '@count' => $itemsCount,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('google_analytics_counter.admin_settings_form');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::database()->truncate('google_analytics_counter')->execute();
    \Drupal::state()->set('google_analytics_counter.total_paths', 0);
    \Drupal::queue('google_analytics_counter_worker')->deleteQueue();
    Cache::invalidateTags(['google_analytics_counter_data']);
    \Drupal::messenger()->addStatus(
      $this->t('Pagepath - pageview table and Google Analytics Counter queue cleared.')
    );
    $form_state->setRedirect('google_analytics_counter.admin_settings_form');
  }

}
