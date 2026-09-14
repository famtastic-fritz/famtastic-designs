<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;

/** Applies bounded, literal search filters before a records query is paged. */
final class OperationsRecordFilter {

  public static function values(array $query): array {
    $values = [];
    foreach (['q', 'status', 'proof', 'from', 'to'] as $key) {
      $values[$key] = is_scalar($query[$key] ?? NULL)
        ? mb_substr(trim((string) $query[$key]), 0, $key === 'q' ? 160 : 64)
        : '';
    }
    foreach (['from', 'to'] as $key) {
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $values[$key]);
      if (!$date || $date->format('Y-m-d') !== $values[$key]) {
        $values[$key] = '';
      }
    }
    return $values;
  }

  /** Field names are supplied only by the controller's fixed record schema. */
  public static function apply(SelectInterface $query, Connection $database, array $values, array $searchFields, array $exactFields, string $dateField): void {
    if ($values['q'] !== '' && $searchFields !== []) {
      $or = $query->orConditionGroup();
      $literal = '%' . $database->escapeLike($values['q']) . '%';
      foreach ($searchFields as $field) {
        $or->condition($field, $literal, 'LIKE');
      }
      $query->condition($or);
    }
    foreach ($exactFields as $key => $field) {
      if (($values[$key] ?? '') !== '') {
        $query->condition($field, $values[$key]);
      }
    }
    if ($values['from'] !== '') {
      $query->condition($dateField, (new \DateTimeImmutable($values['from']))->getTimestamp(), '>=');
    }
    if ($values['to'] !== '') {
      $query->condition($dateField, (new \DateTimeImmutable($values['to']))->modify('+1 day')->getTimestamp(), '<');
    }
  }

}
