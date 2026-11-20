<?php

declare(strict_types=1);

namespace Drupal\user_registration_mobile\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides and validates the Mobile field on user accounts.
 */
class UserMobileHooks {

  /**
   * Machine name of the field this module creates.
   */
  public const FIELD = 'mobile';

  /**
   * The module's config object.
   */
  public const CONFIG = 'user_registration_mobile.settings';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'user') {
      return [];
    }

    $config = $this->configFactory->get(self::CONFIG);
    $enabled = (bool) ($config->get('enabled') ?? TRUE);

    $help = (string) ($config->get('help_text') ?? '');
    if ($config->get('unique')) {
      $help .= (string) ($config->get('unique_help_suffix') ?? '');
    }

    // "Mandatory" is applied per form in alterForm(), so the field can be
    // hidden on some forms without blocking them.
    $field = BaseFieldDefinition::create('string')
      ->setLabel((string) ($config->get('label') ?: 'Mobile'))
      ->setDescription(trim($help))
      ->setSetting('max_length', 20)
      // Fixed widget. Whether it shows on the profile page is set under
      // Account settings > Manage display.
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    if ($enabled) {
      $field->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => (int) ($config->get('weight') ?? 10),
        'settings' => [
          'size' => 20,
          'placeholder' => (string) ($config->get('placeholder') ?? ''),
        ],
      ]);
    }
    else {
      $field->setDisplayOptions('form', ['region' => 'hidden']);
    }

    return [self::FIELD => $field];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_register_form.
   */
  #[Hook('form_user_register_form_alter')]
  public function formUserRegisterFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $setting = $this->routeMatch->getRouteName() === 'user.admin_create' ? 'form_admin_create' : 'form_register';
    $this->alterForm($form, $setting);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_form (account edit).
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $this->alterForm($form, 'form_edit');
  }

  /**
   * Shows or hides the field, sets "required" and adds validation.
   */
  protected function alterForm(array &$form, string $setting): void {
    $config = $this->configFactory->get(self::CONFIG);

    // Rebuild cached forms (e.g. the anonymous registration page) on save.
    $cache = CacheableMetadata::createFromRenderArray($form);
    $cache->addCacheableDependency($config);
    $cache->addCacheTags(['entity_field_info']);
    $cache->addCacheContexts(['route.name']);
    $cache->applyTo($form);

    if (!isset($form[self::FIELD])) {
      return;
    }

    // Hidden on this form: the saved number is kept unchanged.
    if (!($config->get($setting) ?? TRUE)) {
      $form[self::FIELD]['#access'] = FALSE;
      return;
    }

    if ($config->get('required') && isset($form[self::FIELD]['widget'][0]['value'])) {
      $form[self::FIELD]['widget'][0]['value']['#required'] = TRUE;
    }
    $form['#validate'][] = [self::class, 'validateMobile'];
  }

  /**
   * Validates the mobile number, checks it is unique and normalises it.
   *
   * Accepts 04xx xxx xxx or +61 4xx xxx xxx with any spacing.
   */
  public static function validateMobile(array &$form, FormStateInterface $form_state): void {
    $config = \Drupal::config(self::CONFIG);
    $value = $form_state->getValue(self::FIELD);
    $mobile = is_array($value) ? ($value[0]['value'] ?? '') : (string) $value;
    $mobile = trim((string) $mobile);

    // Empty values are handled by the "Mandatory" setting.
    if ($mobile === '') {
      return;
    }

    if (self::formats($mobile) === NULL) {
      $message = (string) ($config->get('error_message')
        ?: 'Please enter a valid Australian mobile number (eg 0412 456 789)');
      $form_state->setErrorByName(self::FIELD, $message);
      return;
    }

    ['local' => $local, 'international' => $international] = self::formats($mobile);

    if ($config->get('unique')) {
      $form_object = $form_state->getFormObject();
      $uid = $form_object instanceof EntityFormInterface ? $form_object->getEntity()->id() : NULL;

      $query = \Drupal::database()->select('users_field_data', 'u')
        ->fields('u', ['uid'])
        ->condition('u.' . self::FIELD, [$local, $international], 'IN')
        ->range(0, 1);
      if ($uid) {
        $query->condition('u.uid', $uid, '<>');
      }
      if ($query->execute()->fetchField()) {
        $message = (string) ($config->get('unique_message')
          ?: new TranslatableMarkup('This mobile number is already used by another account.'));
        $form_state->setErrorByName(self::FIELD, $message);
        return;
      }
    }

    $formatted = $config->get('format') === 'international' ? $international : $local;
    $form_state->setValue([self::FIELD, 0, 'value'], $formatted);
  }

  /**
   * Returns a mobile number in both formats, or NULL if it is not valid.
   *
   * Accepts 04xx xxx xxx or +61 4xx xxx xxx with any spacing.
   *
   * @return array{local: string, international: string}|null
   *   "0412 456 789" and "+61412456789" style values.
   */
  public static function formats(string $mobile): ?array {
    $mobile = trim($mobile);
    if (!preg_match('/^(?:\+?61\s?|0)4(?:\s?\d){8}$/', $mobile)) {
      return NULL;
    }
    $digits = preg_replace('/\D/', '', $mobile);
    if (str_starts_with($digits, '61')) {
      $digits = '0' . substr($digits, 2);
    }
    return [
      'local' => substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 3),
      'international' => '+61' . substr($digits, 1),
    ];
  }

}
