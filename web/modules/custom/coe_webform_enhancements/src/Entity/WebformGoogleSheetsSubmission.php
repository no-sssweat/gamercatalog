<?php

namespace Drupal\coe_webform_enhancements\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the WebformGoogleSheetsSubmission entity used to track submissions stored in a Google Spreadsheet.
 *
 * @ingroup coe_webform_enhancements
 *
 * @ContentEntityType(
 *   id = "webform_googlesheets_submission",
 *   label = @Translation("Webform Google Sheets Submission"),
 *   base_table = "webform_googlesheets_submission",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "storage_schema" = "Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   links = {
 *     "collection" = "/admin/webform-googlesheets-submissions"
 *   },
 * )
 */
class WebformGoogleSheetsSubmission extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the WebformGoogleSheetsSubmission entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the WebformGoogleSheetsSubmission entity.'))
      ->setReadOnly(TRUE);

    $fields['spreadsheet_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Spreadsheet ID'))
      ->setDescription(t('The Google Spreadsheet ID where this submission is stored.'))
      ->setRequired(TRUE);

    $fields['valuerange'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Value Range'))
      ->setDescription(t('The Value Range that represents this submission'))
      ->setRequired(TRUE);

    return $fields;
  }

}
