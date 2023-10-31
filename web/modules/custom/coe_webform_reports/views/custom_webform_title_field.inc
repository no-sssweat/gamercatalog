<?php

/**
 * Custom Views field handler for Webform title.
 */
class custom_webform_title_field extends views_handler_field {

  /**
   * Render the field.
   */
  public function render($values) {
    // Get the machine name of the Webform from the Views row.
    $webform_machine_name = $this->get_value($values);

    // Load the Webform entity.
    $webform = \Drupal\webform\Entity\Webform::load($webform_machine_name);

    // Check if the Webform exists and has a title.
    if ($webform) {
      return $webform->label();
    }

    return '';
  }
}
