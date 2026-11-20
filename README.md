# User Registration Mobile

Adds a **Mobile** field to Drupal user accounts, validated as an Australian
mobile number. The field appears on the registration, admin "Add user" and
account edit forms, and everything about it (where it shows, whether it is
required, its wording, uniqueness and saved format) is managed from one
settings page with no field UI setup needed.

- **Machine name:** `user_registration_mobile`
- **Field:** `mobile` on the `user` entity
- **Requires:** Drupal `^11.1 || ^12`, core User module

## Features

### Mobile field on user accounts
- Adds `mobile` as a **base field** on the user entity. It is stored
  directly on the `users_field_data` table (`varchar(20)`), not as a separate
  Field UI field.
- Works anywhere user fields work. Read it in code with
  `$user->get('mobile')->value`, and add it to Views as a User field.
- Showing the number on the user profile page is optional. Turn it on under
  **Configuration → People → Account settings → Manage display**. The form
  widget is fixed and is controlled by this module's settings instead.

### Choose which forms show it
The field can be switched on or off separately for each form:

| Form | Path |
|---|---|
| Public registration | `/user/register` |
| Admin "Add user" | `/admin/people/create` |
| Account edit | `/user/{uid}/edit` |

Hiding the field on a form never deletes data. The saved number is kept and
left unchanged when that form is submitted.

A master **Show the Mobile field** switch hides it everywhere at once, also
without deleting saved numbers.

### Mandatory or optional
- **Mandatory** makes the number required, but only on the forms where the
  field is shown, so hiding it on one form never blocks that form.
- When optional, an empty value is allowed. Anything entered is still
  validated.

### Australian mobile validation
Accepts Australian mobile numbers written in any of these styles, with or
without spaces:

- `0412 456 789`, `0412456789`
- `+61 412 456 789`, `+61412456789`, `61412456789`

Anything else (landlines such as `03…`, short numbers, letters, dashes or
brackets) is rejected with a configurable error message.

### Unique numbers
- Optionally requires each mobile number to belong to **only one account**.
- Duplicates are detected whichever format they were typed or saved in, so
  `0412 456 789` and `+61412456789` count as the same number.
- When editing an account, that account's own number doesn't count as a
  duplicate.
- Has a configurable duplicate-number message, plus optional text added to
  the field's help text, e.g. *"Mobile number must be unique to user."*

### Consistent saved format
Every valid number is normalised before it is saved, in one of two formats:

| Setting | Saved as | Notes |
|---|---|---|
| International (default) | `+61412456789` | Best for SMS gateways and APIs |
| Local | `0412 456 789` | Easier for people to read |

**Changing the format converts all existing numbers.** Saving the settings
with a new format starts a batch job that rewrites every saved number, 50
users per step. When it finishes, it reports how many numbers were converted,
how many were already correct, and how many were left alone because they
aren't valid Australian mobiles.

### Customisable wording and position
All text on the field can be edited from the settings page:

- Label (default *Mobile*)
- Placeholder (default *eg 0412 456 789*)
- Help text (default *Australian mobile number.*)
- Invalid-number error message
- Duplicate-number message and help-text suffix
- **Weight**: the field's position on the form, from -100 to 100. Lower
  numbers appear higher.

Text settings are typed as translatable in the config schema, but the module
doesn't yet ship a `*.config_translation.yml` file, so there is no translation
tab for them in the admin UI.

### Tokens
Adds tokens for the mobile number, for use anywhere Drupal tokens are
replaced: account emails (**Configuration → People → Account settings**),
Webform and ECA messages, Pathauto patterns and so on.

| Token | Example output |
|---|---|
| `[user:mobile]` | The number as saved, in whichever format the settings use |
| `[user:mobile-local]` | `0412 456 789` |
| `[user:mobile-international]` | `+61412456789` (best for SMS services) |

The local and international tokens always output their own format, whatever
the saved format is. When a user has no number, the tokens are left
unreplaced, so the usual "clear" option removes them. A saved value that
isn't a valid Australian mobile is output as-is.

### Permissions
- **Administer user mobile field settings**
  (`administer user mobile`) controls access to the settings page. It is
  marked as restricted, so grant it only to trusted roles.

### Clean uninstall
Uninstalling the module removes the `mobile` column **and every saved
mobile number**, along with the module's settings, then clears cached user
data. Back up the database first if you might need the numbers again.

## Installation

### With Composer (recommended)
The module isn't on drupal.org, so first tell Composer where to find it:

```bash
composer config repositories.user_registration_mobile vcs https://github.com/waynelevin/user_registration_mobile
composer require drupal/user_registration_mobile:dev-main
drush en user_registration_mobile -y
```

Composer installs it to `web/modules/custom/user_registration_mobile`
(the package type is `drupal-custom-module`).

### Manually
Copy this folder to `web/modules/custom/user_registration_mobile`, then
enable it with `drush en user_registration_mobile -y` or from
**Extend** (`/admin/modules`).

## Configuration

Go to **Configuration → People → User mobile field**
(`/admin/config/people/user-mobile`).

Out of the box the field is shown on all three forms, is mandatory, must be
unique, and saves numbers in international format.

> **Public registration:** the field can only appear on `/user/register` if
> visitors are allowed to register. Check **Configuration → People → Account
> settings → Who can register accounts?**, since sites installed with
> `drush site:install` default to *Administrators only*.

### Settings reference
Settings are stored in `user_registration_mobile.settings` and can be
exported with configuration management.

| Key | Default | Purpose |
|---|---|---|
| `enabled` | `true` | Show the field at all |
| `form_register` | `true` | Show on `/user/register` |
| `form_admin_create` | `true` | Show on `/admin/people/create` |
| `form_edit` | `true` | Show on the account edit form |
| `required` | `true` | Mandatory where shown |
| `label` | `Mobile` | Field label |
| `placeholder` | `eg 0412 456 789` | Placeholder text |
| `help_text` | `Australian mobile number.` | Help text under the field |
| `error_message` | `Please enter a valid Australian mobile number (eg 0412 456 789)` | Invalid-number message |
| `weight` | `10` | Position on the form |
| `unique` | `true` | One account per number |
| `unique_help_suffix` | ` Mobile number must be unique to user.` | Added to the help text when `unique` is on |
| `unique_message` | `This mobile number is already used by another account.` | Duplicate-number message |
| `format` | `international` | `international` or `local` |

## Notes for developers

- Hooks use Drupal 11.1+ attribute-based hook classes
  (`src/Hook/UserMobileHooks.php`), which is why 11.1 is the minimum version.
- `UserMobileHooks::formats(string $mobile)` returns
  `['local' => '0412 456 789', 'international' => '+61412456789']` for a valid
  number, or `NULL` if it isn't valid. Use it to validate or format numbers
  elsewhere.
- Validation and normalisation run as a form `#validate` handler, so numbers
  saved through code (`$user->set('mobile', …)->save()`), migrations or
  REST/JSON:API are **not** validated or normalised.
- The format-conversion batch updates `users_field_data` directly and resets
  the user cache. It does not save user entities, so `hook_user_update()` and
  similar hooks don't run for converted numbers.
- Update hooks `11001`–`11008` bring sites on older versions of the module up
  to the current settings. Run `drush updb` after updating the code.

## Uninstalling

```bash
drush pm:uninstall user_registration_mobile -y
```

This permanently deletes all saved mobile numbers.
