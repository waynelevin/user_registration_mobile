<?php

declare(strict_types=1);

namespace Drupal\user_registration_mobile\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Provides [user:mobile] tokens.
 */
class UserMobileTokenHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'tokens' => [
        'user' => [
          'mobile' => [
            'name' => $this->t('Mobile'),
            'description' => $this->t('The mobile number, as saved (local or international, depending on the module settings).'),
          ],
          'mobile-local' => [
            'name' => $this->t('Mobile (local format)'),
            'description' => $this->t('The mobile number in local format, eg 0412 456 789.'),
          ],
          'mobile-international' => [
            'name' => $this->t('Mobile (international format)'),
            'description' => $this->t('The mobile number in international format, eg +61412456789. Best for SMS services.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    if ($type !== 'user' || !(($data['user'] ?? NULL) instanceof UserInterface)) {
      return [];
    }

    /** @var \Drupal\user\UserInterface $account */
    $account = $data['user'];
    if (!$account->hasField(UserMobileHooks::FIELD)) {
      return [];
    }

    $bubbleable_metadata->addCacheableDependency($account);
    $mobile = trim((string) $account->get(UserMobileHooks::FIELD)->value);
    if ($mobile === '') {
      // Leave the tokens unreplaced so the caller's "clear" option applies.
      return [];
    }

    // Numbers saved outside the forms may not be valid; fall back to the raw
    // value rather than outputting nothing.
    $formats = UserMobileHooks::formats($mobile);

    $replacements = [];
    foreach ($tokens as $name => $original) {
      $replacements[$original] = match ($name) {
        'mobile' => $mobile,
        'mobile-local' => $formats['local'] ?? $mobile,
        'mobile-international' => $formats['international'] ?? $mobile,
        default => NULL,
      };
    }
    return array_filter($replacements, fn($value) => $value !== NULL);
  }

}
