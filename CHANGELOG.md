# Changelog

## 1.0.0 (unreleased)

First release.

- `PhoneNumberField`: a `TextField` that accepts only numbers meeting the rules and saves them in
  E.164 form.
- `PhoneNumberValidator`: the same rules on named fields of any existing form, combinable with
  `RequiredFields` through `CompositeValidator`.
- `PhoneNumberRules`: the rules as config (`default_region`, `allowed_regions`, `allowed_types`)
  and a static `check()` / `normalise()` usable outside forms, for instance right before an SMS
  is sent.
- Silverstripe 4.13 and 5, PHP 8.1 and newer. Dutch and English messages.
