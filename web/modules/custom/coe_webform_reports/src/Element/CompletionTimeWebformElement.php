<?php

namespace Drupal\coe_webform_reports\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'coe_webform_reports'.
 *
 * Webform elements are just wrappers around form elements, therefore every
 * webform element must have correspondent FormElement.
 *
 * Below is the definition for a custom 'coe_webform_reports' which just
 * renders a simple text field.
 *
 * @FormElement("completion_time_element")
 *
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21FormElement.php/class/FormElement
 * @see \Drupal\Core\Render\Element\RenderElement
 * @see https://api.drupal.org/api/drupal/namespace/Drupal%21Core%21Render%21Element
 * @see \Drupal\webform_example_element\Element\WebformExampleElement
 */
class CompletionTimeWebformElement extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#process' => [
        [$class, 'processCompletionTimeWebformElement'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateCompletionTimeWebformElement'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCompletionTimeWebformElement'],
      ],
      '#theme' => 'input__coe_webform_reports',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Processes a 'coe_webform_reports' element.
   */
  public static function processCompletionTimeWebformElement(&$element, FormStateInterface $form_state, &$complete_form) {
    // Here you can add and manipulate your element's properties and callbacks.
    return $element;
  }

  /**
   * Webform element validation handler for #type 'coe_webform_reports'.
   */
  public static function validateCompletionTimeWebformElement(&$element, FormStateInterface $form_state, &$complete_form) {
    // Here you can add custom validation logic.
  }

  /**
   * Prepares a #type 'email_multiple' render element for theme_element().
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for theme_element().
   */
  public static function preRenderCompletionTimeWebformElement(array $element) {
    $element['#attributes']['type'] = 'text';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-text', 'webform-example-element']);
    return $element;
  }

}
