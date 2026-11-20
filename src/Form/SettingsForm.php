<?php

declare(strict_types=1);

namespace Drupal\user_registration_mobile\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user_registration_mobile\MobileFormatBatch;

/**
 * Settings form for the user Mobile field.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'user_registration_mobile_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['user_registration_mobile.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $target = 'user_registration_mobile.settings:';
    $config = $this->config('user_registration_mobile.settings');
    $when_enabled = [':input[name="enabled"]' => ['checked' => TRUE]];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the Mobile field'),
      '#description' => $this->t('Turning it off hides the field from every form but keeps any numbers already saved.'),
      '#default_value' => $config->get('enabled') ?? TRUE,
      '#config_target' => $target . 'enabled',
    ];

    $form['forms'] = [
      '#type' => 'details',
      '#title' => $this->t('Which forms show the field'),
      '#open' => TRUE,
      '#states' => ['visible' => $when_enabled],
    ];
    $form['forms']['form_register'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Registration form (/user/register)'),
      '#description' => $this->t('The public sign-up form for new visitors.'),
      '#default_value' => (bool) ($config->get('form_register') ?? TRUE),
      '#config_target' => $target . 'form_register',
    ];
    $form['forms']['form_admin_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Admin Add user form (/admin/people/create)'),
      '#description' => $this->t('The form administrators use to create accounts for other people.'),
      '#default_value' => (bool) ($config->get('form_admin_create') ?? TRUE),
      '#config_target' => $target . 'form_admin_create',
    ];
    $form['forms']['form_edit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Account edit form'),
      '#description' => $this->t('The Edit tab on a user account (/user/ID/edit), used by the account owner and administrators.'),
      '#default_value' => (bool) ($config->get('form_edit') ?? TRUE),
      '#config_target' => $target . 'form_edit',
    ];

    $form['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mandatory'),
      '#description' => $this->t('Users must enter a mobile number on the forms that show the field.'),
      '#default_value' => $config->get('required') ?? TRUE,
      '#config_target' => $target . 'required',
      '#states' => ['disabled' => [':input[name="enabled"]' => ['checked' => FALSE]]],
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $config->get('label') ?? 'Mobile',
      '#config_target' => $target . 'label',
    ];

    $form['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder text'),
      '#description' => $this->t('Shown inside the empty field. Leave empty for none.'),
      '#maxlength' => 255,
      '#default_value' => $config->get('placeholder') ?? '',
      '#config_target' => $target . 'placeholder',
    ];

    $form['help_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Help text'),
      '#description' => $this->t('Shown under the field. Leave empty for none.'),
      '#maxlength' => 512,
      '#default_value' => $config->get('help_text') ?? '',
      '#config_target' => $target . 'help_text',
    ];

    $form['error_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('Shown when the number is not a valid Australian mobile number.'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $config->get('error_message') ?? '',
      '#config_target' => $target . 'error_message',
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight (order)'),
      '#description' => $this->t('Position of the field on the form. Lower numbers appear higher. Compare with the weights under Account settings > Manage form display.'),
      '#step' => 1,
      '#min' => -100,
      '#max' => 100,
      '#required' => TRUE,
      '#default_value' => $config->get('weight') ?? 10,
      '#config_target' => $target . 'weight',
    ];

    $form['unique_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Unique mobile numbers'),
      '#open' => TRUE,
    ];

    $form['unique_settings']['unique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mobile unique to user'),
      '#description' => $this->t('Each mobile number can only be used by one account.'),
      '#default_value' => $config->get('unique') ?? TRUE,
      '#config_target' => $target . 'unique',
    ];

    $form['unique_settings']['unique_help_suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text added to the help text'),
      '#description' => $this->t('Added to the end of the help text under the field while this is on. Start with a space. Leave empty to add nothing.'),
      '#maxlength' => 255,
      '#default_value' => $config->get('unique_help_suffix') ?? '',
      '#config_target' => $target . 'unique_help_suffix',
      '#states' => ['visible' => [':input[name="unique"]' => ['checked' => TRUE]]],
    ];

    $form['unique_settings']['unique_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Duplicate number message'),
      '#description' => $this->t('Shown when the number is already on another account.'),
      '#maxlength' => 255,
      '#default_value' => $config->get('unique_message') ?? '',
      '#config_target' => $target . 'unique_message',
      '#states' => ['visible' => [':input[name="unique"]' => ['checked' => TRUE]]],
    ];

    $form['format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Saved format'),
      '#description' => $this->t('How numbers are saved. Changing this and saving converts every number already saved to the new format.'),
      '#options' => [
        'local' => $this->t('Local: 0412 456 789'),
        'international' => $this->t('International: +61412456789 (best for SMS services)'),
      ],
      '#default_value' => $config->get('format') ?? 'international',
      '#config_target' => $target . 'format',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('unique') && trim((string) $form_state->getValue('unique_message')) === '') {
      $form_state->setErrorByName('unique_message', $this->t('Enter the message to show for a duplicate number.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Saved format before this save, to spot a change.
    $old_format = \Drupal::config('user_registration_mobile.settings')->get('format') ?? 'local';

    parent::submitForm($form, $form_state);

    // Convert every saved number when the format changes.
    $new_format = (string) $form_state->getValue('format');
    if ($new_format !== $old_format && in_array($new_format, ['local', 'international'], TRUE)) {
      batch_set(MobileFormatBatch::build($new_format));
    }

    // The label, placeholder, help text (with its unique-number note) and
    // weight are part of the field definition.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

}
