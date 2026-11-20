<?php

declare(strict_types=1);

namespace Drupal\user_registration_mobile;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user_registration_mobile\Hook\UserMobileHooks;

/**
 * Batch that converts saved mobile numbers to the chosen format.
 */
final class MobileFormatBatch {

  /**
   * Users processed per batch step.
   */
  private const LIMIT = 50;

  /**
   * Builds the batch definition.
   */
  public static function build(string $format): array {
    return [
      'title' => new TranslatableMarkup('Converting saved mobile numbers'),
      'init_message' => new TranslatableMarkup('Starting...'),
      'progress_message' => new TranslatableMarkup('Working...'),
      'error_message' => new TranslatableMarkup('Converting the mobile numbers failed.'),
      'operations' => [
        [[self::class, 'process'], [$format]],
      ],
      'finished' => [self::class, 'finished'],
    ];
  }

  /**
   * Batch operation: converts one chunk of users.
   */
  public static function process(string $format, array &$context): void {
    $database = \Drupal::database();
    $field = UserMobileHooks::FIELD;

    if (!isset($context['sandbox']['last_uid'])) {
      $context['sandbox']['last_uid'] = 0;
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = (int) $database->select('users_field_data', 'u')
        ->condition("u.$field", '', '<>')
        ->isNotNull("u.$field")
        ->countQuery()
        ->execute()
        ->fetchField();
      $context['results'] = ['converted' => 0, 'unchanged' => 0, 'invalid' => 0];
    }

    $rows = $database->select('users_field_data', 'u')
      ->fields('u', ['uid', 'langcode', $field])
      ->condition("u.$field", '', '<>')
      ->isNotNull("u.$field")
      ->condition('u.uid', $context['sandbox']['last_uid'], '>')
      ->orderBy('u.uid')
      ->range(0, self::LIMIT)
      ->execute()
      ->fetchAll();

    $changed_uids = [];
    foreach ($rows as $row) {
      $context['sandbox']['last_uid'] = (int) $row->uid;
      $context['sandbox']['progress']++;

      $formats = UserMobileHooks::formats((string) $row->{$field});
      if ($formats === NULL) {
        // Not a valid Australian mobile: leave it as it is.
        $context['results']['invalid']++;
        continue;
      }

      $new = $formats[$format] ?? $formats['international'];
      if ($new === $row->{$field}) {
        $context['results']['unchanged']++;
        continue;
      }

      $database->update('users_field_data')
        ->fields([$field => $new])
        ->condition('uid', $row->uid)
        ->condition('langcode', $row->langcode)
        ->execute();
      $context['results']['converted']++;
      $changed_uids[] = (int) $row->uid;
    }

    if ($changed_uids) {
      $changed_uids = array_unique($changed_uids);
      \Drupal::entityTypeManager()->getStorage('user')->resetCache($changed_uids);
      Cache::invalidateTags(array_map(fn(int $uid) => "user:$uid", $changed_uids));
    }

    $context['message'] = new TranslatableMarkup('Checked @done of @max numbers.', [
      '@done' => $context['sandbox']['progress'],
      '@max' => $context['sandbox']['max'],
    ]);
    $context['finished'] = ($rows && $context['sandbox']['max'] > 0)
      ? min(0.99, $context['sandbox']['progress'] / $context['sandbox']['max'])
      : 1;
    if (count($rows) < self::LIMIT) {
      $context['finished'] = 1;
    }
  }

  /**
   * Batch finished callback.
   */
  public static function finished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();
    if (!$success) {
      $messenger->addError(new TranslatableMarkup('Converting the mobile numbers did not finish. Save the settings again to retry.'));
      return;
    }

    Cache::invalidateTags(['user_list']);
    $messenger->addStatus(new TranslatableMarkup('Mobile numbers converted: @converted. Already in this format: @unchanged.', [
      '@converted' => $results['converted'] ?? 0,
      '@unchanged' => $results['unchanged'] ?? 0,
    ]));
    if (!empty($results['invalid'])) {
      $messenger->addWarning(new TranslatableMarkup('@count saved numbers are not valid Australian mobiles and were left unchanged.', [
        '@count' => $results['invalid'],
      ]));
    }
  }

}
