<?php

namespace Drupal\coe_webform_reports\Plugin\views\field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Custom field handler to display a custom field without a database table.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("google_sheets_view_link")
 */
class GoogleSheetsViewLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // leave empty as this field has no db table.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $wsid = $values->sid;
    $webform_submission = $values->_entity;
    $webform_id = $webform_submission->getWebform()->id();

    /** @var \Drupal\coe_webform_enhancements\Service\GoogleSheetsService */
    $sheetsService = \Drupal::service('coe_webform_enhancements.google_sheets');
    $google_sheets_authenticated = $sheetsService->GoogleSheetsAuth($webform_id);
    if ($google_sheets_authenticated) {
      $spreadsheet_submission_url = $sheetsService->getWebformSubmissionSpreadsheetRangeUrl($wsid);

      if ($spreadsheet_submission_url != NULL) {
        $link = '<a href="' . Xss::filterAdmin($spreadsheet_submission_url) . '" target="_blank">View</a>';
        // Use Markup class to ensure HTML is not escaped
        return Markup::create($link);
      }
    }

  }

}
