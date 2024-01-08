<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Filter to handle dates stored as a timestamp.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("date")
 */
class Date extends NumericFilter {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (!$form_state->get('exposed')) {
      // Use default values from options on the config form.
      foreach (['min', 'max', 'value'] as $component) {
        if (isset($this->options['value'][$component]) && isset($form['value'][$component])) {
          $form['value'][$component]['#default_value'] = $this->options['value'][$component];

          // Add description.
          $form['value'][$component]['#description'] = $this->t('A date in any machine readable format (CCYY-MM-DD is preferred) or an offset from the current time such as "@example1" or "@example2".', [
            '@example1' => '+1 day',
            '@example2' => '-2 years -10 days',
          ]);
        }
      }
    }
    else {
      // Convert relative date string representations to actual dates
      // to solve potential datepicker problems.
      foreach (['min', 'max', 'value'] as $component) {
        if (
          isset($form['value'][$component]) &&
          !empty($form['value'][$component]['#default_value']) &&
          preg_match('/[a-zA-Z]+/', $form['value'][$component]['#default_value'])
        ) {
          $form['value'][$component]['#default_value'] = date('Y-m-d', strtotime($form['value'][$component]['#default_value']));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    if (!empty($this->options['exposed']) && $form_state->isValueEmpty([
      'options',
      'expose',
      'required',
    ])) {
      // Who cares what the value is if it's exposed and non-required.
      return;
    }

    $this->validateValidTime($form['value'], $form_state, $form_state->getValue(['options', 'operator']), $form_state->getValue(['options', 'value']));
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    if (empty($this->options['expose']['required'])) {
      // Who cares what the value is if it's exposed and non-required.
      return;
    }

    $value = &$form_state->getValue($this->options['expose']['identifier']);
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id'])) {
      $operator = &$form_state->getValue($this->options['expose']['operator_id']);
    }
    else {
      $operator = $this->operator;
    }

    $this->validateValidTime($this->options['expose']['identifier'], $form_state, $operator, $value);

  }

  /**
   * Validate that the time values convert to something usable.
   */
  public function validateValidTime(&$form, FormStateInterface $form_state, $operator, $value) {
    $operators = $this->operators();

    if ($operators[$operator]['values'] == 1) {
      $convert = strtotime($value['value']);
      if (!empty($form['value']) && ($convert == -1 || $convert === FALSE)) {
        $form_state->setError($form['value'], $this->t('Invalid date format.'));
      }
    }
    elseif ($operators[$operator]['values'] == 2) {
      $min = strtotime($value['min']);
      if ($min == -1 || $min === FALSE) {
        $form_state->setError($form['min'], $this->t('Invalid date format.'));
      }
      $max = strtotime($value['max']);
      if ($max == -1 || $max === FALSE) {
        $form_state->setError($form['max'], $this->t('Invalid date format.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function hasValidGroupedValue(array $group) {
    if (!is_array($group['value']) || empty($group['value'])) {
      return FALSE;
    }

    $operators = $this->operators();
    $expected = $operators[$group['operator']]['values'];
    $actual = count(array_filter($group['value'], 'static::arrayFilterZero'));

    return $actual == $expected;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    $rc = parent::acceptExposedInput($input);

    // Don't filter if value(s) are empty.
    $operators = $this->operators();
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id'])) {
      $operator = $input[$this->options['expose']['operator_id']];
    }
    else {
      $operator = $this->operator;
    }

    if ($operators[$operator]['values'] == 1) {
      // When the operator is either <, <=, =, !=, >=, > or regular_expression
      // the input contains only one value.
      if ($this->value['value'] == '') {
        return FALSE;
      }
    }
    elseif ($operators[$operator]['values'] == 2) {
      // When the operator is either between or not between the input contains
      // at least one value.
      if ($this->value['min'] == '' && $this->value['max'] == '') {
        return FALSE;
      }
    }

    return $rc;
  }

  /**
   * Helper function to get converted values for the query.
   *
   * @return array
   *   Array of timestamps.
   */
  protected function getConvertedValues() {
    $values = [];
    if (!empty($this->value['max']) && !strpos($this->value['max'], ':')) {
      // No time was specified, so make the date range inclusive.
      $this->value['max'] .= ' +1 day';
    }
    foreach (['min', 'max', 'value'] as $component) {
      if (!empty($this->value[$component])) {
        $values[$component] = intval(strtotime($this->value[$component]));
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    $values = $this->getConvertedValues();
    if (empty($values)) {
      // do nothing
      return;
    }
    // Support providing only one value for exposed filters.
    if (empty($values['min'])) {
      $operator = $this->operator === 'between' ? '<=' : '>';
      $this->query->addWhereExpression($this->options['group'], "$field $operator {$values['max']}");
    }
    elseif (empty($values['max'])) {
      $operator = $this->operator === 'between' ? '>=' : '<';
      $this->query->addWhereExpression($this->options['group'], "$field $operator {$values['min']}");
    }
    // Both values given.
    else {
      $operator = strtoupper($this->operator);
      $this->query->addWhereExpression($this->options['group'], "$field $operator {$values['min']} AND {$values['max']}");
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    $values = $this->getConvertedValues();
    $this->query->addWhereExpression($this->options['group'], "$field $this->operator {$values['value']}");
  }

}
