<?php

namespace Drupal\coe_webform_reports\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filters by Entity Label.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("custom_entity_label_filter")
 */
class CustomEntityLabelFilter extends InOperator {

  /**
   * Overrides the query() method to add the Entity Label filter.
   */
  public function query() {
    // Ensure the table is added.
    $this->ensureMyTable();

    // Define the field you want to filter by.
    $field = 'entity_label';
    $this->field = "$this->tableAlias.$field";

    parent::query();
  }

}
