# Silverstripe Phone Number Field

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

Accept only the phone numbers you can actually use: mobile numbers, from the countries you serve,
stored in one format. One form field, or one validator you add to a form you do not own, backed by
Google's libphonenumber.

## A text field is not a phone number

A plain `TextField` with `type="tel"` takes anything: a landline, a premium-rate number, a number
from a country you never message, or "call me". Whatever it takes ends up in the database in the
format the visitor happened to type, and the first place that notices is the code that tries to
send an SMS to it.

That last case has a price tag. SMS pumping works by feeding phone numbers into public forms so
that a site sends messages to number ranges the attacker earns from. Country permissions at the
SMS provider close most of that; the rest is only closed by refusing, at the form, what should
never have been accepted.

## What the module checks

Every number goes through three checks, in this order, and the first failing one is reported:

1. **It is a valid number.** Parsed with libphonenumber against a default region, so "06 1234
   5678" is read as a Dutch number without the visitor typing +31.
2. **Its region is allowed.** The region libphonenumber derives from the number itself, so a
   Jersey number (+44 1534) is `JE`, not `GB`, even though it shares the UK's country code.
   Empty list: any region.
3. **Its type is allowed.** By default `MOBILE` and `FIXED_LINE_OR_MOBILE` (several countries,
   the US among them, do not separate the two ranges). Landlines, premium-rate, shared-cost and
   VoIP numbers are refused.

A number that passes is saved as E.164 (`+31612345678`), whatever was typed. The visitor keeps
seeing their own input in the form. Empty input passes: whether a field is required stays with
`RequiredFields`, as for any other field.

All of this runs offline, from libphonenumber's bundled metadata. No lookup service, no cost per
check.

## Requirements

- Silverstripe 4.13, 5 or 6 (`silverstripe/framework ^4.13 || ^5 || ^6`)
- PHP 8.1 or newer (Silverstripe 6 itself needs 8.3)
- `giggsey/libphonenumber-for-php ^8.13 || ^9` (installed with the module)

`composer.json` is the source of truth for supported versions.

| Branch | Module version | Silverstripe | PHP | libphonenumber |
|---|---|---|---|---|
| `main` | 1.x | 4.13, 5, 6 | 8.1+ | 8.13+, 9 |

One release line covers all three Silverstripe majors. Silverstripe 6 changed the form
validation API (`FormField::validate()` returns a `ValidationResult`, and the validators moved
to `SilverStripe\Forms\Validation`); the module picks the right implementation at runtime, so
nothing changes in how you use it.

libphonenumber 9 turned `PhoneNumberType` into an enum. The module works with either: config
accepts type names or int values on both, and `$check->type` is whatever the installed version
uses, so compare it as `$check->type === PhoneNumberType::MOBILE`, which holds on 8 and 9.
`$check->typeName` is the same string on both. The 8.x line has had no metadata release since
February 2025, so prefer 9 where nothing else holds you on 8.

## Installation

```bash
composer require restruct/silverstripe-phonenumberfield:^1.0
```

## Configuration

Project-wide defaults, in YAML:

```yaml
Restruct\PhoneNumberField\PhoneNumberRules:
  # Region assumed for numbers typed without a country code. ISO 3166-1 alpha-2.
  default_region: 'NL'
  # Regions a number may belong to. Empty: any region.
  allowed_regions: ['NL', 'BE', 'DE']
  # libphonenumber\PhoneNumberType constant names. Empty: any type.
  allowed_types: ['MOBILE', 'FIXED_LINE_OR_MOBILE']
```

Defaults when nothing is set: `NL`, any region, the two mobile types above.

Available type names: `FIXED_LINE`, `MOBILE`, `FIXED_LINE_OR_MOBILE`, `TOLL_FREE`, `PREMIUM_RATE`,
`SHARED_COST`, `VOIP`, `PERSONAL_NUMBER`, `PAGER`, `UAN`, `VOICEMAIL`. An unknown name is ignored.

## Usage

### The field

```php
use Restruct\PhoneNumberField\PhoneNumberField;

PhoneNumberField::create('Mobile', 'Mobile number');
```

Per-field overrides, each falling back to config when not called:

```php
PhoneNumberField::create('Mobile', 'Mobile number')
    ->setDefaultRegion('BE')
    ->setAllowedRegions(['BE', 'NL'])
    ->setAllowedTypes(['MOBILE'])
    ->setNormalise(false);   // save what was typed instead of E.164
```

The field renders as `<input type="tel" autocomplete="tel">`, so phones show the numeric keypad
and browsers can autofill.

### The validator, on a form you cannot change

For a form built elsewhere (a module's form, a userforms form, a CMS edit form) where swapping the
field class is not an option, the same rules can be added as a validator:

```php
use Restruct\PhoneNumberField\PhoneNumberValidator;
use SilverStripe\Forms\CompositeValidator;   // SS6: SilverStripe\Forms\Validation\CompositeValidator
use SilverStripe\Forms\RequiredFields;       // SS6: SilverStripe\Forms\Validation\RequiredFieldsValidator

$form->setValidator(CompositeValidator::create([
    RequiredFields::create('Mobile'),
    PhoneNumberValidator::create(['Mobile']),
]));

// or, when the form already has a CompositeValidator (setValidator() again, because
// CompositeValidator::addValidator() does not hand the form to the added validator, on
// Silverstripe 4.13, 5 and 6 alike):
$form->setValidator(
    $form->getValidator()->addValidator(PhoneNumberValidator::create(['Mobile', 'Phone']))
);

// per-validator overrides:
PhoneNumberValidator::create(['Mobile'], [
    'default_region' => 'BE',
    'allowed_regions' => ['BE'],
    'allowed_types' => ['MOBILE'],
]);
```

The validator refuses, it does not rewrite: the submitted value stays as typed. Normalise where
the data is saved, with `PhoneNumberRules::normalise()`.

### The rules, outside a form

Anything that is about to use a phone number can run the same check, for instance a queued job
right before it sends an SMS to a number stored years ago under older rules:

```php
use Restruct\PhoneNumberField\PhoneNumberRules;

$check = PhoneNumberRules::check($contact->Mobile);
if (!$check->valid) {
    // $check->reason: 'empty', 'unparseable', 'invalid', 'region' or 'type'
    // $check->e164, ->region, ->typeName are set whenever the number parsed to a valid one
    return;
}
$sms->send($check->e164, $text);

// or just the normalised number, null when it does not pass:
$e164 = PhoneNumberRules::normalise('06-12345678');   // '+31612345678'
```

`check()` takes the same optional overrides as the field: `check($raw, $defaultRegion,
$allowedRegions, $allowedTypes)`.

## Messages

Dutch and English, in `lang/`. Keys on `Restruct\PhoneNumberField\PhoneNumberField`:
`INVALID`, `REGION_NOT_ALLOWED`, `MOBILE_REQUIRED` (used when the allowed types only admit mobile
numbers), `TYPE_NOT_ALLOWED`.

## Running the tests

From a host project that requires this module through a symlinked path repository (`/tests` is
`export-ignore`, so a dist install has no tests):

```bash
vendor/bin/phpunit vendor/restruct/silverstripe-phonenumberfield/tests
```

CI runs the suite on Silverstripe 4.13 (PHP 8.1), 5 (PHP 8.1 and 8.3) and 6 (PHP 8.3 and 8.4),
with libphonenumber 8.x and 9.x each on Silverstripe 5 and 6.

## License

MIT, see [LICENSE](LICENSE).
