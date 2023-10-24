<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form before clearing out the examples.
 */
class ConfirmClearQueueForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gac_clear_queue_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear the Google Analytics Counter queue?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $itemsCount = \Drupal::queue('google_analytics_counter_worker')->numberOfItems() ?? 0;
    return $this->t('There are @count items in the queue. The clear process cannot be undone, the next cron run will start building the queue again.', [
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
    \Drupal::queue('google_analytics_counter_worker')->deleteQueue();
    Cache::invalidateTags(['google_analytics_counter_data']);
    \Drupal::messenger()->addStatus(
      $this->t('Google Analytics Counter queue cleared.')
    );
    $form_state->setRedirect('google_analytics_counter.admin_settings_form');
  }

}
