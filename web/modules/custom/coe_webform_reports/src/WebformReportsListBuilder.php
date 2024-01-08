<?php

namespace Drupal\coe_webform_reports;

use Drupal\Core\Entity\EntityInterface;
use Drupal\webform\Element\WebformHtmlEditor;
use Drupal\webform\WebformEntityListBuilder;
use Drupal\webform\WebformInterface;

/**
 * Overrides the WebformEntityListBuilder class.
 */
class WebformReportsListBuilder extends WebformEntityListBuilder {

  /**
   * Overrides the buildHeader method to customize the header output.
   */
  public function buildHeader() {
    $header['title'] = [
      'data' => $this->t('Title'),
      'specifier' => 'title',
      'field' => 'title',
      'sort' => 'asc',
    ];
    $header['description'] = [
      'data' => $this->t('Description'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
      'specifier' => 'description',
      'field' => 'description',
    ];
    $header['category'] = [
      'data' => $this->t('Category'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
      'specifier' => 'category',
      'field' => 'category',
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
      'specifier' => 'status',
      'field' => 'status',
    ];
    $header['owner'] = [
      'data' => $this->t('Author'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
      'specifier' => 'uid',
      'field' => 'uid',
    ];
    $header['results'] = [
      'data' => $this->t('Results'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      'specifier' => 'results',
      'field' => 'results',
    ];
    $header['view_count'] = [
      'data' => $this->t('View Count'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      'specifier' => 'total_count',
      'field' => 'view_count',
    ];
    $header['avg_time'] = [
      'data' => $this->t('Avg completion time'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      'specifier' => 'avg_time',
      'field' => 'avg_time',
    ];
    $header['operations'] = [
      'data' => $this->t('Operations'),
    ];
    return $header;
  }

  /**
   * Overrides the buildRow method to customize the row output.
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\webform\WebformInterface $entity */

    // Title.
    //
    // ISSUE: Webforms that the current user can't access are not being hidden via the EntityQuery.
    // WORK-AROUND: Don't link to the webform.
    // See: Access control is not applied to config entity queries
    // https://www.drupal.org/node/2636066
    $row['title']['data']['title'] = ['#markup' => ($entity->access('submission_page')) ? $entity->toLink()->toString() : $entity->label()];
    if ($entity->isTemplate()) {
      $row['title']['data']['template'] = ['#markup' => ' <b>(' . $this->t('Template') . ')</b>'];
    }

    // Description.
    $row['description']['data'] = WebformHtmlEditor::checkMarkup($entity->get('description'));

    // Category.
    $row['category']['data']['#markup'] = $entity->get('category');

    // Status.
    $t_args = ['@label' => $entity->label()];
    if ($entity->isArchived()) {
      $row['status']['data'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Archived'),
        '#attributes' => ['aria-label' => $this->t('@label is archived', $t_args)],
      ];
      $row['status'] = $this->t('Archived');
    }
    else {
      switch ($entity->get('status')) {
        case WebformInterface::STATUS_OPEN:
          $status = $this->t('Open');
          $aria_label = $this->t('@label is open', $t_args);
          break;

        case WebformInterface::STATUS_CLOSED:
          $status = $this->t('Closed');
          $aria_label = $this->t('@label is closed', $t_args);
          break;

        case WebformInterface::STATUS_SCHEDULED:
          $status = $this->t('Scheduled (@state)', ['@state' => $entity->isOpen() ? $this->t('Open') : $this->t('Closed')]);
          $aria_label = $this->t('@label is scheduled and is @state', $t_args + ['@state' => $entity->isOpen() ? $this->t('open') : $this->t('closed')]);
          break;

        default:
          return [];
      }

      if ($entity->access('update')) {
        $row['status']['data'] = $entity->toLink($status, 'settings-form', ['query' => $this->getDestinationArray()])->toRenderable() + [
            '#attributes' => ['aria-label' => $aria_label],
          ];
      }
      else {
        $row['status']['data'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $status,
          '#attributes' => ['aria-label' => $aria_label],
        ];
      }
    }

    // Owner.
    $row['owner'] = ($owner = $entity->getOwner()) ? $owner->toLink() : '';

    // Results.
    $result_total = $this->totalNumberOfResults[$entity->id()];
    $results_disabled = $entity->isResultsDisabled();
    $results_access = $entity->access('submission_view_any');
    if ($results_disabled || !$results_access) {
      $row['results'] = ($result_total ? $result_total : '')
        . ($result_total && $results_disabled ? ' ' : '')
        . ($results_disabled ? $this->t('(Disabled)') : '');
    }
    else {
      $row['results'] = [
        'data' => [
          '#type' => 'link',
          '#title' => $result_total,
          '#attributes' => [
            'aria-label' => $this->formatPlural($result_total, '@count result for @label', '@count results for @label', ['@label' => $entity->label()]),
          ],
          '#url' => $entity->toUrl('results-submissions'),
        ],
      ];
    }

    // Total Views.
    $webform_id = $entity->id();
    $count = \Drupal::service('coe_webform_reports.view_count')->getViewCount($webform_id);
    $row['view_count'] = $count;

    // Avg completion time.
    $avg_time = \Drupal::service('coe_webform_reports.view_count')->getAverageTime($webform_id);
    $row['avg_time'] = \Drupal::service('coe_webform_reports.view_count')->convertSecondsToTime($avg_time);

    // Operations.
    return $row + parent::buildRow($entity);
  }

  protected function getEntityIds() {
    $header = $this->buildHeader();
    if ($this->request->query->get('order') === (string) $header['results']['data']) {
      $entity_ids = $this->getQuery($this->keys, $this->category, $this->state)
        ->execute();
      // Make sure all entity ids have totals.
      $this->totalNumberOfResults += array_fill_keys($entity_ids, 0);

      // Calculate totals.
      // @see \Drupal\webform\WebformEntityStorage::getTotalNumberOfResults
      if ($entity_ids) {
        $query = $this->database->select('webform_submission', 'ws');
        $query->fields('ws', ['webform_id']);
        $query->condition('webform_id', $entity_ids, 'IN');
        $query->addExpression('COUNT(sid)', 'results');
        $query->groupBy('webform_id');
        $totals = array_map('intval', $query->execute()->fetchAllKeyed());
        foreach ($totals as $entity_id => $total) {
          $this->totalNumberOfResults[$entity_id] = $total;
        }
      }

      // Sort totals.
      asort($this->totalNumberOfResults, SORT_NUMERIC);
      if ($this->request->query->get('sort') === 'desc') {
        $this->totalNumberOfResults = array_reverse($this->totalNumberOfResults, TRUE);
      }

      // Build an associative array of entity ids from totals.
      $entity_ids = array_keys($this->totalNumberOfResults);
      $entity_ids = array_combine($entity_ids, $entity_ids);

      // Manually initialize and apply paging to the entity ids.
      $page = $this->request->query->get('page') ?: 0;
      $total = count($entity_ids);
      $limit = $this->getLimit();
      $start = ($page * $limit);
      \Drupal::service('pager.manager')->createPager($total, $limit);
      return array_slice($entity_ids, $start, $limit, TRUE);
    }

    $query = $this->getQuery($this->keys, $this->category, $this->state);
    $query->tableSort($header);
    $query->pager($this->getLimit());
    $entity_ids = $query->execute();

    // Calculate totals.
    // @see \Drupal\webform\WebformEntityStorage::getTotalNumberOfResults
    if ($entity_ids) {
      $query = $this->database->select('webform_submission', 'ws');
      $query->fields('ws', ['webform_id']);
      $query->condition('webform_id', $entity_ids, 'IN');
      $query->addExpression('COUNT(sid)', 'results');
      $query->groupBy('webform_id');
      $this->totalNumberOfResults = array_map('intval', $query->execute()->fetchAllKeyed());
    }

    // Make sure all entity ids have totals.
    $this->totalNumberOfResults += array_fill_keys($entity_ids, 0);

    if ($this->request->query->get('order') === 'View Count') {
      $page = $this->request->query->get('page') ?: 0;
      $limit = $this->getLimit();
      $start = ($page * $limit);
      $sort_direction = $this->request->query->get('sort');
      $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
        ->fields('coe_rvc', ['view_count', 'id'])
        ->orderBy('view_count', $sort_direction)
        ->range($start, $limit)
        ->execute();

      $rows = $query->fetchAll();
      $entity_ids = [];
      foreach ($rows as $row) {
        $webform_id = $row->id;
        $entity_ids[] = $webform_id;
      }
    }
    if ($this->request->query->get('order') === 'Avg completion time') {
      $page = $this->request->query->get('page') ?: 0;
      $limit = $this->getLimit();
      $start = ($page * $limit);
      $sort_direction = $this->request->query->get('sort');
      $query = $this->database->select('coe_webform_reports_view_count', 'coe_rvc')
        ->fields('coe_rvc', ['average_time', 'id'])
        ->orderBy('average_time', $sort_direction)
        ->range($start, $limit)
        ->execute();

      $rows = $query->fetchAll();
      $entity_ids = [];
      foreach ($rows as $row) {
        $webform_id = $row->id;
        $entity_ids[] = $webform_id;
      }
    }

    return $entity_ids;
  }

}
