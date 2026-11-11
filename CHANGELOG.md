# Changelog

## 1.0.0 (2026-11-11)

First release.

- `PhoneNumberField`: a `TextField` that accepts only numbers meeting the rules and saves them in
  E.164 form.
- `PhoneNumberValidator`: the same rules on named fields of any existing form, combinable with
  `RequiredFields` through `CompositeValidator`.
- `PhoneNumberRules`: the rules as config (`default_region`, `allowed_regions`, `allowed_types`)
  and a static `check()` / `normalise()` usable outside forms, for instance right before an SMS
  is sent.
- Silverstripe 4.13, 5 and 6 from one release line, PHP 8.1 and newer. On Silverstripe 6 the
  field's `validate()` returns a `ValidationResult` and the validator extends
  `SilverStripe\Forms\Validation\Validator`; the module selects the implementation at runtime.
- libphonenumber `^8.13 || ^9`. On 9.x, where `PhoneNumberType` is an enum,
  `PhoneNumberCheck::$type` holds the enum case; on 8.x the int constant. Compare with
  `=== PhoneNumberType::MOBILE` to work on both. `PhoneNumberRules::typeFromValue()` turns an
  int into the installed version's form.
- Dutch and English messages.
